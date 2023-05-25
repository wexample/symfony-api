<?php

namespace Wexample\SymfonyApi\Api\Controller\Test;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\YearQueryOption;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Route(path: '_test/api/query-option/', name: '_test_query_option_')]
class QueryOptionController extends AbstractApiController
{
    final public const ROUTE_YEAR = VariableHelper::YEAR;

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