<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Wexample\SymfonyApi\Tests\Traits\UserTokenTestTrait;

/**
 * A user token limited to some of its holder's roles, the longest a token may
 * live (`max_lifetime: P90D` in the fixture), and the console commands.
 */
class UserTokenScopeAndLifetimeTest extends WebTestCase
{
    use UserTokenTestTrait;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
    }

    public function testScopedTokenOpensOnlyItsScopes(): void
    {
        $person = $this->createPerson(roles: ['ROLE_USER', 'ROLE_IMPORTER']);
        $service = $this->getUserTokenService();

        $readOnly = $service->issue($person, scopes: ['ROLE_USER']);
        $full = $service->issue($person);

        $this->requestScript('GET', 'whoami', $readOnly);
        $this->assertSame(200, $this->getStatusCode());
        $this->requestScript('POST', 'import', $readOnly);
        $this->assertSame(403, $this->getStatusCode());

        $this->requestScript('POST', 'import', $full);
        $this->assertSame(200, $this->getStatusCode());
    }

    public function testScopesReachRolesThroughTheHierarchyOnly(): void
    {
        // ROLE_ADMIN implies ROLE_USER in symfony-testing's kernel, which
        // sets the hierarchy of every fixture.
        $admin = $this->createPerson(roles: ['ROLE_ADMIN']);
        $user = $this->getUserTokenService()->issue($admin, scopes: ['ROLE_USER']);

        $this->requestScript('GET', 'whoami', $user);
        $this->assertSame(200, $this->getStatusCode());

    }

    public function testScopeOutsideTheHoldersRolesIsRefused(): void
    {
        // A token never opens more than its holder.
        $this->expectException(InvalidArgumentException::class);
        $this->getUserTokenService()->issue($this->createPerson(), scopes: ['ROLE_IMPORTER']);
    }

    public function testRoleLostSinceIssueIsDropped(): void
    {
        $person = $this->createPerson(roles: ['ROLE_USER', 'ROLE_IMPORTER']);
        $secret = $this->getUserTokenService()->issue($person, scopes: ['ROLE_USER', 'ROLE_IMPORTER']);

        $lessened = new \ReflectionProperty($person, 'roles');
        $lessened->setValue($person, ['ROLE_USER']);
        $this->entityManager->flush();

        $this->requestScript('POST', 'import', $secret);
        $this->assertSame(403, $this->getStatusCode());
    }

    public function testScopesFromTheSession(): void
    {
        $this->client->loginUser($this->createPerson(roles: ['ROLE_USER', 'ROLE_IMPORTER']), 'main');

        $issued = $this->requestTokens('POST', body: ['scopes' => ['ROLE_USER']]);
        $this->assertSame(201, $this->getStatusCode());
        $this->assertSame(['ROLE_USER'], $issued['data']['token']['scopes']);

        $refused = $this->requestTokens('POST', body: ['scopes' => ['ROLE_ADMIN']]);
        $this->assertSame(400, $this->getStatusCode());
        $this->assertStringContainsString('ROLE_ADMIN', $refused['message']);
    }

    public function testMaximumLifetime(): void
    {
        $person = $this->createPerson();
        $service = $this->getUserTokenService();

        // Asked without an expiration: the latest one allowed.
        $service->issue($person);
        $expiration = $service->findTokens($person)[0]->getDateExpiration();
        $this->assertNotNull($expiration);
        $this->assertEqualsWithDelta((new DateTimeImmutable('+90 days'))->getTimestamp(), $expiration->getTimestamp(), 60);

        $this->client->loginUser($person, 'main');
        $this->requestTokens('POST', body: ['expiresAt' => (new DateTimeImmutable('+1 year'))->format('Y-m-d')]);
        $this->assertSame(400, $this->getStatusCode());

        $this->expectException(InvalidArgumentException::class);
        $service->issue($person, new DateTimeImmutable('+91 days'));
    }

    public function testConsoleCommands(): void
    {
        $person = $this->createPerson(roles: ['ROLE_USER', 'ROLE_IMPORTER']);
        $id = (string) $person->getId();

        $issue = $this->runCommand('api:user-token:issue', ['client' => $id, '--label' => 'cli', '--scope' => ['ROLE_USER']]);
        $this->assertSame(0, $issue->getStatusCode());
        $this->assertMatchesRegularExpression('/^fx_usr_[0-9A-Za-z]{43}$/m', $issue->getDisplay());

        $list = $this->runCommand('api:user-token:list', ['client' => $id]);
        $this->assertStringContainsString('cli', $list->getDisplay());
        $this->assertStringContainsString('ROLE_USER', $list->getDisplay());

        $this->runCommand('api:user-token:revoke', ['reference' => $id, '--all' => true]);
        $this->entityManager->clear();
        $this->assertFalse($this->getUserTokenService()->findTokens($this->entityManager->find($person::class, $person->getId()))[0]->isUsable());
    }

    private function runCommand(string $name, array $input): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }
}
