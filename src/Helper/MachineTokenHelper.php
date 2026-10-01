<?php

namespace Wexample\SymfonyApi\Helper;

class MachineTokenHelper
{
    final public const string HASH_ALGORITHM = 'sha256';

    // 43 base62 characters carry 256 bits.
    final public const int SECRET_LENGTH = 43;

    final public const int HINT_SECRET_LENGTH = 6;

    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function generateToken(string $prefix): string
    {
        $secret = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::SECRET_LENGTH; $i++) {
            $secret .= self::ALPHABET[random_int(0, $max)];
        }

        return $prefix . $secret;
    }

    /**
     * A token is random and long: a plain hash is enough to keep it unusable
     * from the database, and lets it be looked up directly.
     */
    public static function hashToken(string $token): string
    {
        return hash(self::HASH_ALGORITHM, $token);
    }

    /**
     * What a log line or a support ticket may carry to name a token: its
     * prefix and the first characters of the secret.
     */
    public static function buildHint(
        string $token,
        string $prefix
    ): string {
        return substr($token, 0, strlen($prefix) + self::HINT_SECRET_LENGTH) . '…';
    }
}
