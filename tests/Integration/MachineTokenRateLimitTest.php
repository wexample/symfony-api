<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * The fixture allows 20 requests per client and 8 failures per address, an hour.
 */
class MachineTokenRateLimitTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private const int CLIENT_LIMIT = 20;

    private const int IP_FAILURE_LIMIT = 8;

    private Device $device;

    private string $token;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->device = $this->createDevice();
        $this->token = $this->getMachineTokenService()->issue($this->device);
    }

    public function testClientOverItsLimitGetsA429(): void
    {
        for ($i = 0; $i < self::CLIENT_LIMIT; $i++) {
            $this->requestWhoami($this->token);
            $this->assertResponseIsSuccessful();
        }

        $payload = $this->requestWhoami($this->token);

        $this->assertSame(429, $this->getStatusCode());
        $this->assertSame(['type' => 'error', 'code' => 429, 'message' => 'Too many requests.', 'data' => []], $payload);
        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(3600, $retryAfter);

        // The test log handler is reset at every request: read it now.
        $throttled = $this->getJournal('machine_token.throttled');
        $this->assertCount(1, $throttled);
        $this->assertSame('client', $throttled[0]->context['cause']);
        $this->assertSame((string) $this->device->getId(), $throttled[0]->context['user_id']);
        $this->assertSame(substr($this->token, 0, strlen('fx_dev_') + 6) . '…', $throttled[0]->context['extra']['token_hint']);

        // Another client is not affected.
        $this->requestWhoami($this->getMachineTokenService()->issue($this->createDevice()));
        $this->assertResponseIsSuccessful();
    }

    public function testBatchCountsAsOneRequest(): void
    {
        $items = array_map(fn (int $i) => ['key' => 'k' . $i, 'data' => ['value' => $i]], range(1, 15));

        for ($i = 0; $i < self::CLIENT_LIMIT; $i++) {
            $this->client->request('POST', '/api/device/readings', server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ], content: json_encode(['items' => $items]));

            $this->assertResponseIsSuccessful();
        }

        $this->requestWhoami($this->token);
        $this->assertSame(429, $this->getStatusCode());
    }

    public function testAddressFailingTooOftenIsStoppedWhateverTheToken(): void
    {
        for ($i = 0; $i < self::IP_FAILURE_LIMIT; $i++) {
            $this->requestWhoami('fx_dev_unknown' . $i);
            $this->assertSame(401, $this->getStatusCode());
        }

        $this->requestWhoami('fx_dev_unknown');
        $this->assertSame(429, $this->getStatusCode());
        $refusal = $this->client->getResponse()->getContent();
        $this->assertSame('ip', $this->getJournal('machine_token.throttled')[0]->context['cause']);

        // Even a valid token, from that address: nothing tells it apart.
        $this->requestWhoami($this->token);
        $this->assertSame(429, $this->getStatusCode());
        $this->assertSame($refusal, $this->client->getResponse()->getContent());

        // Another address goes on.
        $this->requestWhoami($this->token, ['REMOTE_ADDR' => '10.0.0.2']);
        $this->assertResponseIsSuccessful();
    }

    public function testSuccessesDoNotCountAgainstTheAddress(): void
    {
        for ($i = 0; $i < self::IP_FAILURE_LIMIT + 2; $i++) {
            $this->requestWhoami($this->token);
            $this->assertResponseIsSuccessful();
        }
    }

    /**
     * @return list<LogRecord>
     */
    private function getJournal(string $type): array
    {
        return array_values(array_filter(
            $this->getLogHandler()->getRecords(),
            fn (LogRecord $record) => $type === $record->message
        ));
    }

    private function getLogHandler(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.test');
    }
}
