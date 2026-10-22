<?php

namespace Wexample\SymfonyApi\Helper;

class IdempotencyHelper
{
    final public const int KEY_MAX_LENGTH = 255;

    /**
     * The same payload gives the same hash whatever the order of its keys.
     */
    public static function hashPayload(mixed $payload): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonicalize(...), $value);
    }
}
