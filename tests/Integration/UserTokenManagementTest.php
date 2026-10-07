<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Wexample\SymfonyApi\Tests\Traits\UserTokenTestTrait;

/**
 * A person managing their own tokens from a signed-in session.
 */
class UserTokenManagementTest extends WebTestCase
{
    use UserTokenTestTrait;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
    }

    public function testIssueListRevoke(): void
    {
        $this->client->loginUser($this->createPerson(), 'main');

        $issued = $this->requestTokens('POST', body: ['label' => 'import script', 'expiresAt' => '2099-01-01']);
        $this->assertSame(201, $this->getStatusCode());
        $secret = $issued['data']['secret'];
        $this->assertStringStartsWith('fx_usr_', $secret);
        $this->assertSame('import script', $issued['data']['token']['label']);
        $this->assertStringStartsWith('2099-01-01', $issued['data']['token']['dateExpiration']);
        $this->assertTrue($issued['data']['token']['usable']);

        // The secret works, and is never shown again.
        $this->requestScript('GET', 'whoami', $secret);
        $this->assertSame(200, $this->getStatusCode());

        $list = $this->requestTokens('GET');
        $this->assertSame(200, $this->getStatusCode());
        $this->assertCount(1, $list['data']['items']);
        $this->assertSame($issued['data']['token']['hint'], $list['data']['items'][0]['hint']);
        $this->assertNotNull($list['data']['items'][0]['dateLastUsed']);
        $this->assertStringNotContainsString($secret, $this->client->getResponse()->getContent());
        $this->assertArrayNotHasKey('tokenHash', $list['data']['items'][0]);

        $revoked = $this->requestTokens('DELETE', '/' . $issued['data']['token']['id']);
        $this->assertSame(200, $this->getStatusCode());
        $this->assertFalse($revoked['data']['usable']);

        $this->requestScript('GET', 'whoami', $secret);
        $this->assertSame(401, $this->getStatusCode());
    }

    public function testIssueWithoutBodyAndRefusals(): void
    {
        $this->client->loginUser($this->createPerson(), 'main');

        $this->requestTokens('POST');
        $this->assertSame(201, $this->getStatusCode());

        $this->requestTokens('POST', body: ['expiresAt' => '2001-01-01']);
        $this->assertSame(400, $this->getStatusCode());

        $this->requestTokens('POST', body: ['expiresAt' => 'tomorrow']);
        $this->assertSame(400, $this->getStatusCode());
    }

    public function testSomeoneElsesTokenIsNotFound(): void
    {
        $other = $this->createPerson('grace@example.com');
        $this->getUserTokenService()->issue($other);
        $otherToken = $this->getUserTokenService()->findTokens($other)[0];

        $this->client->loginUser($this->createPerson(), 'main');

        $this->assertCount(0, $this->requestTokens('GET')['data']['items']);
        $this->requestTokens('DELETE', '/' . $otherToken->getId());
        $this->assertSame(404, $this->getStatusCode());
        $this->assertTrue($otherToken->isUsable());
    }

    public function testATokenCannotManageTokens(): void
    {
        $person = $this->createPerson();
        $secret = $this->getUserTokenService()->issue($person);

        // Not on the page firewall: no session, nothing authenticated.
        $this->requestTokens('POST', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $secret]);
        $this->assertContains($this->getStatusCode(), [401, 403]);
        $this->assertCount(1, $this->getUserTokenService()->findTokens($person));
    }

    public function testAnAccountThatCannotHoldTokensIsRefused(): void
    {
        $this->client->loginUser(new InMemoryUser('jane', 'secret', ['ROLE_USER']), 'main');

        $this->requestTokens('GET');
        $this->assertSame(403, $this->getStatusCode());
    }

    public function testAnonymousIsRefused(): void
    {
        $this->requestTokens('GET');
        $this->assertNotSame(200, $this->getStatusCode());
    }
}
