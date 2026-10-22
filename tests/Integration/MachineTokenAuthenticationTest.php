<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\DeviceToken;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * A machine firewall on /api/device/, next to a page firewall with a session.
 */
class MachineTokenAuthenticationTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private Device $device;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->device = $this->createDevice();
    }

    public function testValidTokenAuthenticatesItsClient(): void
    {
        [$plain] = $this->createToken();

        $payload = $this->requestWhoami($plain);

        $this->assertResponseIsSuccessful();
        $this->assertSame((string) $this->device->getId(), $payload['data']['identifier']);
        $this->assertSame(['ROLE_MACHINE'], $payload['data']['roles']);
    }

    public function testEveryBadTokenGetsTheSameRefusal(): void
    {
        [$revoked, $revokedToken] = $this->createToken();
        $revokedToken->revoke();
        [$expired] = $this->createToken(new DateTimeImmutable('-1 second'));
        [$valid] = $this->createToken();
        $this->entityManager->flush();

        $responses = [];
        foreach (['fx_dev_unknown', $revoked, $expired, substr($valid, 0, -1)] as $token) {
            $this->requestWhoami($token);
            $responses[] = $this->describeRefusal($this->client->getResponse());
        }

        $this->assertSame([
            'code' => Response::HTTP_UNAUTHORIZED,
            'challenge' => 'Bearer error="invalid_token"',
            'body' => [
                'type' => 'error',
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Invalid credentials.',
                'data' => [],
            ],
        ], $responses[0]);
        $this->assertSame(array_fill(0, 4, $responses[0]), $responses);
    }

    public function testMissingTokenIsChallenged(): void
    {
        $this->client->request('GET', self::WHOAMI_PATH);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('Authentication required.', json_decode($response->getContent(), true)['message']);
    }

    public function testTokenInTheUrlIsRefused(): void
    {
        [$plain] = $this->createToken();

        $this->client->request('GET', self::WHOAMI_PATH . '?access_token=' . $plain);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/api/device/rotate', ['access_token' => $plain]);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testSessionCookieDoesNotOpenTheMachineApi(): void
    {
        $this->client->loginUser(new InMemoryUser('jane', 'secret', ['ROLE_USER']), 'main');

        $this->client->request('GET', '/page');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', self::WHOAMI_PATH);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testBearerTokenDoesNotOpenAPage(): void
    {
        [$plain] = $this->createToken();

        $this->client->request('GET', '/page', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $plain]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testMachineApiNeverSetsACookie(): void
    {
        [$plain] = $this->createToken();
        // A page session open in the same browser must not leak into the API.
        $this->client->loginUser(new InMemoryUser('jane', 'secret', ['ROLE_USER']), 'main');

        foreach ([$plain, 'fx_dev_unknown', null] as $token) {
            $this->requestWhoami($token);

            $this->assertSame([], $this->client->getResponse()->headers->getCookies());
        }
    }

    public function testClientCarryingAHumanRoleIsRefused(): void
    {
        [$plain] = $this->createToken();
        $this->device->setExtraRoles(['ROLE_ADMIN']);
        $this->entityManager->flush();

        $this->requestWhoami($plain);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testDatabaseHoldsNoPlainToken(): void
    {
        [$plain] = $this->createToken();

        $this->assertStringStartsWith('fx_dev_', $plain);

        /** @var Connection $connection */
        $connection = $this->entityManager->getConnection();
        $dump = var_export($connection->fetchAllAssociative('SELECT * FROM device_token'), true);

        $this->assertStringNotContainsString(substr($plain, strlen('fx_dev_')), $dump);
        $this->assertStringContainsString(hash('sha256', $plain), $dump);
    }

    public function testLastUseIsRecordedOncePerInterval(): void
    {
        [$plain, $token] = $this->createToken();
        $this->assertNull($token->getDateLastUsed());

        $this->requestWhoami($plain);
        $firstUse = $token->getDateLastUsed();
        $this->assertNotNull($firstUse);

        $this->requestWhoami($plain);
        $this->assertSame($firstUse, $token->getDateLastUsed());
    }

    /**
     * @return array{string, DeviceToken}
     */
    private function createToken(?DateTimeImmutable $dateExpiration = null): array
    {
        $token = (new DeviceToken())
            ->setClient($this->device)
            ->setDateExpiration($dateExpiration);
        $plain = $this->getMachineTokenService()->generateSecret($token);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return [$plain, $token];
    }

    private function describeRefusal(Response $response): array
    {
        return [
            'code' => $response->getStatusCode(),
            'challenge' => $response->headers->get('WWW-Authenticate'),
            'body' => json_decode($response->getContent(), true),
        ];
    }
}
