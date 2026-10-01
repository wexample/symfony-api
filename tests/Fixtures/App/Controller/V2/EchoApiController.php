<?php

namespace Wexample\SymfonyApi\Tests\Fixtures\App\Controller\V2;

use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Attribute\ValidateRequestContent;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Tests\Fixtures\App\Dto\V2\EchoDto;

#[Route(path: '/api/device/v2/', name: 'api_device_v2_')]
class EchoApiController extends AbstractApiController
{
    #[Route(path: 'echo', name: 'echo', methods: ['POST'])]
    #[ValidateRequestContent(dto: EchoDto::class)]
    public function echo(EchoDto $content): ApiResponse
    {
        return self::apiResponseSuccess(data: ['version' => 'v2'] + $content->toArray());
    }
}
