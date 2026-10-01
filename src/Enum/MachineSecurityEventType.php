<?php

namespace Wexample\SymfonyApi\Enum;

/**
 * Every fact of a machine token's life the package records. A refusal carries
 * its real cause, in MachineSecurityEvent::$cause.
 */
enum MachineSecurityEventType: string
{
    case ISSUED = 'machine_token.issued';
    case REVOKED = 'machine_token.revoked';
    case ROTATED = 'machine_token.rotated';
    case REFUSED = 'machine_token.refused';

    public function isFailure(): bool
    {
        return self::REFUSED === $this;
    }
}
