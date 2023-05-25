<?php

namespace Wexample\SymfonyApi\Api\Controller\Test;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\IdQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\YearQueryOption;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Route(path: '_test/api/query-option/', name: '_test_query_option_')]
class QueryOptionController extends AbstractApiController
{
    final public const ROUTE_ID = VariableHelper::ID;
    final public const ROUTE_LENGTH = VariableHelper::LENGTH;
    final public const ROUTE_PAGE = VariableHelper::PAGE;
    final public const ROUTE_YEAR = VariableHelper::YEAR;

    #[Route(path: VariableHelper::ID, name: self::ROUTE_ID)]
    #[IdQueryOption(required: true)]
    public function id(Request $request): JsonResponse
    {
        return self::apiResponseSuccess([
            VariableHelper::ID => $request->get(
                VariableHelper::ID
            ),
        ]);
    }

    #[Route(path: VariableHelper::LENGTH, name: self::ROUTE_LENGTH)]
    #[LengthQueryOption(required: true)]
    public function length(Request $request): JsonResponse
    {
        return self::apiResponseSuccess([
            VariableHelper::LENGTH => $request->get(
                VariableHelper::LENGTH
            ),
        ]);
    }

    #[Route(path: VariableHelper::PAGE, name: self::ROUTE_PAGE)]
    #[PageQueryOption(required: true)]
    public function page(Request $request): JsonResponse
    {
        return self::apiResponseSuccess([
            VariableHelper::PAGE => $request->get(
                VariableHelper::PAGE
            ),
        ]);
    }

    #[Route(path: VariableHelper::YEAR, name: self::ROUTE_YEAR)]
    #[YearQueryOption(required: true)]
    public function year(Request $request): JsonResponse
    {
        return self::apiResponseSuccess([
            VariableHelper::YEAR => $this
                ->getQueryOptionDateFilter(
                    $request
                )
                ->format(
                    DateHelper::DATE_PATTERN_DAY_DEFAULT
                ),
        ]);
    }
}