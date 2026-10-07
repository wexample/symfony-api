<?php

namespace Wexample\SymfonyApi\Enum;

use Wexample\SymfonySecurity\Interface\SecurityEventTypeInterface;

/**
 * Every fact of a user token's life the package records: the same facts as a
 * machine token's, under their own codes. A refusal carries its real cause,
 * in UserTokenSecurityEvent::$cause.
 */
enum UserTokenSecurityEventType: string implements SecurityEventTypeInterface
{
    case ISSUED = 'user_token.issued';
    case REVOKED = 'user_token.revoked';
    case ROTATED = 'user_token.rotated';
    case REFUSED = 'user_token.refused';
    case THROTTLED = 'user_token.throttled';

    public function isFailure(): bool
    {
        return in_array($this, [self::REFUSED, self::THROTTLED], true);
    }
}
