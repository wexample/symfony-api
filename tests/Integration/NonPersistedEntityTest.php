<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\DeviceFile;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * A list computed on demand goes through the same chain as a stored one:
 * an entity with no table, its normalizer, the same envelope, the same
 * query options.
 */
class NonPersistedEntityTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private string $token;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->token = $this->getMachineTokenService()->issue($this->createDevice());
    }

    public function testDoctrineIgnoresTheEntityWithoutTable(): void
    {
        $this->assertTrue($this->entityManager->getMetadataFactory()->isTransient(DeviceFile::class));
    }

    public function testListIsSortedAndPagedLikeAStoredOne(): void
    {
        $payload = $this->list([]);
        $this->assertSame(['alarms.json', 'boot.log', 'config.yml', 'firmware.bin', 'readings.csv'], $this->names($payload));
        $this->assertSame(['page' => 0, 'length' => 10, 'total' => 5, 'pagesCount' => 1, 'hasMore' => false], $payload['data']['pagination']);

        // Several terms; equal sizes fall back on the next one.
        $this->assertSame(['firmware.bin', 'readings.csv', 'alarms.json', 'boot.log', 'config.yml'], $this->names($this->list(['sort' => '-size,name'])));
        // Nulls first ascending, last descending.
        $this->assertSame('config.yml', $this->names($this->list(['sort' => 'dateModified']))[0]);
        $this->assertSame('config.yml', $this->names($this->list(['sort' => '-dateModified']))[4]);

        $page = $this->list(['sort' => 'name', 'length' => 2, 'page' => -1]);
        $this->assertSame(['readings.csv'], $this->names($page));
        $this->assertSame(['page' => 2, 'length' => 2, 'total' => 5, 'pagesCount' => 3, 'hasMore' => false], $page['data']['pagination']);

        $this->list(['sort' => 'secret']);
        $this->assertSame(400, $this->getStatusCode());
    }

    public function testItemsCarryTheEntityEnvelopeAndKeepTheirId(): void
    {
        $first = $this->list(['sort' => 'name'])['data']['items'];
        $second = $this->list(['sort' => '-name'])['data']['items'];

        $this->assertSame('deviceFile', $first[0]['type']);
        $this->assertSame(['id', 'name', 'size', 'dateModified'], array_keys($first[0]['entity']));
        // Computed again on each request, the same file keeps the same id.
        $this->assertSame($first[0]['entity']['id'], $second[4]['entity']['id']);
    }

    public function testSortListReadsArraysAndObjectsAlike(): void
    {
        $option = new SortQueryOption(allowed: ['size', 'meta.rank'], tieBreaker: null);
        $rows = [['size' => 2, 'meta' => ['rank' => 1]], ['size' => 1, 'meta' => ['rank' => 3]], ['size' => 3, 'meta' => ['rank' => 2]]];

        $this->assertSame([1, 2, 3], array_column($option->sortList($rows, 'size'), 'size'));
        $this->assertSame([1, 3, 2], array_column($option->sortList($rows, '-meta.rank'), 'size'));
    }

    private function list(array $query): ?array
    {
        $this->client->request('GET', '/api/device/files', $query, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function names(array $payload): array
    {
        return array_map(fn (array $item) => $item['entity']['name'], $payload['data']['items']);
    }
}
