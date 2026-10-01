<?php

namespace Wexample\SymfonyApi\Tests\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Wexample\SymfonyApi\Service\MachineTokenService;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;

/**
 * A machine firewall on /api/device/, next to a page firewall with a session,
 * on an in-memory database.
 */
trait MachineTokenTestTrait
{
    protected const string WHOAMI_PATH = '/api/device/whoami';

    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUpMachineTokenApp(): void
    {
        $this->client = static::createClient();
        // The database lives in memory: a rebooted kernel would start on an
        // empty one at every request.
        $this->client->disableReboot();

        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema(
            $this->entityManager->getMetadataFactory()->getAllMetadata()
        );
    }

    protected function createDevice(): Device
    {
        $device = new Device();
        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $device;
    }

    protected function getMachineTokenService(): MachineTokenService
    {
        return self::getContainer()->get(MachineTokenService::class);
    }

    protected function requestWhoami(?string $token, array $server = []): ?array
    {
        $this->client->request(
            'GET',
            self::WHOAMI_PATH,
            server: $server + (null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer ' . $token])
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    protected function getStatusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }
}
