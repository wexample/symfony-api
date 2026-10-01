<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Exception\BatchItemRejectedException;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyApi\Service\BatchReceiverService;
use Wexample\SymfonyApi\Service\MachineTokenService;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\ReadingDto;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Device;
use Wexample\SymfonyApi\Tests\Fixtures\App\Entity\Reading;

#[Route(path: '/api/device/', name: 'api_device_')]
class DeviceApiController extends AbstractApiController
{
    #[Route(path: 'whoami', name: 'whoami')]
    public function whoami(#[CurrentUser] MachineClientInterface $client): ApiResponse
    {
        return self::apiResponseSuccess(data: [
            'identifier' => $client->getUserIdentifier(),
            'roles' => $client->getRoles(),
        ]);
    }

    #[Route(path: 'readings', name: 'readings', methods: ['POST'])]
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
