<?php

namespace Wexample\SymfonyApi\Api\Controller\Test;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Type;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\CustomQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\DisplayFormatQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\FilterTagQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\IdQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\PageQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\YearQueryOption;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Helper\ApiHelper;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\TypesHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

#[Route(path: '_test/api/query-option/', name: '_test_query_option_')]
class QueryOptionController extends AbstractApiController
{
    final public const ROUTE_CUSTOM = VariableHelper::CUSTOM;
    final public const ROUTE_DISPLAY_FORMAT = ApiHelper::DISPLAY_FORMAT;
    final public const ROUTE_FILTER_TAG = ApiHelper::FILTER_TAG;
    final public const ROUTE_ID = VariableHelper::ID;
    final public const ROUTE_LENGTH = VariableHelper::LENGTH;
    final public const ROUTE_PAGE = VariableHelper::PAGE;
    final public const ROUTE_YEAR = VariableHelper::YEAR;

    final public const TEST_CUSTOM_TYPES = [
        TypesHelper::BOOLEAN,
        TypesHelper::INTEGER,
        TypesHelper::STRING,
    ];

    #[Route(path: VariableHelper::CUSTOM, name: self::ROUTE_CUSTOM)]
    #[CustomQueryOption(key: VariableHelper::CUSTOM.'-'.TypesHelper::BOOLEAN, constraint: new Type(TypesHelper::BOOLEAN), required: true)]
    #[CustomQueryOption(key: VariableHelper::CUSTOM.'-'.TypesHelper::INTEGER, constraint: new Type(TypesHelper::INTEGER), required: true)]
    #[CustomQueryOption(key: VariableHelper::CUSTOM.'-'.TypesHelper::STRING, constraint: new Type(TypesHelper::STRING), required: true)]
    public function custom(Request $request): JsonResponse
    {
        $data = [];

        foreach (self::TEST_CUSTOM_TYPES as $type) {
            $data[VariableHelper::CUSTOM . '_' . $type] = $request->get(
                VariableHelper::CUSTOM. '-' . $type
            );
        }

        return self::apiResponseSuccess($data);
    }

    #[Route(path: ApiHelper::_KEBAB_DISPLAY_FORMAT, name: self::ROUTE_DISPLAY_FORMAT)]
    #[DisplayFormatQueryOption(required: true)]
    public function displayFormat(Request $request): JsonResponse
    {
        return self::apiResponseSuccess([
            ApiHelper::DISPLAY_FORMAT => $request->get(
                ApiHelper::_KEBAB_DISPLAY_FORMAT
            ),
        ]);
    }

    #[Route(path: ApiHelper::_KEBAB_FILTER_TAG, name: self::ROUTE_FILTER_TAG)]
    #[FilterTagQueryOption(required: true)]
    public function filterTag(Request $request): JsonResponse
    {
        return self::apiResponseSuccess([
            ApiHelper::FILTER_TAG => $request->get(
                ApiHelper::_KEBAB_FILTER_TAG
            ),
        ]);
    }

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