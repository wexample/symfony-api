<?php

namespace Wexample\SymfonyApi\Security;

use Wexample\SymfonyApi\Enum\MachineSecurityEventType;

/**
 * The `failure_handler` and `entry_point` of a machine firewall.
 */
class MachineTokenAuthenticationFailureHandler extends AbstractApiTokenAuthenticationFailureHandler
{
    protected function getEventTypeClass(): string
    {
        return MachineSecurityEventType::class;
    }
}
