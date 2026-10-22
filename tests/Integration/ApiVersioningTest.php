<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * Two versions of one endpoint, side by side, the old one deprecated.
 */
class ApiVersioningTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private string $token;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->token = $this->getMachineTokenService()->issue($this->createDevice());
    }

    public function testBothVersionsAnswerWithTheirOwnDto(): void
    {
        $v1 = $this->post('/api/device/v1/echo', ['value' => 120]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(['version' => 'v1', 'value' => 120], $v1['data']);

        $v2 = $this->post('/api/device/v2/echo', ['value' => 120, 'unit' => 'mmHg']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(['version' => 'v2', 'value' => 120, 'unit' => 'mmHg'], $v2['data']);

        // What v1 accepts, v2 does not.
        $refused = $this->post('/api/device/v2/echo', ['value' => 120]);
        $this->assertSame(400, $this->getStatusCode());
        $this->assertSame(['unit'], array_keys($refused['data']['summary']['fields']));
        // And the other way round.
        $this->post('/api/device/v1/echo', ['value' => 120, 'unit' => 'mmHg']);
        $this->assertSame(400, $this->getStatusCode());
    }

    public function testDeprecatedVersionSaysSoOnEveryResponse(): void
    {
        $this->post('/api/device/v1/echo', ['value' => 1]);
        $success = $this->client->getResponse()->headers;

        $this->assertSame('@' . strtotime('2026-09-01 00:00:00 UTC'), $success->get('Deprecation'));
        $this->assertSame('Mon, 01 Mar 2027 00:00:00 GMT', $success->get('Sunset'));
        $this->assertSame('<https://example.com/api/v1-sunset>; rel="deprecation"; type="text/html"', $success->get('Link'));

        // A refused request too: an old firmware with a bad token learns it.
        $this->post('/api/device/v1/echo', ['value' => 1], 'fx_dev_unknown');
        $this->assertSame(401, $this->getStatusCode());
        $this->assertNotNull($this->client->getResponse()->headers->get('Sunset'));

        $this->post('/api/device/v2/echo', ['value' => 1, 'unit' => 'kPa']);
        $current = $this->client->getResponse()->headers;
        $this->assertNull($current->get('Deprecation'));
        $this->assertNull($current->get('Sunset'));
    }

    public function testVersionReachesTheJournal(): void
    {
        $this->post('/api/device/v1/echo', ['value' => 1], 'fx_dev_unknown');

        $refusals = array_values(array_filter(
            $this->getLogHandler()->getRecords(),
            fn (LogRecord $record) => 'machine_token.refused' === $record->message
        ));

        $this->assertSame('v1', $refusals[0]->context['extra']['api_version']);
    }

    private function post(string $path, array $body, ?string $token = null): ?array
    {
        $this->client->request('POST', $path, server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . ($token ?? $this->token),
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode($body));

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function getLogHandler(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.test');
    }
}
