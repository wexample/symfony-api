<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyApi\Tests\Traits\MachineTokenTestTrait;

/**
 * A paginated list sorted by `?sort=`, within the names its route allows.
 */
class ListSortingTest extends WebTestCase
{
    use MachineTokenTestTrait;

    private string $token;

    protected function setUp(): void
    {
        $this->setUpMachineTokenApp();
        $device = $this->createDevice();
        $this->token = $this->getMachineTokenService()->issue($device);

        foreach ([[3.0, 'b'], [1.0, 'c'], [2.0, 'a'], [2.0, 'd'], [2.0, 'e']] as [$value, $code]) {
            $reading = new Reading();
            $reading->device = $device;
            $reading->value = $value;
            $reading->code = $code;
            $this->entityManager->persist($reading);
        }

        // Another device's readings never show.
        $other = $this->createDevice();
        $foreign = new Reading();
        $foreign->device = $other;
        $foreign->value = 99.0;
        $this->entityManager->persist($foreign);
        $this->entityManager->flush();
    }

    public function testDefaultOrderApplies(): void
    {
        // JSON writes 3.0 as 3.
        $this->assertEquals([3, 2, 2, 2, 1], array_column($this->list([])['data']['items'], 'value'));
    }

    public function testAscendingDescendingAndSeveralTerms(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $this->codes(['sort' => 'code']));
        $this->assertSame(['e', 'd', 'c', 'b', 'a'], $this->codes(['sort' => '-code']));
        $this->assertSame(['c', 'e', 'd', 'a', 'b'], $this->codes(['sort' => 'value,-code']));
        // A name mapped to an expression of a joined entity.
        $this->assertCount(5, $this->codes(['sort' => 'device,code']));
    }

    public function testPagesNeverOverlapOnEqualValues(): void
    {
        $seen = [];
        for ($page = 0; $page < 5; $page++) {
            array_push($seen, ...$this->codes(['sort' => 'value', 'length' => 1, 'page' => $page]));
        }

        sort($seen);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $seen);
    }

    /**
     * SQLite returns equal rows in a stable order anyway: the tie-breaker is
     * checked on the query itself, as PostgreSQL would need it.
     */
    public function testOrderEndsOnTheTieBreakerOnce(): void
    {
        $option = new SortQueryOption(allowed: ['value', 'id', 'device' => 'device.id'], default: '-value');
        $queryBuilder = fn () => $this->entityManager->createQueryBuilder()
            ->select('reading')->from(Reading::class, 'reading')->join('reading.device', 'device')
            ->orderBy('reading.code', 'DESC');

        $this->assertStringEndsWith('ORDER BY reading.value DESC, reading.id ASC', $option->apply($queryBuilder(), null)->getDQL());
        $this->assertStringEndsWith('ORDER BY device.id ASC, reading.value DESC, reading.id ASC', $option->apply($queryBuilder(), 'device,-value')->getDQL());
        $this->assertStringEndsWith('ORDER BY reading.id DESC', $option->apply($queryBuilder(), '-id')->getDQL());
        // A name asked twice counts once.
        $this->assertStringEndsWith('ORDER BY reading.value ASC, reading.id ASC', $option->apply($queryBuilder(), 'value,-value')->getDQL());
    }

    public function testNameOutsideTheWhitelistIsRefused(): void
    {
        foreach (['id', 'value;DROP TABLE reading', 'reading.value', '--value', 'value,', 'code,secret'] as $sort) {
            $payload = $this->list(['sort' => $sort]);

            $this->assertSame(400, $this->getStatusCode(), $sort);
            $this->assertSame('error', $payload['type']);
            $this->assertStringContainsString('Sort by value, code, device', json_encode($payload['data']), $sort);
        }

        $this->client->request('GET', '/api/device/readings?sort[]=value', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
        $this->assertSame(400, $this->getStatusCode());
    }

    public function testPaginationTellsTheTotal(): void
    {
        $pagination = $this->list(['sort' => 'code', 'length' => 2, 'page' => 1])['data']['pagination'];

        $this->assertSame(['page' => 1, 'length' => 2, 'total' => 5, 'pagesCount' => 3, 'hasMore' => true], $pagination);
    }

    public function testDeclarationMistakesFailAtOnce(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SortQueryOption(allowed: ['value'], default: 'code');
    }

    public function testDocumentedWithItsAllowedNames(): void
    {
        $document = json_decode(self::getContainer()->get('nelmio_api_doc.generator.default')->generate()->toJson(), true);
        $parameters = array_column($document['paths']['/api/device/readings']['get']['parameters'], null, 'name');

        $this->assertSame('-value', $parameters['sort']['schema']['default']);
        $this->assertStringContainsString('value, code, device', $parameters['sort']['description']);
    }

    private function list(array $query): ?array
    {
        $this->client->request('GET', '/api/device/readings', $query, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * @return list<string>
     */
    private function codes(array $query): array
    {
        $payload = $this->list($query);
        $this->assertSame(200, $this->getStatusCode(), json_encode($payload));

        return array_column($payload['data']['items'], 'code');
    }
}
