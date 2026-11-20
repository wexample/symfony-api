<?php

namespace Wexample\SymfonyApi\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The api's OpenAPI document — nelmio's, of the configured area — turned into
 * what the api's page (/api/) draws: its operations by group, each with its
 * parameters, the fields of its body, its responses, and examples built from
 * the schemas. The document is the one source: what the page shows is what
 * Swagger UI and the clients read.
 */
final readonly class ApiDocumentationService
{
    private const array METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private const int MAX_DEPTH = 4;

    /**
     * @param array{area: string, access_role: ?string, preview_paths: list<string>} $configuration
     */
    public function __construct(
        #[Autowire(param: 'api_documentation')]
        private array $configuration,
        #[Autowire(service: 'nelmio_api_doc.generator_locator')]
        private ?ContainerInterface $generators = null,
    ) {
    }

    public function getAccessRole(): ?string
    {
        return $this->configuration['access_role'];
    }

    /**
     * Whether a reader lacking the role sees anything.
     */
    public function hasPreview(): bool
    {
        return [] !== $this->configuration['preview_paths'];
    }

    /**
     * The document, or null when NelmioApiDocBundle is not registered in the
     * application or has no such area.
     */
    public function getDocument(): ?array
    {
        $area = $this->configuration['area'];

        if (null === $this->generators || ! $this->generators->has($area)) {
            return null;
        }

        return json_decode($this->generators->get($area)->generate()->toJson(), true);
    }

    /**
     * The operations, grouped by their first tag or else by the first segment
     * of their path after the prefix; in a preview, those of the preview's
     * paths only.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function getOperationGroups(array $document, bool $preview): array
    {
        $groups = [];

        foreach ($document['paths'] ?? [] as $path => $pathItem) {
            if ($preview && ! $this->isPreviewed($path)) {
                continue;
            }

            foreach (self::METHODS as $method) {
                if (! isset($pathItem[$method])) {
                    continue;
                }

                $operation = $this->buildOperation($document, $path, $method, $pathItem[$method], $pathItem['parameters'] ?? []);
                $groups[$operation['group']][] = $operation;
            }
        }

        ksort($groups);

        return $groups;
    }

    private function isPreviewed(string $path): bool
    {
        foreach ($this->configuration['preview_paths'] as $pattern) {
            if (preg_match('#' . str_replace('#', '\\#', $pattern) . '#', $path)) {
                return true;
            }
        }

        return false;
    }

    private function buildOperation(
        array $document,
        string $path,
        string $method,
        array $operation,
        array $sharedParameters
    ): array {
        $parameters = [];

        foreach ([...$sharedParameters, ...($operation['parameters'] ?? [])] as $parameter) {
            $parameter = $this->resolve($document, $parameter);
            $schema = $this->resolve($document, $parameter['schema'] ?? []);
            $parameters[] = [
                'name' => $parameter['name'],
                'in' => $parameter['in'],
                'required' => (bool) ($parameter['required'] ?? false),
                'type' => $this->describeType($document, $schema),
                'description' => $parameter['description'] ?? $schema['description'] ?? null,
                'example' => $parameter['example'] ?? $schema['example'] ?? $schema['default'] ?? null,
            ];
        }

        $bodySchema = $operation['requestBody']['content']['application/json']['schema'] ?? null;
        $responses = [];

        foreach ($operation['responses'] ?? [] as $code => $response) {
            $response = $this->resolve($document, $response);
            $schema = $response['content']['application/json']['schema'] ?? null;
            $responses[] = [
                'code' => (string) $code,
                'description' => $response['description'] ?? '',
                'example' => null !== $schema && str_starts_with((string) $code, '2') ? $this->encode($this->buildExample($document, $schema)) : null,
            ];
        }

        $segments = explode('/', trim($path, '/'));

        return [
            'id' => $method . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($path)), '-'),
            'group' => $operation['tags'][0] ?? ($segments[1] ?? $segments[0]),
            'method' => strtoupper($method),
            'path' => $path,
            'summary' => $operation['summary'] ?? null,
            'description' => $operation['description'] ?? null,
            'deprecated' => (bool) ($operation['deprecated'] ?? false),
            'security' => array_values(array_unique(array_merge(...array_map('array_keys', $operation['security'] ?? [[]])))),
            'parameters' => $parameters,
            'body' => null === $bodySchema ? null : [
                'fields' => $this->buildFields($document, $bodySchema),
                'example' => $this->encode($this->buildExample($document, $bodySchema)),
            ],
            'responses' => $responses,
        ];
    }

    /**
     * The properties of an object schema, nested ones named by their path
     * (`address.city`, `items[].key`).
     *
     * @return list<array{name: string, type: string, required: bool, description: ?string}>
     */
    private function buildFields(
        array $document,
        array $schema,
        string $prefix = '',
        int $depth = 0
    ): array {
        $schema = $this->flatten($document, $schema);

        if ('array' === ($schema['type'] ?? null) && isset($schema['items'])) {
            return $this->buildFields($document, $schema['items'], $prefix . '[]', $depth);
        }

        if ($depth >= self::MAX_DEPTH || ! isset($schema['properties'])) {
            return [];
        }

        $fields = [];

        foreach ($schema['properties'] as $name => $property) {
            $property = $this->flatten($document, $property);
            $fullName = '' === $prefix ? $name : $prefix . '.' . $name;
            $fields[] = [
                'name' => $fullName,
                'type' => $this->describeType($document, $property),
                'required' => in_array($name, $schema['required'] ?? [], true),
                'description' => $property['description'] ?? null,
            ];
            array_push($fields, ...$this->buildFields($document, $property, $fullName, $depth + 1));
        }

        return $fields;
    }

    /**
     * A value the schema accepts: its example, its default, its first choice,
     * or a placeholder of its type.
     */
    private function buildExample(
        array $document,
        array $schema,
        int $depth = 0
    ): mixed {
        $schema = $this->flatten($document, $schema);

        foreach (['example', 'default'] as $key) {
            if (array_key_exists($key, $schema)) {
                return $schema[$key];
            }
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }

        $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : null);
        $type = is_array($type) ? (array_values(array_diff($type, ['null']))[0] ?? 'null') : $type;

        return match ($type) {
            'object' => $depth >= self::MAX_DEPTH ? new \stdClass() : (object) array_map(
                fn (array $property) => $this->buildExample($document, $property, $depth + 1),
                $schema['properties'] ?? []
            ),
            'array' => $depth >= self::MAX_DEPTH || ! isset($schema['items']) ? [] : [$this->buildExample($document, $schema['items'], $depth + 1)],
            'integer' => 0,
            'number' => 0.0,
            'boolean' => true,
            'string' => match ($schema['format'] ?? null) {
                'date-time' => '2026-01-01T12:00:00+00:00',
                'date' => '2026-01-01',
                'uuid' => '01960000-0000-7000-8000-000000000000',
                'email' => 'someone@example.com',
                'uri' => 'https://example.com',
                default => 'string',
            },
            default => null,
        };
    }

    /**
     * `string`, `integer[]`, `Account`, `string|null`.
     */
    private function describeType(
        array $document,
        array $schema
    ): string {
        if (isset($schema['$ref'])) {
            return substr(strrchr($schema['$ref'], '/'), 1);
        }

        foreach (['oneOf', 'anyOf'] as $key) {
            if (isset($schema[$key])) {
                return implode('|', array_map(fn (array $option) => $this->describeType($document, $option), $schema[$key]));
            }
        }

        if (isset($schema['allOf'])) {
            return $this->describeType($document, $schema['allOf'][0]);
        }

        $type = $schema['type'] ?? 'mixed';

        if ('array' === $type) {
            return $this->describeType($document, $schema['items'] ?? []) . '[]';
        }

        $type = is_array($type) ? implode('|', $type) : $type;

        return isset($schema['format']) ? $type . ' (' . $schema['format'] . ')' : $type;
    }

    /**
     * The schema itself: a reference followed, `allOf` merged, the first of
     * `oneOf` / `anyOf`.
     */
    private function flatten(
        array $document,
        array $schema
    ): array {
        $schema = $this->resolve($document, $schema);

        if (isset($schema['allOf'])) {
            $merged = array_diff_key($schema, ['allOf' => true]);

            foreach ($schema['allOf'] as $part) {
                $part = $this->flatten($document, $part);
                // A later part narrows what an earlier one said: the envelope's
                // `data`, then the route's own.
                $merged['properties'] = array_replace($merged['properties'] ?? [], $part['properties'] ?? []);
                $merged['required'] = [...($merged['required'] ?? []), ...($part['required'] ?? [])];
                $merged += $part;
            }

            return $merged;
        }

        foreach (['oneOf', 'anyOf'] as $key) {
            if (isset($schema[$key])) {
                $options = array_values(array_filter($schema[$key], static fn (array $option) => 'null' !== ($option['type'] ?? null)));

                return $this->flatten($document, $options[0] ?? []);
            }
        }

        return $schema;
    }

    private function resolve(
        array $document,
        array $node
    ): array {
        for ($hops = 0; isset($node['$ref']) && $hops < 10; ++$hops) {
            $target = $document;

            foreach (explode('/', substr($node['$ref'], 2)) as $key) {
                $target = $target[str_replace(['~1', '~0'], ['/', '~'], $key)] ?? [];
            }

            $node = $target + array_diff_key($node, ['$ref' => true]);
        }

        return $node;
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
