<?php

namespace Wexample\SymfonyApi\Api\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyApi\Helper\ApiHelper;

abstract class AbstractApiController extends AbstractController
{
    public static function apiResponseSuccess(
        $message = null,
        $data = [],
        $status = ApiHelper::RESPONSE_TYPE_SUCCESS,
    ): JsonResponse {
        return self::apiResponse(
            $message,
            $status,
            $data
        );
    }

    public static function apiResponse(
        $message = null,
        $type = ApiHelper::RESPONSE_TYPE_SUCCESS,
        $data = []
    ): JsonResponse {
        $status = ApiHelper::RESPONSE_TYPE_FAILURE === $type
            ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK;

        $dataDefault = [
            ApiHelper::KEY_RESPONSE_TYPE => $type,
            ApiHelper::KEY_RESPONSE_STATUS => $status,
        ];

        if (!is_null($message)) {
            $dataDefault[ApiHelper::KEY_RESPONSE_MESSAGE] = $message;
        }

        return new JsonResponse(
            array_merge(
                $dataDefault,
                $data
            ),
            $status
        );
    }

    public static function apiResponseError(
        string|Exception $message,
        $data = [],
        $type = ApiHelper::RESPONSE_TYPE_FAILURE,
    ): JsonResponse {
        return self::apiResponse(
            $message instanceof Exception ? $message->getMessage() : $message,
            $type,
            $data
        );
    }
}
