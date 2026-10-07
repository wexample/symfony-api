<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Opis\JsonSchema\CompliantValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Service\UserTokenService;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Person;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * The OpenAPI documents NelmioApiDocBundle generates through this package's
 * bridge: one per version, valid, complete, and true to the routes.
 */
class ApiDocumentationTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private const array AREAS = ['default', 'app', 'ops', 'script', 'tokens', 'v1', 'v2'];

    /**
     * The areas whose every operation a sample request can pass: the token
     * management routes take ids and are exercised by UserTokenManagementTest.
     */
    private const array SAMPLED_AREAS = ['default', 'app', 'ops', 'script', 'v1', 'v2'];

    private const string SCHEMA_ID = 'https://spec.openapis.org/oas/3.1/schema/2022-10-07';

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
    }

    public function testEveryDocumentValidatesAgainstOpenApi31(): void
    {
        // The official schema reaches the schema objects through
        // `$dynamicRef: #meta`, which opis resolves to the document root; its
        // default target, `#/$defs/schema`, is what it means without a dialect.
        $schema = str_replace(
            '"$dynamicRef": "#meta"',
            '"$ref": "#/$defs/schema"',
            file_get_contents(__DIR__ . '/../Fixtures/openapi/schema-3.1.json')
        );
        // Compliant: the default validator writes `default` values into the
        // document it checks.
        $validator = new CompliantValidator();
        $validator->resolver()->registerRaw($schema, self::SCHEMA_ID);
        $validator->setMaxErrors(10);

        foreach (self::AREAS as $area) {
            $document = json_decode($this->generate($area));
            $result = $validator->validate($document, self::SCHEMA_ID);

            $this->assertTrue($result->isValid(), $area . ': ' . json_encode($result->error() ? (new \Opis\JsonSchema\Errors\ErrorFormatter())->format($result->error()) : null));
            $this->assertSame('3.1.0', $document->openapi);
        }
    }

    public function testEveryApiRouteIsDocumentedOnceAndNothingElse(): void
    {
        $expected = [];
        foreach (self::getContainer()->get('router')->getRouteCollection() as $route) {
            $controller = explode('::', (string) $route->getDefault('_controller'))[0];

            if (is_a($controller, AbstractApiController::class, true)) {
                foreach ($route->getMethods() ?: ['GET'] as $method) {
                    $expected[] = strtolower($method) . ' ' . $route->getPath();
                }
            }
        }

        $documented = [];
        foreach (self::AREAS as $area) {
            foreach ($this->document($area)['paths'] as $path => $operations) {
                foreach (array_keys($operations) as $method) {
                    $documented[] = $method . ' ' . $path;
                }
            }
        }

        sort($expected);
        sort($documented);
        $this->assertSame($expected, $documented);
    }

    public function testOperationsCarryTheirBodyEnvelopeSecurityAndRefusals(): void
    {
        $default = $this->document('default');

        $readings = $default['paths']['/api/device/readings']['post'];
        $this->assertSame([['machineToken' => []]], $readings['security']);
        $this->assertSame(['200', '400', '422', '401', '429'], array_map('strval', array_keys($readings['responses'])));
        $this->assertSame('#/components/schemas/ApiBatchResponse', $readings['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertArrayHasKey('WWW-Authenticate', $readings['responses']['401']['headers']);
        $this->assertArrayHasKey('Retry-After', $readings['responses']['429']['headers']);

        $item = $readings['requestBody']['content']['application/json']['schema']['properties']['items'];
        $this->assertSame(15, $item['maxItems']);
        $this->assertSame(['key', 'data'], $item['items']['required']);
        $this->assertSame('#/components/schemas/ReadingDto', $item['items']['properties']['data']['$ref']);

        $reading = $default['components']['schemas']['ReadingDto'];
        $this->assertSame(['value'], $reading['required']);
        $this->assertSame(0, $reading['properties']['value']['minimum']);
        $this->assertSame(100, $reading['properties']['value']['maximum']);
        // Uploaded files never reach a body schema.
        $this->assertArrayNotHasKey('files', $reading['properties']);

        $whoami = $default['paths']['/api/device/whoami']['get'];
        $this->assertArrayNotHasKey('requestBody', $whoami);
        $this->assertSame(['200', '401', '429'], array_map('strval', array_keys($whoami['responses'])));

        $this->assertSame(['type' => 'http', 'description' => 'A machine token, in the Authorization header only — never in the URL.', 'scheme' => 'bearer'], $default['components']['securitySchemes']['machineToken']);
    }

    public function testEachVersionHasItsOwnDocumentAndOnlyTheDeprecatedOneSaysSo(): void
    {
        $v1 = $this->document('v1');
        $v2 = $this->document('v2');

        $this->assertSame(['/api/device/v1/echo'], array_keys($v1['paths']));
        $this->assertSame(['/api/device/v2/echo'], array_keys($v2['paths']));
        $this->assertSame(['value'], $v1['components']['schemas']['EchoDto']['required']);
        $this->assertSame(['value', 'unit'], $v2['components']['schemas']['EchoDto']['required']);

        $old = $v1['paths']['/api/device/v1/echo']['post'];
        $this->assertTrue($old['deprecated']);
        $this->assertStringContainsString('2027-03-01', $old['description']);
        $this->assertArrayHasKey('Sunset', $old['responses']['200']['headers']);

        $this->assertArrayNotHasKey('deprecated', $v2['paths']['/api/device/v2/echo']['post']);
    }

    public function testSecurityIsReadFromTheFirewallCoveringEachRoute(): void
    {
        $app = $this->document('app');
        $account = $app['paths']['/api/app/account']['get'];

        $this->assertSame([['sessionCookie' => []]], $account['security']);
        $this->assertSame(['type' => 'apiKey', 'description' => 'The session of the signed-in user, as the pages use it.', 'name' => 'PHPSESSID', 'in' => 'cookie'], $app['components']['securitySchemes']['sessionCookie']);
        $this->assertSame(['200', '400', '401', '403'], array_map('strval', array_keys($account['responses'])));
        $this->assertArrayNotHasKey('machineToken', $app['components']['securitySchemes']);

        // No `bearer_paths` in the fixture: the machine firewall is recognised by its access_token authenticator.
        $this->assertSame([['machineToken' => []]], $this->document('default')['paths']['/api/device/whoami']['get']['security']);
    }

    public function testResponseDataIsDescribedWhereTheRouteDeclaresIt(): void
    {
        $default = $this->document('default');
        $list = $default['paths']['/api/device/readings']['get']['responses']['200']['content']['application/json']['schema'];

        $this->assertSame('#/components/schemas/ApiSuccessResponse', $list['allOf'][0]['$ref']);
        $data = $list['allOf'][1]['properties']['data'];
        $this->assertSame(['items', 'pagination'], $data['required']);
        $this->assertSame('#/components/schemas/ReadingRowDto', $data['properties']['items']['items']['$ref']);
        $this->assertSame('#/components/schemas/ApiPagination', $data['properties']['pagination']['$ref']);
        $this->assertSame(['page', 'length', 'total', 'pagesCount', 'hasMore'], $default['components']['schemas']['ApiPagination']['required']);

        $account = $this->document('app')['paths']['/api/app/account']['get']['responses']['200']['content']['application/json']['schema'];
        $this->assertSame('#/components/schemas/AccountDto', $account['allOf'][1]['properties']['data']['$ref']);

        // Without the declaration, data stays an open object.
        $this->assertSame('#/components/schemas/ApiSuccessResponse', $default['paths']['/api/device/whoami']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
    }

    public function testErrorsAndQueryFormatsAreDescribed(): void
    {
        $default = $this->document('default');

        $this->assertSame(['errorCode', 'kind', 'issues', 'summary'], $default['components']['schemas']['ApiValidationErrorData']['required']);
        $this->assertSame('#/components/schemas/ApiValidationErrorData', $default['components']['schemas']['ApiErrorResponse']['properties']['data']['anyOf'][1]['$ref']);
        $this->assertSame('#/components/schemas/ApiValidationErrorData', $default['components']['schemas']['ApiBatchResponse']['properties']['data']['properties']['items']['items']['properties']['errors']['$ref']);

        $parameters = array_column($this->document('app')['paths']['/api/app/account']['get']['parameters'], 'schema', 'name');
        $this->assertSame('string', $parameters['date']['type']);
        $this->assertMatchesRegularExpression('/' . $parameters['date']['pattern'] . '/', '2026-10-05 14:30');
        $this->assertDoesNotMatchRegularExpression('/' . $parameters['date']['pattern'] . '/', '05/10/2026');
        $this->assertContains('medium', $parameters['display-format']['enum']);
    }

    /**
     * Requests built from nothing but the documents are accepted by the routes
     * they describe.
     */
    public function testRequestsBuiltFromTheDocumentsAreAccepted(): void
    {
        $token = $this->getMachineTokenService()->issue($this->createDevice());
        $person = new Person('ada@example.com', ['ROLE_USER', 'ROLE_IMPORTER']);
        $this->entityManager->persist($person);
        $this->entityManager->flush();
        $userToken = self::getContainer()->get(UserTokenService::class)->issue($person);
        // The session routes, signed in the way the pages are.
        $this->client->loginUser(new InMemoryUser('jane', 'secret', ['ROLE_USER']), 'main');

        foreach (self::SAMPLED_AREAS as $area) {
            $document = $this->document($area);

            foreach ($document['paths'] as $path => $operations) {
                foreach ($operations as $method => $operation) {
                    $schema = $operation['requestBody']['content']['application/json']['schema'] ?? null;

                    $this->client->request(
                        strtoupper($method),
                        $path,
                        server: ['app' === $area ? 'HTTP_ACCEPT' : 'HTTP_AUTHORIZATION' => 'app' === $area ? 'application/json' : 'Bearer ' . ('script' === $area ? $userToken : $token), 'CONTENT_TYPE' => 'application/json'],
                        content: null === $schema ? null : json_encode($this->buildSample($schema, $document))
                    );

                    $this->assertResponseIsSuccessful($method . ' ' . $path . ': ' . $this->client->getResponse()->getContent());
                    $body = json_decode($this->client->getResponse()->getContent(), true);
                    $this->assertSame('success', $body['type']);

                    // A batch report: each item stored.
                    foreach ($body['data']['items'] ?? [] as $reported) {
                        if (isset($reported['outcome'])) {
                            $this->assertSame('accepted', $reported['outcome'], $path);
                        }
                    }
                }
            }
        }
    }

    /**
     * The smallest value a schema accepts: required properties only.
     */
    private function buildSample(array $schema, array $document): mixed
    {
        if (isset($schema['$ref'])) {
            $name = substr($schema['$ref'], strlen('#/components/schemas/'));

            return $this->buildSample($document['components']['schemas'][$name], $document);
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }

        $type = is_array($schema['type'] ?? null) ? $schema['type'][0] : ($schema['type'] ?? 'object');

        return match ($type) {
            'object' => array_combine(
                $schema['required'] ?? [],
                array_map(fn (string $name) => $this->buildSample($schema['properties'][$name], $document), $schema['required'] ?? [])
            ) ?: new \stdClass(),
            'array' => [$this->buildSample($schema['items'], $document)],
            'integer' => (int) ($schema['minimum'] ?? 1),
            'number' => (float) ($schema['minimum'] ?? 1),
            'boolean' => true,
            default => 'sample-' . bin2hex(random_bytes(4)),
        };
    }

    private function document(string $area): array
    {
        return json_decode($this->generate($area), true);
    }

    private function generate(string $area): string
    {
        return self::getContainer()->get('nelmio_api_doc.generator.' . $area)->generate()->toJson();
    }
}
