<?php

namespace Wexample\SymfonyApi\Api\Controller\Test;

use Symfony\Component\Routing\Annotation\Route;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Traits\SymfonyApiBundleClassTrait;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Route(path: '_test/api/response/', name: '_test_api_')]
final class ResponseController extends AbstractApiController
{
    use SymfonyApiBundleClassTrait;

    final public const ROUTE_SUCCESS = VariableHelper::SUCCESS;
    final public const ROUTE_ERROR = VariableHelper::ERROR;

    #[Route(path: 'success', name: self::ROUTE_SUCCESS)]
    public function success(): ApiResponse
    {
        return self::apiResponseSuccess();
    }

    #[Route(path: 'error', name: self::ROUTE_ERROR)]
    public function error(): ApiResponse
    {
        return self::apiResponseError('EXPECTED_ERROR');
    }
}
