<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use DateInterval;
use DateTimeImmutable;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * Every refusal reaches the journal with its real cause, while the response
 * stays the same; no secret reaches any log.
 */
class MachineTokenJournalTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private Device $device;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->device = $this->createDevice();
    }

    public function testEveryRefusalIsJournalledWithItsCause(): void
    {
        $service = $this->getMachineTokenService();

        $revoked = $service->issue($this->device);
        $service->revoke($service->findTokenBySecret($revoked));
        $expired = $service->issue($this->device, new DateTimeImmutable('-1 second'));

        $roleDevice = $this->createDevice()->setExtraRoles(['ROLE_ADMIN']);
        $role = $service->issue($roleDevice);

        $disabledDevice = $this->createDevice()->setEnabled(false);
        $disabled = $service->issue($disabledDevice);
        $this->entityManager->flush();

        $cases = [
            'unknown' => ['fx_dev_' . str_repeat('a', 43), null],
            'revoked' => [$revoked, $this->device],
            'expired' => [$expired, $this->device],
            'role_not_allowed' => [$role, $roleDevice],
            'client_disabled' => [$disabled, $disabledDevice],
            'missing' => [null, null],
        ];

        $bodies = [];
        foreach ($cases as $cause => [$plain, $device]) {
            $this->getLogHandler()->clear();
            $this->requestWhoami($plain, ['HTTP_X_REQUEST_ID' => 'req-' . $cause]);

            $this->assertSame(401, $this->getStatusCode(), $cause);
            if (null !== $plain) {
                $bodies[$cause] = [
                    $this->client->getResponse()->getContent(),
                    $this->client->getResponse()->headers->get('WWW-Authenticate'),
                ];
            }

            $entries = $this->getJournal('machine_token.refused');
            $this->assertCount(1, $entries, $cause);
            $context = $entries[0]->context;

            $this->assertSame('warning', strtolower($entries[0]->level->getName()));
            $this->assertSame($cause, $context['cause']);
            $this->assertSame('failure', $context['outcome']);
            $this->assertSame('machine', $context['firewall']);
            $this->assertSame('req-' . $cause, $context['request_id']);
            $this->assertSame($device ? (string) $device->getId() : null, $context['user_id']);
            $this->assertSame(
                $plain ? substr($plain, 0, strlen('fx_dev_') + 6) . '…' : null,
                $context['extra']['token_hint'],
                $cause
            );
        }

        // Whatever the cause, the caller reads the same thing.
        $this->assertCount(1, array_unique(array_map('serialize', $bodies)));
    }

    public function testForeignBearerLeavesNoTraceOfItself(): void
    {
        $this->requestWhoami('someone-elses-secret');

        $this->assertSame(401, $this->getStatusCode());
        $this->assertNull($this->getJournal('machine_token.refused')[0]->context['extra']['token_hint']);
        $this->assertStringNotContainsString('someone-elses-secret', $this->dumpLogs());
    }

    public function testLifecycleIsJournalled(): void
    {
        $service = $this->getMachineTokenService();
        $first = $service->issue($this->device, label: 'commissioning');
        $service->rotate($this->device, new DateInterval('P1D'));
        $service->revoke($service->findTokenBySecret($first));
        $service->revokeAll($this->device);

        $this->assertSame(['commissioning'], array_map(
            fn (LogRecord $record) => $record->context['extra']['label'],
            $this->getJournal('machine_token.issued')
        ));
        $this->assertSame(1, $this->getJournal('machine_token.rotated')[0]->context['extra']['previous_tokens']);
        $this->assertCount(2, $this->getJournal('machine_token.revoked'));

        foreach ($this->getJournal() as $record) {
            $this->assertSame((string) $this->device->getId(), $record->context['user_id']);
        }
    }

    public function testNoSecretReachesAnyLog(): void
    {
        $service = $this->getMachineTokenService();
        $valid = $service->issue($this->device);
        $revoked = $service->issue($this->device);
        $service->revoke($service->findTokenBySecret($revoked));
        $rotated = $service->rotate($this->device, new DateInterval('PT1H'));

        $this->requestWhoami($valid);
        $this->requestWhoami($revoked);
        $this->requestWhoami($rotated);
        // The router logs the request URI.
        $this->client->request('GET', self::WHOAMI_PATH . '?access_token=' . $valid);
        $this->client->request('GET', '/api/device/missing-route', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $valid]);

        $logs = $this->dumpLogs();
        $this->assertNotSame('', $logs);

        foreach ([$valid, $revoked, $rotated] as $plain) {
            $this->assertStringNotContainsString(substr($plain, strlen('fx_dev_')), $logs);
        }
    }

    /**
     * @return list<LogRecord>
     */
    private function getJournal(?string $type = null): array
    {
        return array_values(array_filter(
            $this->getLogHandler()->getRecords(),
            fn (LogRecord $record) => 'machine_security' === $record->channel
                && (null === $type || $record->message === $type)
        ));
    }

    /**
     * Every record as a JSON handler would write it, exception traces included.
     */
    private function dumpLogs(): string
    {
        $formatter = new JsonFormatter();
        $formatter->includeStacktraces();

        return implode('', array_map(
            $formatter->format(...),
            $this->getLogHandler()->getRecords()
        ));
    }

    private function getLogHandler(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.test');
    }
}
