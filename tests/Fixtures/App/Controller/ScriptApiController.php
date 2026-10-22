<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;

/**
 * Routes of the user token firewall: a person's script.
 */
#[Route(path: '/api/script/', name: 'api_script_')]
class ScriptApiController extends AbstractApiController
{
    #[Route(path: 'whoami', name: 'whoami', methods: ['GET'])]
    public function whoami(#[CurrentUser] UserInterface $user): ApiResponse
    {
        return self::apiResponseSuccess(data: [
            'identifier' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * Only for the people whose role allows it.
     */
    #[Route(path: 'import', name: 'import', methods: ['POST'])]
    public function import(): ApiResponse
    {
        return self::apiResponseSuccess(data: ['imported' => true]);
    }
}
