<?php

namespace Wexample\SymfonyApi\Helper;

/**
 * The version of an API endpoint is a segment of its path — `/api/device/v2/…`
 * — visible in proxy logs, in the journal, and in the code of the route.
 */
class ApiVersionHelper
{
    final public const string PATTERN = '#/(v\d+)(?:/|$)#';

    public static function fromPath(string $path): ?string
    {
        return preg_match(self::PATTERN, $path, $matches) ? $matches[1] : null;
    }
}
