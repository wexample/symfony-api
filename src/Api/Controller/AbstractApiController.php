<?php

namespace Wexample\SymfonyApi\Api\Controller;

use DateTime;
use DateTimeInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Helper\ApiHelper;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\DateHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

abstract class AbstractApiController extends AbstractController
{
    final public const ROUTES_PATH_PREFIX = VariableHelper::API;
    final public const ROUTES_NAME_PREFIX = VariableHelper::API;

    public static function apiResponseSuccess(
        $message = null,
        $data = [],
        $status = ApiHelper::RESPONSE_TYPE_SUCCESS,
        bool $prettyPrint = false
    ): JsonResponse {
        return self::apiResponse(
            $message,
            $status,
            $data,
            $prettyPrint
        );
    }

    public static function apiResponse(
        $message = null,
        $type = ApiHelper::RESPONSE_TYPE_SUCCESS,
        $data = null,
        bool $prettyPrint = false
    ): JsonResponse {
        $status = ApiHelper::RESPONSE_TYPE_FAILURE === $type
            ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK;

        $content = [
            ApiHelper::KEY_RESPONSE_TYPE => $type,
            ApiHelper::KEY_RESPONSE_STATUS => $status,
        ];

        if (!is_null($message)) {
            $content[ApiHelper::KEY_RESPONSE_MESSAGE] = $message;
        }

        if (!is_null($data)) {
            $content[ApiHelper::KEY_RESPONSE_DATA] = $data;
        }

        $response = new JsonResponse(
            $content,
            $status,
        );

        if ($prettyPrint) {
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_PRETTY_PRINT
            );
        }

        return $response;
    }

    public static function apiResponseError(
        string|Exception $message,
        $data = [],
        $type = ApiHelper::RESPONSE_TYPE_FAILURE,
        bool $prettyPrint = false
    ): JsonResponse {
        return self::apiResponse(
            $message instanceof Exception ? $message->getMessage() : $message,
            $type,
            $data,
            $prettyPrint
        );
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
