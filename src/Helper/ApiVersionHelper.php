<?php

namespace Wexample\SymfonyApi\Helper;

/**
 * The version of an API endpoint is a segment of its path, visible in proxy
 * logs, in the journal, and in the code of the route: numbered —
 * `/api/device/v2/…` — or dated, a year and its release in it —
 * `/api/2026-1/…`.
 */
class ApiVersionHelper
{
    final public const string PATTERN = '#/(v\d+|\d{4}-\d{1,2})(?:/|$)#';

    public static function fromPath(string $path): ?string
    {
        return preg_match(self::PATTERN, $path, $matches) ? $matches[1] : null;
    }
}
