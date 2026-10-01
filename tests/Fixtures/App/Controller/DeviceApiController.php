<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use DateInterval;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyApi\Service\MachineTokenService;

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
