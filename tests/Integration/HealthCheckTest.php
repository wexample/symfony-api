<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Monolog\Handler\TestHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Fixtures\App\HealthCheck\FixtureHealthCheck;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * The health endpoint, with the package's database probe and one contributed
 * by the application.
 */
class HealthCheckTest extends WebTestCase
{
    use MachineTokenTestTrait;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
    }

    public function testEveryProbePassing(): void
    {
        $body = $this->requestHealth();

        $this->assertSame(200, $this->getStatusCode());
        $this->assertSame('success', $body['type']);
        $this->assertSame('ok', $body['data']['status']);
        $this->assertSame('ok', $body['data']['checks']['database']['status']);
        $this->assertSame('ok', $body['data']['checks']['fixture']['status']);
        $this->assertIsInt($body['data']['checks']['database']['durationMs']);
        $this->assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testOneProbeFailingAnswers503WithoutItsReason(): void
    {
        self::getContainer()->get(FixtureHealthCheck::class)->failing = true;

        $body = $this->requestHealth();
        $content = $this->client->getResponse()->getContent();

        $this->assertSame(503, $this->getStatusCode());
        $this->assertSame('error', $body['type']);
        $this->assertSame('fail', $body['data']['status']);
        $this->assertSame('fail', $body['data']['checks']['fixture']['status']);
        // The others still ran.
        $this->assertSame('ok', $body['data']['checks']['database']['status']);
        // The reason goes to the log, never to the caller.
        $this->assertStringNotContainsString('secret-host', $content);

        /** @var TestHandler $logs */
        $logs = self::getContainer()->get('monolog.handler.test');
        $this->assertTrue($logs->hasErrorThatContains('Health check failed'));
    }

    public function testReachableWithoutAuthentication(): void
    {
        $this->requestHealth();

        $this->assertNotSame(401, $this->getStatusCode());
        $this->assertNull($this->client->getResponse()->headers->get('Set-Cookie'));
    }

    private function requestHealth(): ?array
    {
        $this->client->request('GET', '/api/health');

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
