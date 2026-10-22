<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Traits\UserTokenTestTrait;

/**
 * A person's script authenticated by a token of theirs, on a firewall of its
 * own, beside the machine one.
 */
class UserTokenAuthenticationTest extends WebTestCase
{
    use UserTokenTestTrait;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
    }

    public function testTokenAuthenticatesAsThePersonWithTheirRoles(): void
    {
        $person = $this->createPerson(roles: ['ROLE_USER', 'ROLE_IMPORTER']);
        $secret = $this->getUserTokenService()->issue($person, label: 'import script');

        $this->assertStringStartsWith('fx_usr_', $secret);

        $body = $this->requestScript('GET', 'whoami', $secret);
        $this->assertSame(200, $this->getStatusCode());
        $this->assertSame('ada@example.com', $body['data']['identifier']);
        $this->assertSame(['ROLE_USER', 'ROLE_IMPORTER'], $body['data']['roles']);

        // The person's roles open what they open.
        $this->requestScript('POST', 'import', $secret);
        $this->assertSame(200, $this->getStatusCode());
        $this->assertNull($this->client->getResponse()->headers->get('Set-Cookie'));
    }

    public function testRolesTheyLackStayClosed(): void
    {
        $secret = $this->getUserTokenService()->issue($this->createPerson());

        $this->requestScript('POST', 'import', $secret);
        $this->assertSame(403, $this->getStatusCode());
    }

    public function testRevokedExpiredUnknownAndMissingAreTheSame401(): void
    {
        $person = $this->createPerson();
        $service = $this->getUserTokenService();

        $revoked = $service->issue($person);
        $service->revoke($service->findTokenBySecret($revoked));
        $expired = $service->issue($person, new DateTimeImmutable('-1 minute'));

        foreach ([$revoked, $expired, 'fx_usr_unknown'] as $token) {
            $body = $this->requestScript('GET', 'whoami', $token);
            $this->assertSame(401, $this->getStatusCode());
            $this->assertSame('Invalid credentials.', $body['message']);
        }

        $body = $this->requestScript('GET', 'whoami', null);
        $this->assertSame(401, $this->getStatusCode());
        $this->assertSame('Authentication required.', $body['message']);
    }

    public function testKindsDoNotCross(): void
    {
        $device = $this->createDevice();
        $machineSecret = $this->getMachineTokenService()->issue($device);
        $userSecret = $this->getUserTokenService()->issue($this->createPerson());

        // Each firewall knows its own kind only.
        $this->requestScript('GET', 'whoami', $machineSecret);
        $this->assertSame(401, $this->getStatusCode());
        $this->requestWhoami($userSecret);
        $this->assertSame(401, $this->getStatusCode());

        // A machine client never holds a user token: it would escape the
        // machine roles.
        $this->expectException(InvalidArgumentException::class);
        $this->getUserTokenService()->issue($device);
    }

    public function testDisabledPersonIsRefusedAndJournalled(): void
    {
        $person = $this->createPerson();
        $secret = $this->getUserTokenService()->issue($person);
        $person->setEnabled(false);
        $this->entityManager->flush();

        $this->requestScript('GET', 'whoami', $secret);
        $this->assertSame(401, $this->getStatusCode());

        $record = $this->findJournal('user_token.refused');
        $this->assertSame('client_disabled', $record->context['cause']);
        $this->assertSame('scripts', $record->context['firewall']);
    }

    public function testLifecycleIsJournalledOnItsOwnChannelAndTokensAreMasked(): void
    {
        $person = $this->createPerson();
        $secret = $this->getUserTokenService()->issue($person, label: 'laptop');
        $this->requestScript('GET', 'whoami', $secret);

        $issued = $this->findJournal('user_token.issued');
        $this->assertSame('user_token_security', $issued->channel);
        $this->assertSame((string) $person->getId(), $issued->context['user_id']);

        // A secret quoted by some log line is replaced by its hint.
        self::getContainer()->get('logger')->warning('Script called with Bearer ' . $secret);
        $hint = $this->getUserTokenService()->findTokenBySecret($secret)->getHint();
        $this->assertTrue($this->getLogs()->hasWarningThatContains('Bearer ' . $hint));

        foreach ($this->getLogs()->getRecords() as $record) {
            $this->assertStringNotContainsString($secret, json_encode([$record->message, $record->context]));
        }
    }

    public function testLastUseIsWritten(): void
    {
        $secret = $this->getUserTokenService()->issue($this->createPerson());
        $this->requestScript('GET', 'whoami', $secret);

        $this->entityManager->clear();
        $this->assertNotNull($this->getUserTokenService()->findTokenBySecret($secret)->getDateLastUsed());
    }

    public function testFailuresPerAddressAreThrottledBeforeTheDatabase(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->requestScript('GET', 'whoami', 'fx_usr_unknown' . $i);
        }

        $this->requestScript('GET', 'whoami', 'fx_usr_unknown');
        $this->assertSame(429, $this->getStatusCode());
        $this->assertSame('ip', $this->findJournal('user_token.throttled')->context['cause']);

        // The machine firewall counts its own failures apart.
        $this->requestWhoami('fx_dev_unknown');
        $this->assertSame(401, $this->getStatusCode());
    }

    private function getLogs(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.test');
    }

    private function findJournal(string $type): LogRecord
    {
        foreach (array_reverse($this->getLogs()->getRecords()) as $record) {
            if ('user_token_security' === $record->channel && $record->message === $type) {
                return $record;
            }
        }

        $this->fail('No journal entry ' . $type . '.');
    }
}
