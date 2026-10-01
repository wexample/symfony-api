<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;
use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * A refused machine token, carrying for the journal what the response hides.
 */
class MachineTokenRefusedException extends BadCredentialsException
{
    public function __construct(
        public readonly MachineTokenRefusalCause $refusalCause,
        public readonly ?string $tokenHint = null,
        public readonly ?MachineClientInterface $client = null,
    ) {
        parent::__construct('Invalid machine token.');
    }
}
