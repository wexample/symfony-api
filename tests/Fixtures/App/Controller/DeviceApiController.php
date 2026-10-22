<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wexample\SymfonyApi\Api\Attribute\ApiBatch;
use Wexample\SymfonyApi\Api\Attribute\ApiResponseData;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SortQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Exception\BatchItemRejectedException;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyApi\Service\BatchReceiverService;
use Wexample\SymfonyApi\Service\MachineTokenService;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\ReadingDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\ReadingRowDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\DeviceFile;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;
use Wexample\SymfonyApi\Tests\Fixtures\App\Normalizer\DeviceFileNormalizer;

#[Route(path: '/api/device/', name: 'api_device_')]
class DeviceApiController extends AbstractApiController
{
    #[Route(path: 'whoami', name: 'whoami', methods: ['GET'])]
    public function whoami(#[CurrentUser] MachineClientInterface $client): ApiResponse
    {
        return self::apiResponseSuccess(data: [
            'identifier' => $client->getUserIdentifier(),
            'roles' => $client->getRoles(),
        ]);
    }

    /**
     * The readings of the calling device, a page at a time.
     */
    #[Route(path: 'readings', name: 'readings_list', methods: ['GET'])]
    #[PageQueryOption]
    #[LengthQueryOption]
    #[SortQueryOption(allowed: ['value', 'code', 'device' => 'device.id'], default: '-value')]
    #[ApiResponseData(ReadingRowDto::class, paginated: true)]
    public function listReadings(
        Request $request,
        #[CurrentUser] Device $device,
        EntityManagerInterface $entityManager
    ): ApiResponse {
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('reading')
            ->from(Reading::class, 'reading')
            ->join('reading.device', 'device')
            ->where('device.id = :device')
            // Typed: SQLite stores the UUID as binary.
            ->setParameter('device', $device->getId(), UuidType::NAME);

        return self::apiResponseQueryPage($request, $queryBuilder, fn (array $readings) => array_map(
            fn (Reading $reading) => ['value' => $reading->value, 'code' => $reading->code],
            $readings
        ));
    }

    /**
     * The files of the calling device: computed, never stored.
     */
    #[Route(path: 'files', name: 'files', methods: ['GET'])]
    #[PageQueryOption]
    #[LengthQueryOption]
    #[SortQueryOption(allowed: ['name', 'size', 'dateModified'], default: 'name')]
    public function files(Request $request, DeviceFileNormalizer $normalizer): ApiResponse
    {
        $files = [
            new DeviceFile('readings.csv', 4096, new DateTimeImmutable('2026-10-01 08:00')),
            new DeviceFile('boot.log', 512, new DateTimeImmutable('2026-10-03 09:00')),
            new DeviceFile('config.yml', 128),
            new DeviceFile('firmware.bin', 65536, new DateTimeImmutable('2026-09-01 12:00')),
            new DeviceFile('alarms.json', 512, new DateTimeImmutable('2026-10-02 10:00')),
        ];

        [$pagination, $page] = self::applyQueryOptionsToList($request, $files);

        return self::apiResponsePaginated($pagination, $normalizer->normalizeCollection($page));
    }

    #[Route(path: 'readings', name: 'readings', methods: ['POST'])]
    #[ApiBatch(itemDto: ReadingDto::class, maxItems: 15)]
    public function readings(
        Request $request,
        BatchReceiverService $batchReceiverService,
        EntityManagerInterface $entityManager
    ): ApiResponse {
        return self::apiResponseBatch($batchReceiverService->receive(
            $request,
            ReadingDto::class,
            function (ReadingDto $item, Device $device) use ($entityManager) {
                $reading = new Reading();
                $reading->device = $device;
                $reading->value = $item->value;
                $reading->code = $item->code;
                $entityManager->persist($reading);

                if (ReadingDto::VALUE_REFUSED === $item->value) {
                    throw new BatchItemRejectedException('DEVICE_MISMATCH', 'The reading does not belong to this device.');
                }

                if (ReadingDto::VALUE_THAT_FAILS === $item->value) {
                    throw new RuntimeException('Processor failure.');
                }

                return ['id' => (string) $reading->getId()];
            },
            maxItems: 15
        ));
    }

    /**
     * A device renewing its own token.
     */
    #[Route(path: 'rotate', name: 'rotate', methods: ['POST'])]
    public function rotate(
        #[CurrentUser] MachineClientInterface $client,
        MachineTokenService $machineTokenService
    ): ApiResponse {
        return self::apiResponseSuccess(data: [
            'token' => $machineTokenService->rotate($client, new DateInterval('PT1H')),
        ]);
    }
}
