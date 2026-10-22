<?php

namespace Wexample\SymfonyApi\Enum;

/**
 * Why a machine request was refused. The client never learns it: it goes to
 * the journal, the response stays the same.
 */
enum MachineTokenRefusalCause: string
{
    case MISSING = 'missing';
    case UNKNOWN = 'unknown';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
    case ROLE_NOT_ALLOWED = 'role_not_allowed';
    case CLIENT_DISABLED = 'client_disabled';
}
