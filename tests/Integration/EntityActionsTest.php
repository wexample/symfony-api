<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\EventSubscriber\ApiEventSubscriber;
use Wexample\SymfonyApi\Tests\Fixtures\App\Controller\ReadingApiController;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyApi\Tests\Fixtures\App\Voter\ReadingVoter;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * The entity actions — list, show, update, delete — each behind its voter.
 */
class EntityActionsTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private string $token;

    private Reading $own;

    private Reading $foreign;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $device = $this->createDevice();
        $this->token = $this->getMachineTokenService()->issue($device);

        $this->own = $this->createReading($device, 1.0, 'a');
        $this->createReading($device, 2.0, 'b');
        $this->createReading($device, 3.0, ReadingVoter::CODE_SEALED);
        $this->createReading($device, 4.0, ReadingApiController::CODE_KEPT);
        $this->foreign = $this->createReading($this->createDevice(), 99.0, 'z');
        $this->entityManager->flush();
    }

    public function testListPagesTheScopedQuery(): void
    {
        $payload = $this->call('GET', '/api/device/reading/list', ['length' => 3]);

        $this->assertSame(200, $this->getStatusCode());
        $this->assertSame(['a', 'b', ReadingApiController::CODE_KEPT], array_column(array_column($payload['data']['items'], 'entity'), 'code'));
        $this->assertSame(4, $payload['data']['pagination']['total']);
    }

    public function testAnUnscopedListIsRefusedRatherThanThinned(): void
    {
        $payload = $this->call('GET', '/api/device/reading-unscoped/list');

        $this->assertSame(403, $this->getStatusCode());
        $this->assertSame(ApiEventSubscriber::MESSAGE_ACCESS_DENIED, $payload['message']);
    }

    public function testShownIfViewableNotFoundOtherwise(): void
    {
        $this->assertSame(1, $this->call('GET', '/api/device/reading/show/' . $this->own->getId())['data']['entity']['value']);

        // Another device's reading, and an identifier naming nothing, alike.
        foreach ([(string) $this->foreign->getId(), 'not-an-id'] as $identifier) {
            $this->call('GET', '/api/device/reading/show/' . $identifier);
            $this->assertSame(404, $this->getStatusCode(), $identifier);
        }
    }

    public function testUpdatedIfEditable(): void
    {
        $payload = $this->call('PATCH', '/api/device/reading/update/' . $this->own->getId(), body: ['value' => 7.5, 'code' => 'a2']);

        $this->assertSame(200, $this->getStatusCode());
        $this->assertSame(['value' => 7.5, 'code' => 'a2'], array_intersect_key($payload['data']['entity'], ['value' => 0, 'code' => 0]));

        $sealed = $this->findReading(ReadingVoter::CODE_SEALED);
        $this->call('PATCH', '/api/device/reading/update/' . $sealed->getId(), body: ['value' => 5.0]);
        $this->assertSame(403, $this->getStatusCode());

        $this->call('PATCH', '/api/device/reading/update/' . $this->foreign->getId(), body: ['value' => 5.0]);
        $this->assertSame(404, $this->getStatusCode());

        // The body is validated as any other.
        $this->call('PATCH', '/api/device/reading/update/' . $this->own->getId(), body: ['value' => 500]);
        $this->assertSame(400, $this->getStatusCode());
    }

    public function testDeletedUnlessRefusedOrKept(): void
    {
        $id = $this->own->getId();
        $this->call('DELETE', '/api/device/reading/delete/' . $id);
        $this->assertSame(200, $this->getStatusCode());
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(Reading::class, $id));

        $this->call('DELETE', '/api/device/reading/delete/' . $this->findReading(ReadingVoter::CODE_SEALED)->getId());
        $this->assertSame(403, $this->getStatusCode());

        $payload = $this->call('DELETE', '/api/device/reading/delete/' . $this->findReading(ReadingApiController::CODE_KEPT)->getId());
        $this->assertSame(409, $this->getStatusCode());
        $this->assertSame('Kept: a kept reading stays.', $payload['message']);
    }

    private function createReading(
        Device $device,
        float $value,
        string $code
    ): Reading {
        $reading = new Reading();
        $reading->device = $device;
        $reading->value = $value;
        $reading->code = $code;
        $this->entityManager->persist($reading);

        return $reading;
    }

    private function findReading(string $code): Reading
    {
        return $this->entityManager->getRepository(Reading::class)->findOneBy(['code' => $code]);
    }

    private function call(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null
    ): ?array {
        $this->client->request(
            $method,
            $path,
            $query,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token, 'CONTENT_TYPE' => 'application/json'],
            content: null === $body ? null : json_encode($body)
        );

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
