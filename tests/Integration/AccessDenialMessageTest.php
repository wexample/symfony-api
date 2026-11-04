<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * A refusal says that much and no more: Symfony appends the voters' reasons
 * to the exception's message — a role, a grant, the record it was refused on.
 */
class AccessDenialMessageTest extends WebTestCase
{
    use MachineTokenTestTrait;

    public function testARefusalNamesNothing(): void
    {
        $this->setUpMachineTokenApp();
        $token = $this->getMachineTokenService()->issue($this->createDevice());

        $this->client->request('GET', '/api/device/denied', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $this->assertSame(403, $this->getStatusCode());
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Access Denied.', $payload['message']);
        $this->assertStringNotContainsString('ROLE_ADMIN', $this->client->getResponse()->getContent());
    }
}
