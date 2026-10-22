<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyApi\Helper\IdempotencyHelper;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\ReadingDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * A device sending readings in batches, again after a dropped connection.
 */
class BatchReceiptTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private const string READINGS_PATH = '/api/device/readings';

    private Device $device;

    private string $token;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $this->device = $this->createDevice();
        $this->token = $this->getMachineTokenService()->issue($this->device);
    }

    public function testValidItemsAreStoredAndEachItemIsReported(): void
    {
        $payload = $this->sendBatch([
            ['key' => 'k1', 'data' => ['value' => 12.5]],
            ['key' => 'k2', 'data' => ['value' => 140]],
            ['key' => 'k3', 'data' => []],
            ['key' => '', 'data' => ['value' => 1]],
            ['data' => ['value' => 1]],
            'not an item',
            ['key' => 'k4', 'data' => ['value' => 3], 'extra' => true],
            ['key' => 'k5', 'data' => ['value' => 'high']],
            ['key' => 'k6', 'data' => ['value' => 42]],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['accepted', 'rejected', 'rejected', 'rejected', 'rejected', 'rejected', 'rejected', 'rejected', 'accepted'],
            array_column($payload['data']['items'], 'outcome')
        );
        $this->assertSame(range(0, 8), array_column($payload['data']['items'], 'index'));
        $this->assertSame(['accepted' => 2, 'duplicate' => 0, 'rejected' => 7, 'conflict' => 0, 'error' => 0], $payload['data']['summary']);

        $outOfRange = $payload['data']['items'][1]['errors'];
        $this->assertSame('validation_collection', $outOfRange['kind']);
        $this->assertSame(['value'], array_keys($outOfRange['summary']['fields']));

        $this->assertSame([12.5, 42.0], $this->getStoredValues());
        $this->assertSame(
            ['id' => (string) $this->findReading(12.5)->getId()],
            $payload['data']['items'][0]['result']
        );
    }

    public function testReplayedBatchStoresNothingMore(): void
    {
        $batch = [
            ['key' => 'k1', 'data' => ['value' => 1]],
            ['key' => 'k2', 'data' => ['value' => 2, 'code' => null]],
            ['key' => 'k3', 'data' => ['value' => 500]],
        ];

        $first = $this->sendBatch($batch);
        // Same items, keys of the data in another order.
        $batch[1]['data'] = ['code' => null, 'value' => 2];
        $second = $this->sendBatch($batch);

        $this->assertSame(['accepted', 'accepted', 'rejected'], array_column($first['data']['items'], 'outcome'));
        $this->assertSame(['duplicate', 'duplicate', 'rejected'], array_column($second['data']['items'], 'outcome'));
        // The original result comes back with a duplicate.
        $this->assertSame($first['data']['items'][0]['result'], $second['data']['items'][0]['result']);
        $this->assertSame([1.0, 2.0], $this->getStoredValues());
    }

    public function testKeyAlreadyClaimedByAConcurrentRequestStoresNothing(): void
    {
        // What a concurrent send of the same item leaves once it committed:
        // the insert of the key fails on the unique constraint, nothing is read before.
        $this->insertRecord('Device:' . $this->device->getUserIdentifier(), 'k1', ['value' => 1]);

        $payload = $this->sendBatch([['key' => 'k1', 'data' => ['value' => 1]]]);

        $this->assertSame('duplicate', $payload['data']['items'][0]['outcome']);
        $this->assertSame([], $this->getStoredValues());
    }

    public function testSameKeyWithAnotherPayloadIsAConflict(): void
    {
        $this->sendBatch([['key' => 'k1', 'data' => ['value' => 1]]]);
        $payload = $this->sendBatch([['key' => 'k1', 'data' => ['value' => 2]]]);

        $item = $payload['data']['items'][0];
        $this->assertSame('conflict', $item['outcome']);
        $this->assertSame('KEY_REUSED', $item['errors']['issues'][0]['code']);
        $this->assertSame([1.0], $this->getStoredValues());
    }

    public function testSameKeyFromTwoClientsIsStoredTwice(): void
    {
        $otherToken = $this->getMachineTokenService()->issue($this->createDevice());

        $this->assertSame('accepted', $this->sendBatch([['key' => 'k1', 'data' => ['value' => 1]]])['data']['items'][0]['outcome']);
        $this->assertSame('accepted', $this->sendBatch([['key' => 'k1', 'data' => ['value' => 1]]], $otherToken)['data']['items'][0]['outcome']);

        $this->assertSame([1.0, 1.0], $this->getStoredValues());
    }

    public function testAFailingItemLeavesTheOthersStored(): void
    {
        $this->sendBatch([['key' => 'k0', 'data' => ['value' => 9, 'code' => 'taken']]]);

        $payload = $this->sendBatch([
            ['key' => 'k1', 'data' => ['value' => 1]],
            // The processor throws after persisting: nothing of it may be flushed later.
            ['key' => 'k2', 'data' => ['value' => ReadingDto::VALUE_THAT_FAILS]],
            ['key' => 'k3', 'data' => ['value' => 3]],
            // The flush fails on a unique column: the entity manager closes.
            ['key' => 'k4', 'data' => ['value' => 4, 'code' => 'taken']],
            ['key' => 'k5', 'data' => ['value' => 5]],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['accepted', 'error', 'accepted', 'error', 'accepted'], array_column($payload['data']['items'], 'outcome'));
        $this->assertArrayNotHasKey('errors', $payload['data']['items'][1]);
        $this->assertSame([1.0, 3.0, 5.0, 9.0], $this->getStoredValues());

        // A failed item left no key: sent again, it is processed again.
        $retry = $this->sendBatch([['key' => 'k4', 'data' => ['value' => 4, 'code' => 'free']]]);
        $this->assertSame('accepted', $retry['data']['items'][0]['outcome']);
    }

    public function testProcessorRefusalIsARejectionThatConsumesNoKey(): void
    {
        $batch = [
            ['key' => 'k1', 'data' => ['value' => 1]],
            ['key' => 'k2', 'data' => ['value' => ReadingDto::VALUE_REFUSED]],
            ['key' => 'k3', 'data' => ['value' => 3]],
        ];

        $first = $this->sendBatch($batch);

        $this->assertSame(['accepted', 'rejected', 'accepted'], array_column($first['data']['items'], 'outcome'));
        $refused = $first['data']['items'][1]['errors'];
        $this->assertSame('DEVICE_MISMATCH', $refused['issues'][0]['code']);
        $this->assertSame('The reading does not belong to this device.', $refused['issues'][0]['message']);
        // The refused item persisted before throwing: none of it remains.
        $this->assertSame([1.0, 3.0], $this->getStoredValues());

        $replay = $this->sendBatch($batch);
        $this->assertSame(['duplicate', 'rejected', 'duplicate'], array_column($replay['data']['items'], 'outcome'));
        $this->assertSame('DEVICE_MISMATCH', $replay['data']['items'][1]['errors']['issues'][0]['code']);

        $journal = array_values(array_filter(
            $this->getLogHandler()->getRecords(),
            fn (LogRecord $record) => 'api_batch' === $record->channel
        ));
        $this->assertSame(['DEVICE_MISMATCH' => 1], $journal[0]->context['rejection_codes']);
    }

    public function testBatchOverTheLimitIsRefusedWhole(): void
    {
        $items = array_map(
            fn (int $i) => ['key' => 'k' . $i, 'data' => ['value' => $i]],
            range(1, 16)
        );

        $payload = $this->sendBatch($items);

        $this->assertSame(422, $this->getStatusCode());
        $this->assertSame('error', $payload['type']);
        $this->assertStringContainsString('at most 15', $payload['message']);
        $this->assertSame([], $this->getStoredValues());
    }

    public function testUnreadableBatchIsRefusedWhole(): void
    {
        foreach (['not json', '{"items": {"a": 1}}', '[]'] as $body) {
            $this->client->request('POST', self::READINGS_PATH, server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ], content: $body);

            $this->assertSame(400, $this->getStatusCode(), $body);
        }
    }

    public function testBatchIsJournalledOnce(): void
    {
        $this->sendBatch([
            ['key' => 'k1', 'data' => ['value' => 1]],
            ['key' => 'k2', 'data' => ['value' => 500]],
        ], server: ['HTTP_X_REQUEST_ID' => 'req-batch']);

        $records = array_values(array_filter(
            $this->getLogHandler()->getRecords(),
            fn (LogRecord $record) => 'api_batch' === $record->channel
        ));

        $this->assertCount(1, $records);
        $this->assertSame('Device:' . $this->device->getUserIdentifier(), $records[0]->context['scope']);
        $this->assertSame(ReadingDto::class, $records[0]->context['item_class']);
        $this->assertSame(1, $records[0]->context['summary']['accepted']);
        $this->assertSame(1, $records[0]->context['summary']['rejected']);
        $this->assertSame('req-batch', $records[0]->context['request_id']);
    }

    public function testPurgeDeletesKeysOlderThanTheRetention(): void
    {
        $scope = 'Device:' . $this->device->getUserIdentifier();
        $this->insertRecord($scope, 'old', ['value' => 1], '-31 days');
        $this->insertRecord($scope, 'recent', ['value' => 1], '-29 days');

        $tester = new CommandTester((new Application(self::$kernel))->find('api:batch:purge-keys'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $keys = $this->entityManager->getConnection()->fetchFirstColumn('SELECT idempotency_key FROM idempotency_record');
        $this->assertSame(['recent'], $keys);
    }

    private function sendBatch(array $items, ?string $token = null, array $server = []): ?array
    {
        $this->client->request('POST', self::READINGS_PATH, server: $server + [
            'HTTP_AUTHORIZATION' => 'Bearer ' . ($token ?? $this->token),
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['items' => $items]));

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * @return list<float>
     */
    private function getStoredValues(): array
    {
        $values = array_map('floatval', $this->entityManager->getConnection()->fetchFirstColumn('SELECT value FROM reading'));
        sort($values);

        return $values;
    }

    private function findReading(float $value): Reading
    {
        return self::getContainer()->get('doctrine')->getManager()->getRepository(Reading::class)->findOneBy(['value' => $value]);
    }

    private function insertRecord(string $scope, string $key, array $data, string $age = 'now'): void
    {
        $this->entityManager->getConnection()->insert('idempotency_record', [
            'id' => Uuid::v7(),
            'scope' => $scope,
            'idempotency_key' => $key,
            'payload_hash' => IdempotencyHelper::hashPayload($data),
            'date_created' => new \DateTime($age),
        ], ['id' => 'uuid', 'date_created' => 'datetime']);
    }

    private function getLogHandler(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.test');
    }
}
