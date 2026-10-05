<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Wexample\SymfonyApi\Api\Attribute\ApiResponseData;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\DateQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\DisplayFormatQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\AccountDto;

/**
 * An API route of the pages' session firewall.
 */
#[Route(path: '/api/app/', name: 'api_app_')]
class AccountApiController extends AbstractApiController
{
    #[Route(path: 'account', name: 'account', methods: ['GET'])]
    #[DateQueryOption]
    #[DisplayFormatQueryOption]
    #[ApiResponseData(AccountDto::class)]
    public function account(#[CurrentUser] UserInterface $user): ApiResponse
    {
        return self::apiResponseSuccess(data: ['identifier' => $user->getUserIdentifier()]);
    }
}
