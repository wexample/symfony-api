<?php

namespace Wexample\SymfonyApi\Api\Controller\Test;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Route(path: '_test/api/', name: '_test_api_')]
class TestController extends AbstractApiController
{
    final public const ROUTE_SUCCESS = VariableHelper::SUCCESS;
    final public const ROUTE_ERROR = VariableHelper::ERROR;

    #[Route(path: 'success', name: self::ROUTE_SUCCESS)]
    public function success(): JsonResponse
    {
        return self::apiResponseSuccess();
    }

    #[Route(path: 'error', name: self::ROUTE_ERROR)]
    public function error(): JsonResponse
    {
        return self::apiResponseError('EXPECTED_ERROR');
    }
}