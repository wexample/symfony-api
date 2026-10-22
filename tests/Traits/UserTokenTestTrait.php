<?php

namespace Wexample\SymfonyApi\Tests\Traits;

use Wexample\SymfonyApi\Service\UserTokenService;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Person;

/**
 * A user token firewall on /api/script/, beside the machine one, and the
 * token management routes on the page firewall.
 */
trait UserTokenTestTrait
{
    use MachineTokenTestTrait;

    protected function createPerson(string $email = 'ada@example.com', array $roles = ['ROLE_USER']): Person
    {
        $person = new Person($email, $roles);
        $this->entityManager->persist($person);
        $this->entityManager->flush();

        return $person;
    }

    protected function getUserTokenService(): UserTokenService
    {
        return self::getContainer()->get(UserTokenService::class);
    }

    protected function requestScript(string $method, string $path, ?string $token): ?array
    {
        $this->client->request(
            $method,
            '/api/script/' . $path,
            server: null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    protected function requestTokens(string $method, string $path = '', ?array $body = null, array $server = []): ?array
    {
        $this->client->request(
            $method,
            '/api/user-tokens' . $path,
            server: $server + ['CONTENT_TYPE' => 'application/json'],
            content: null === $body ? null : json_encode($body)
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
