<?php

namespace Wexample\SymfonyApi\Helper;

use Symfony\Component\HttpFoundation\Request;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Helper\VariableHelper;

class ApiHelper
{
    final public const KEY_RESPONSE_DATA = VariableHelper::DATA;
    final public const _KEBAB_FILTER_TAG = 'filter-tag';
    final public const FILTER_TAG = 'filter_tag';
    final public const KEY_RESPONSE_MESSAGE = VariableHelper::MESSAGE;
    final public const KEY_RESPONSE_CODE = VariableHelper::CODE;
    final public const KEY_RESPONSE_TYPE = VariableHelper::TYPE;
    final public const RESPONSE_TYPE_FAILURE = VariableHelper::ERROR;
    final public const RESPONSE_TYPE_SUCCESS = VariableHelper::SUCCESS;
    /**
     * The application's own api: called by programs with their own
     * credentials — tokens, or none —, versioned and documented.
     */
    final public const string PATH_PREFIX_API = '/api/';

    /**
     * What the application's pages read on the session: the lists of its
     * tables, a screen's actions. Reached by a program — the front —, never
     * read by a person, hence the underscore; off the api's documentation.
     */
    final public const string PATH_PREFIX_FRONT = '/_api/';

    final public const HEADER_BEARER_AUTHORIZATION_KEY = "Authorization";
    final public const HEADER_BEARER_AUTHORIZATION_PREFIX = "Bearer ";

    /**
     * What `Request::get()` read before HttpFoundation 8 removed it: the
     * routing attributes, then the query string, then the form body.
     */
    public static function getRequestParameter(
        Request $request,
        string $key,
        mixed $default = null
    ): mixed {
        foreach ([$request->attributes, $request->query, $request->request] as $bag) {
            if ($bag->has($key)) {
                return $bag->all()[$key];
            }
        }

        return $default;
    }

    public static function extractBearerTokenFromRequest(
        Request $request,
        string $bearerIdentifier = ApiHelper::HEADER_BEARER_AUTHORIZATION_KEY
    ): ?string {
        if (! $request->headers->get($bearerIdentifier)) {
            return null;
        }

        return ApiHelper::extractBearerTokenFromString(
            $request->headers->get($bearerIdentifier)
        );
    }

    public static function extractBearerTokenFromString(
        ?string $authHeader,
        string $bearerPrefix = ApiHelper::HEADER_BEARER_AUTHORIZATION_PREFIX
    ): ?string {
        if ($authHeader && str_starts_with($authHeader, $bearerPrefix)) {
            return TextHelper::removePrefix($authHeader, $bearerPrefix);
        }

        return null;
    }
}
