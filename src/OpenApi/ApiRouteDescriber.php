<?php

namespace Wexample\SymfonyApi\OpenApi;

use DateTimeImmutable;
use DateTimeZone;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type as TypeConstraint;
use Wexample\SymfonyApi\Api\Attribute\ApiBatch;
use Wexample\SymfonyApi\Api\Attribute\ApiResponseData;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\Trait\QueryOptionConstrainedTrait;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Class\ApiValidationErrorData;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Enum\BatchItemOutcome;
use Wexample\SymfonyApi\Helper\ApiVersionHelper;
use Wexample\SymfonyHelpers\Validator\DateQueryStringConstraint;

/**
 * Teaches NelmioApiDocBundle what this package's attributes mean, for the
 * routes of AbstractApiController subclasses: the body DTO, the query
 * options, the batch shape, the envelopes, the bearer refusals and the
 * deprecation of a version.
 */
class ApiRouteDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;
    use RouteDescriberTrait;

    final public const string SECURITY_SCHEME = 'machineToken';

    final public const string SECURITY_SCHEME_SESSION = 'sessionCookie';

    /**
     * @param list<string> $bearerPaths
     * @param array<string, array{deprecation: ?string, sunset: ?string, link: ?string}> $versions
     */
    public function __construct(
        #[Autowire(param: 'api_openapi_bearer_paths')]
        private readonly array $bearerPaths,
        #[Autowire(param: 'api_versions')]
        private readonly array $versions,
        #[Autowire(param: 'api_machine_token_rate_limit_enabled')]
        private readonly bool $rateLimitEnabled,
        #[Autowire(service: 'security.firewall.map')]
        private readonly FirewallMap $firewallMap,
        // Read when needed: an application without sessions has no such parameter.
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function describe(OA\OpenApi $api, Route $route, ReflectionMethod $reflectionMethod): void
    {
        if (! is_a($reflectionMethod->getDeclaringClass()->getName(), AbstractApiController::class, true)) {
            return;
        }

        $version = ApiVersionHelper::fromPath($route->getPath());
        $deprecation = null !== $version ? ($this->versions[$version] ?? null) : null;

        foreach ($this->getOperations($api, $route) as $operation) {
            $security = $this->getSecurity($route, $operation->method);

            $this->describeRequest($operation, $reflectionMethod);
            $this->describeResponses($api, $operation, $reflectionMethod, $security);

            if (null !== $security) {
                $this->declareSecurityScheme($api, $security);
                Util::modifyAnnotationValue($operation, 'security', [[$security => []]]);
            }

            if ($deprecation && null !== $deprecation['deprecation']) {
                $this->describeDeprecation($operation, $deprecation);
            }
        }
    }

    private function describeRequest(OA\Operation $operation, ReflectionMethod $method): void
    {
        foreach ($this->getAttributes($method, QueryOptionConstrainedTrait::class) as $option) {
            $parameter = Util::getOperationParameter($operation, $option->key, 'query');
            Util::modifyAnnotationValue($parameter, 'required', $option->required);
            $schema = Util::getChild($parameter, OA\Schema::class);
            $this->describeQuerySchema($schema, $option->getConstraint());

            if (null !== $option->default) {
                Util::modifyAnnotationValue($schema, 'default', $option->default);
            }

            if ($option instanceof SortQueryOption) {
                Util::modifyAnnotationValue($parameter, 'description', 'Sort by ' . implode(', ', array_keys($option->allowed)) . ', separated by commas, "-" before a name for descending.');
            }
        }

        $content = $this->getAttributes($method, ValidateRequestContent::class)[0] ?? null;
        $batch = $this->getAttributes($method, ApiBatch::class)[0] ?? null;

        if ($content) {
            $this->setJsonBody($operation, ['ref' => $this->registerModel($content->dto)]);
        }

        if ($batch) {
            $this->setJsonBody($operation, [
                'type' => 'object',
                'required' => ['items'],
                'properties' => [
                    new OA\Property([
                        'property' => 'items',
                        'type' => 'array',
                        'maxItems' => $batch->maxItems ?? Generator::UNDEFINED,
                        'items' => new OA\Items([
                            'type' => 'object',
                            'required' => ['key', 'data'],
                            'properties' => [
                                new OA\Property(['property' => 'key', 'type' => 'string', 'maxLength' => 255, 'description' => 'Generated by the sender when it records the item, unique for that sender.']),
                                new OA\Property(['property' => 'data', 'ref' => $this->registerModel($batch->itemDto)]),
                            ],
                        ]),
                    ]),
                ],
            ]);
        }
    }

    private function describeResponses(
        OA\OpenApi $api,
        OA\Operation $operation,
        ReflectionMethod $method,
        ?string $security
    ): void {
        $batch = ! empty($this->getAttributes($method, ApiBatch::class));
        $this->declareEnvelopes($api, $batch);
        $data = $this->getAttributes($method, ApiResponseData::class)[0] ?? null;

        if ($data && ! $batch) {
            $schema = $this->setResponseSchema($operation, '200', 'Success.');
            // Created through Util so that swagger-php sees them nested, not
            // as components missing their name.
            Util::createCollectionItem($schema, 'allOf', OA\Schema::class, ['ref' => '#/components/schemas/ApiSuccessResponse']);
            $own = $schema->allOf[Util::createCollectionItem($schema, 'allOf', OA\Schema::class, ['type' => 'object'])];
            $this->describeData($api, Util::getProperty($own, 'data'), $data);
        } else {
            $this->setResponse(
                $operation,
                '200',
                $batch ? 'The batch was read: the outcome of each item is in the report.' : 'Success.',
                $batch ? '#/components/schemas/ApiBatchResponse' : '#/components/schemas/ApiSuccessResponse'
            );
        }

        if (! empty($this->getAttributes($method, ValidateRequestContent::class))
            || ! empty($this->getAttributes($method, QueryOptionConstrainedTrait::class))
            || $batch
        ) {
            $this->setResponse($operation, '400', 'The request could not be read or validated.', '#/components/schemas/ApiErrorResponse');
        }

        if ($batch) {
            $this->setResponse($operation, '422', 'Too many items: the batch is refused whole.', '#/components/schemas/ApiErrorResponse');
        }

        if (self::SECURITY_SCHEME === $security) {
            $this->setResponse($operation, '401', 'Missing, unknown, revoked or expired token — one response for all.', '#/components/schemas/ApiErrorResponse', [
                'WWW-Authenticate' => 'Bearer, with error="invalid_token" when a token was presented.',
            ]);

            if ($this->rateLimitEnabled) {
                $this->setResponse($operation, '429', 'Over the rate limit of the client or of the address.', '#/components/schemas/ApiErrorResponse', [
                    'Retry-After' => 'Seconds to wait before the next request.',
                ]);
            }
        }

        if (self::SECURITY_SCHEME_SESSION === $security) {
            $this->setResponse($operation, '401', 'No session. Send `Accept: application/json`, or the firewall may answer with a redirect to its login page instead.', '#/components/schemas/ApiErrorResponse');
            $this->setResponse($operation, '403', 'Signed in, without the rights this route requires.', '#/components/schemas/ApiErrorResponse');
        }
    }

    /**
     * @param array{deprecation: ?string, sunset: ?string, link: ?string} $config
     */
    private function describeDeprecation(OA\Operation $operation, array $config): void
    {
        Util::modifyAnnotationValue($operation, 'deprecated', true);

        $headers = ['Deprecation' => 'Deprecated since ' . $this->formatDate($config['deprecation']) . ' (RFC 9745).'];
        if (null !== $config['sunset']) {
            $headers['Sunset'] = 'Removed on ' . $this->formatDate($config['sunset']) . ' (RFC 8594).';
        }

        foreach ($operation->responses as $response) {
            $this->addHeaders($response, $headers);
        }

        $description = 'Deprecated since ' . $this->formatDate($config['deprecation'])
            . (null !== $config['sunset'] ? ', removed on ' . $this->formatDate($config['sunset']) : '')
            . (null !== $config['link'] ? ': ' . $config['link'] : '') . '.';
        Util::modifyAnnotationValue($operation, 'description', $description);
    }

    private function declareSecurityScheme(OA\OpenApi $api, string $scheme): void
    {
        $components = Util::getChild($api, OA\Components::class);

        Util::getCollectionItem($components, OA\SecurityScheme::class, self::SECURITY_SCHEME === $scheme
            ? [
                'securityScheme' => self::SECURITY_SCHEME,
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => 'A machine token, in the Authorization header only — never in the URL.',
            ]
            : [
                'securityScheme' => self::SECURITY_SCHEME_SESSION,
                'type' => 'apiKey',
                'in' => 'cookie',
                'name' => ($this->parameterBag->has('session.storage.options') ? $this->parameterBag->get('session.storage.options')['name'] ?? null : null) ?? 'PHPSESSID',
                'description' => 'The session of the signed-in user, as the pages use it.',
            ]);
    }

    /**
     * How the firewall covering the route authenticates its caller: a token
     * per request, a session, or nothing.
     */
    private function getSecurity(Route $route, string $method): ?string
    {
        if ($this->isBearerPath($route->getPath())) {
            return self::SECURITY_SCHEME;
        }

        $path = preg_replace('/\{[^}]+}/', 'x', $route->getPath());
        $config = $this->firewallMap->getFirewallConfig(Request::create($path, strtoupper($method)));

        if (null === $config || ! $config->isSecurityEnabled()) {
            return null;
        }

        if (in_array('access_token', $config->getAuthenticators(), true)) {
            return self::SECURITY_SCHEME;
        }

        return $config->isStateless() ? null : self::SECURITY_SCHEME_SESSION;
    }

    /**
     * The envelope every response of the package shares, success and error.
     */
    private function declareEnvelopes(OA\OpenApi $api, bool $withBatch): void
    {
        $validation = Util::getSchema($api, 'ApiValidationErrorData');
        if (Generator::isDefault($validation->properties)) {
            Util::merge($validation, new OA\Schema([
                'type' => 'object',
                'required' => ['errorCode', 'kind', 'issues', 'summary'],
                'properties' => [
                    new OA\Property(['property' => 'errorCode', 'type' => 'string']),
                    new OA\Property(['property' => 'kind', 'type' => 'string', 'enum' => [ApiValidationErrorData::KIND_VALIDATION_COLLECTION]]),
                    new OA\Property([
                        'property' => 'issues',
                        'type' => 'array',
                        'items' => new OA\Items([
                            'type' => 'object',
                            'required' => ['code', 'path'],
                            'properties' => [
                                new OA\Property(['property' => 'code', 'type' => 'string', 'description' => 'Stable, for a program to read.']),
                                new OA\Property(['property' => 'path', 'type' => 'string', 'description' => 'The field at fault, empty for the whole body.']),
                                new OA\Property(['property' => 'message', 'type' => 'string', 'description' => 'For a person.']),
                                new OA\Property(['property' => 'meta', 'type' => 'object']),
                            ],
                        ]),
                    ]),
                    new OA\Property([
                        'property' => 'summary',
                        'type' => 'object',
                        'required' => ['global', 'fields', 'count'],
                        'properties' => [
                            new OA\Property(['property' => 'global', 'type' => 'array', 'items' => new OA\Items(['type' => 'string'])]),
                            new OA\Property(['property' => 'fields', 'type' => 'object', 'additionalProperties' => new OA\AdditionalProperties(['type' => 'array', 'items' => new OA\Items(['type' => 'string'])])]),
                            new OA\Property(['property' => 'count', 'type' => 'integer']),
                        ],
                    ]),
                ],
            ]));
        }

        $success = Util::getSchema($api, 'ApiSuccessResponse');
        if (Generator::isDefault($success->properties)) {
            Util::merge($success, new OA\Schema([
                'type' => 'object',
                'required' => ['type', 'code', 'data'],
                'properties' => [
                    new OA\Property(['property' => 'type', 'type' => 'string', 'enum' => ['success']]),
                    new OA\Property(['property' => 'code', 'type' => 'integer']),
                    new OA\Property(['property' => 'message', 'type' => 'string']),
                    new OA\Property(['property' => 'data', 'type' => 'object']),
                ],
            ]));
        }

        $error = Util::getSchema($api, 'ApiErrorResponse');
        if (Generator::isDefault($error->properties)) {
            Util::merge($error, new OA\Schema([
                'type' => 'object',
                'required' => ['type', 'code', 'message', 'data'],
                'properties' => [
                    new OA\Property(['property' => 'type', 'type' => 'string', 'enum' => ['error']]),
                    new OA\Property(['property' => 'code', 'type' => 'integer']),
                    new OA\Property(['property' => 'message', 'type' => 'string']),
                ],
            ]));

            $data = Util::getProperty($error, 'data');
            Util::modifyAnnotationValue($data, 'description', 'Empty, or the validation issues of a refused body or query.');
            Util::createCollectionItem($data, 'anyOf', OA\Schema::class, ['type' => 'object', 'maxProperties' => 0]);
            Util::createCollectionItem($data, 'anyOf', OA\Schema::class, ['ref' => '#/components/schemas/ApiValidationErrorData']);
        }

        if (! $withBatch) {
            return;
        }

        $batch = Util::getSchema($api, 'ApiBatchResponse');
        if (Generator::isDefault($batch->properties)) {
            $outcomes = array_map(fn (BatchItemOutcome $outcome) => $outcome->value, BatchItemOutcome::cases());

            Util::merge($batch, new OA\Schema([
                'type' => 'object',
                'required' => ['type', 'code', 'data'],
                'properties' => [
                    new OA\Property(['property' => 'type', 'type' => 'string', 'enum' => ['success']]),
                    new OA\Property(['property' => 'code', 'type' => 'integer']),
                    new OA\Property([
                        'property' => 'data',
                        'type' => 'object',
                        'required' => ['items', 'summary'],
                        'properties' => [
                            new OA\Property([
                                'property' => 'items',
                                'type' => 'array',
                                'items' => new OA\Items([
                                    'type' => 'object',
                                    'required' => ['index', 'key', 'outcome'],
                                    'properties' => [
                                        new OA\Property(['property' => 'index', 'type' => 'integer']),
                                        new OA\Property(['property' => 'key', 'type' => ['string', 'null']]),
                                        new OA\Property([
                                            'property' => 'outcome',
                                            'type' => 'string',
                                            'enum' => $outcomes,
                                            'description' => 'accepted, duplicate: drop it. rejected, conflict: drop it, it will never pass. error: keep it and send it again.',
                                        ]),
                                        new OA\Property(['property' => 'result', 'description' => 'What the endpoint returned for the item, given back on a replay.']),
                                        new OA\Property(['property' => 'errors', 'ref' => '#/components/schemas/ApiValidationErrorData', 'description' => 'For rejected and conflict.']),
                                    ],
                                ]),
                            ]),
                            new OA\Property([
                                'property' => 'summary',
                                'type' => 'object',
                                'properties' => array_map(
                                    fn (string $outcome) => new OA\Property(['property' => $outcome, 'type' => 'integer']),
                                    $outcomes
                                ),
                            ]),
                        ],
                    ]),
                ],
            ]));
        }
    }

    private function describeData(OA\OpenApi $api, OA\Property $property, ApiResponseData $data): void
    {
        $ref = $this->registerModel($data->class);

        if (! $data->collection && ! $data->paginated) {
            Util::modifyAnnotationValue($property, 'ref', $ref);

            return;
        }

        Util::modifyAnnotationValue($property, 'type', 'object');
        $items = Util::getProperty($property, 'items');
        Util::modifyAnnotationValue($items, 'type', 'array');
        Util::getChild($items, OA\Items::class, ['ref' => $ref]);
        $required = ['items'];

        if ($data->paginated) {
            $this->declarePagination($api);
            Util::modifyAnnotationValue(Util::getProperty($property, 'pagination'), 'ref', '#/components/schemas/ApiPagination');
            $required[] = 'pagination';
        }

        Util::modifyAnnotationValue($property, 'required', $required);
    }

    private function declarePagination(OA\OpenApi $api): void
    {
        $pagination = Util::getSchema($api, 'ApiPagination');
        if (! Generator::isDefault($pagination->properties)) {
            return;
        }

        Util::merge($pagination, new OA\Schema([
            'type' => 'object',
            'required' => ['page', 'length', 'total', 'pagesCount', 'hasMore'],
            'properties' => [
                new OA\Property(['property' => 'page', 'type' => 'integer', 'minimum' => 0, 'description' => 'Zero-based.']),
                new OA\Property(['property' => 'length', 'type' => ['integer', 'null'], 'description' => 'Null: no limit.']),
                new OA\Property(['property' => 'total', 'type' => ['integer', 'null'], 'description' => 'Null: not counted.']),
                new OA\Property(['property' => 'pagesCount', 'type' => ['integer', 'null']]),
                new OA\Property(['property' => 'hasMore', 'type' => ['boolean', 'null']]),
            ],
        ]));
    }

    private function setResponseSchema(
        OA\Operation $operation,
        string $code,
        string $description
    ): OA\Schema {
        $response = Util::getIndexedCollectionItem($operation, OA\Response::class, $code);

        if (Generator::isDefault($response->description)) {
            Util::modifyAnnotationValue($response, 'description', $description);
        }

        $mediaType = Util::getIndexedCollectionItem($response, OA\MediaType::class, 'application/json');

        return Util::getChild($mediaType, OA\Schema::class);
    }

    /**
     * Type, format and allowed values of a query option, read from its constraint.
     */
    private function describeQuerySchema(OA\Schema $schema, Constraint $constraint): void
    {
        if ($constraint instanceof Sequentially) {
            foreach ($constraint->constraints as $inner) {
                $this->describeQuerySchema($schema, $inner);
            }

            return;
        }

        if ($constraint instanceof Choice && is_array($constraint->choices)) {
            Util::modifyAnnotationValue($schema, 'type', 'string');
            Util::modifyAnnotationValue($schema, 'enum', array_values($constraint->choices));

            return;
        }

        if ($constraint instanceof DateQueryStringConstraint) {
            Util::modifyAnnotationValue($schema, 'type', 'string');
            Util::modifyAnnotationValue($schema, 'pattern', '^\\d{4}(-\\d{2}(-\\d{2}( \\d{2}(:\\d{2}(:\\d{2})?)?)?)?)?$');
            Util::modifyAnnotationValue($schema, 'description', 'Y, Y-m, Y-m-d, Y-m-d H, Y-m-d H:i or Y-m-d H:i:s.');

            return;
        }

        if ($constraint instanceof Regex && null !== $constraint->pattern) {
            Util::modifyAnnotationValue($schema, 'type', 'string');

            return;
        }

        Util::modifyAnnotationValue($schema, 'type', $this->getQueryType($constraint));
    }

    private function setJsonBody(OA\Operation $operation, array $schema): void
    {
        $body = Util::getChild($operation, OA\RequestBody::class);
        Util::modifyAnnotationValue($body, 'required', true);
        $mediaType = Util::getIndexedCollectionItem($body, OA\MediaType::class, 'application/json');
        Util::getChild($mediaType, OA\Schema::class, $schema);
    }

    /**
     * @param array<string, string> $headers
     */
    private function setResponse(
        OA\Operation $operation,
        string $code,
        string $description,
        string $ref,
        array $headers = []
    ): void {
        $response = Util::getIndexedCollectionItem($operation, OA\Response::class, $code);

        if (Generator::isDefault($response->description)) {
            Util::modifyAnnotationValue($response, 'description', $description);
        }

        $mediaType = Util::getIndexedCollectionItem($response, OA\MediaType::class, 'application/json');
        Util::getChild($mediaType, OA\Schema::class, ['ref' => $ref]);
        $this->addHeaders($response, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function addHeaders(OA\Response $response, array $headers): void
    {
        foreach ($headers as $name => $description) {
            $header = Util::getCollectionItem($response, OA\Header::class, ['header' => $name]);
            Util::modifyAnnotationValue($header, 'description', $description);
            Util::getChild($header, OA\Schema::class, ['type' => 'string']);
        }
    }

    private function registerModel(string $class): string
    {
        return $this->modelRegistry->register(new Model(Type::object($class)));
    }

    private function getQueryType(object $constraint): string
    {
        $type = $constraint instanceof TypeConstraint ? (array) $constraint->type : [];

        return match (true) {
            [] !== array_intersect($type, ['int', 'integer']) => 'integer',
            [] !== array_intersect($type, ['float', 'numeric']) => 'number',
            [] !== array_intersect($type, ['bool', 'boolean']) => 'boolean',
            default => 'string',
        };
    }

    private function isBearerPath(string $path): bool
    {
        foreach ($this->bearerPaths as $pattern) {
            if (preg_match('{' . $pattern . '}', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T of object
     * @param class-string<T> $class A class or, for the query options, a trait
     * @return list<T>
     */
    private function getAttributes(ReflectionMethod $method, string $class): array
    {
        $instances = [];

        foreach ([...$method->getDeclaringClass()->getAttributes(), ...$method->getAttributes()] as $attribute) {
            $name = $attribute->getName();

            if (is_a($name, $class, true) || (trait_exists($class) && in_array($class, $this->getTraits($name), true))) {
                $instances[] = $attribute->newInstance();
            }
        }

        return $instances;
    }

    /**
     * @return list<string>
     */
    private function getTraits(string $class): array
    {
        $traits = [];

        for ($current = $class; $current; $current = get_parent_class($current)) {
            foreach (class_uses($current) as $trait) {
                $traits[] = $trait;
                array_push($traits, ...array_values(class_uses($trait)));
            }
        }

        return $traits;
    }

    private function formatDate(string $date): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('Y-m-d');
    }
}
