<?php

namespace Wexample\SymfonyApi\Api\Controller;

use DateTime;
use DateTimeInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\AbstractQueryOption;
use Wexample\SymfonyApi\Api\Class\AbstractApiResponseMember;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Helper\ApiHelper;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

abstract class AbstractApiController extends AbstractController
{
    final public const ROUTES_PATH_PREFIX = VariableHelper::API;
    final public const ROUTES_NAME_PREFIX = VariableHelper::API;

    final public const DEFAULT_ROUTE_NAME_CREATE = 'create';
    final public const DEFAULT_ROUTE_NAME_DELETE = 'delete';
    final public const DEFAULT_ROUTE_NAME_DETAIL = 'detail';
    final public const DEFAULT_ROUTE_NAME_LIST = VariableHelper::LIST;
    final public const DEFAULT_ROUTE_NAME_SHOW = VariableHelper::SHOW;
    final public const DEFAULT_ROUTE_NAME_UPDATE = 'update';

    public static function apiResponseSuccess(
        $message = null,
        $data = [],
        $status = ApiHelper::RESPONSE_TYPE_SUCCESS,
        bool $prettyPrint = null
    ): ApiResponse {
        return self::apiResponse(
            $message,
            $status,
            $data,
            $prettyPrint
        );
    }

    public static function apiResponse(
        $message = null,
        $type = null,
        $data = null,
        bool $prettyPrint = null,
        int $code = null
    ): ApiResponse {
        if (is_null($code)) {
            $code = ApiHelper::RESPONSE_TYPE_FAILURE === $type
                ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK;
        }

        $content = [
            ApiHelper::KEY_RESPONSE_TYPE => $type,
            ApiHelper::KEY_RESPONSE_CODE => $code,
        ];

        if (!is_null($message)) {
            $content[ApiHelper::KEY_RESPONSE_MESSAGE] = $message;
        }

        if (!is_null($data)) {
            $content[ApiHelper::KEY_RESPONSE_DATA] = $data;
        }

        return new ApiResponse(
            $content,
            $code,
            $prettyPrint
        );
    }

    public static function apiResponseError(
        string|Exception $message,
        $data = [],
        $type = ApiHelper::RESPONSE_TYPE_FAILURE,
        bool $prettyPrint = null,
        int $code = null,
    ): ApiResponse {
        return self::apiResponse(
            $message instanceof Exception ? $message->getMessage() : $message,
            $type,
            $data,
            $prettyPrint,
            $code
        );
    }

    public static function apiResponsePaginated(
        int $page,
        int $length,
        array $items
    ): ApiResponse {
        return self::apiResponseSuccess(
            data: [
                'pagination' => [
                    'page' => $page,
                    'page_length' => $length,
                ],
                'items' => $items,
            ]
        );
    }

    public static function getQueryOptionValue(
        Request $request,
        string $name,
        array|float|int|null|string $default = AbstractApiResponseMember::DISPLAY_FORMAT_DEFAULT,
    ): mixed {
        /** @var AbstractQueryOption $attribute */
        if ($attribute = self::findMethodAttribute($request, $name)) {
            return $attribute->getRequestValue(
                $request
            );
        }

        return $default;
    }

    protected static function findMethodAttribute(
        Request $request,
        string $name
    ) {
        $methodClassPath = $request->attributes->get('_controller');

        foreach (ClassHelper::getChildrenAttributes(
            $methodClassPath,
            AbstractQueryOption::class
        ) as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->key === $name) {
                return $instance;
            }
        }

        return null;
    }

    protected function getQueryOptionDateFilter(
        Request $request,
        string $keyYear = VariableHelper::YEAR,
        string $keyMonth = VariableHelper::MONTH,
        string $keyDay = VariableHelper::DAY
    ): DateTimeInterface {
        try {
            return new DateTime(
                implode(
                    '-',
                    [
                        $request->get($keyYear) ?? DateHelper::getCurrentYearInt(),
                        $request->get($keyMonth) ?? '01',
                        $request->get($keyDay) ?? '01',
                    ]
                )
            );
        } catch (Exception) {
            return DateHelper::getCurrentYearDate();
        }
    }
}
