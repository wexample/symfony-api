<?php

namespace Wexample\SymfonyApi\Exception;

use DateTimeImmutable;
use RuntimeException;

/**
 * A password login refused. The response says no more than invalid or
 * throttled: an unknown account, a wrong password and a disabled account
 * answer the same.
 */
class UserTokenLoginException extends RuntimeException
{
    final public const string REASON_INVALID = 'invalid';

    final public const string REASON_THROTTLED = 'throttled';

    public function __construct(
        public readonly string $reason,
        public readonly ?DateTimeImmutable $retryAfter = null,
    ) {
        parent::__construct(self::REASON_THROTTLED === $reason ? 'Too many attempts.' : 'Invalid credentials.');
    }
}
