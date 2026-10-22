<?php

namespace Wexample\SymfonyApi\Security;

use Wexample\SymfonyApi\Enum\UserTokenSecurityEventType;

/**
 * The `failure_handler` and `entry_point` of a user token firewall.
 */
class UserTokenAuthenticationFailureHandler extends AbstractApiTokenAuthenticationFailureHandler
{
    protected function getEventTypeClass(): string
    {
        return UserTokenSecurityEventType::class;
    }
}
