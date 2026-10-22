<?php

namespace Wexample\SymfonyApi\Exception;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Enum\MachineTokenRefusalCause;

/**
 * A refused API token — a machine's or a user's —, carrying for the journal
 * what the response hides.
 */
class MachineTokenRefusedException extends BadCredentialsException
{
    public function __construct(
        public readonly MachineTokenRefusalCause $refusalCause,
        public readonly ?string $tokenHint = null,
        public readonly ?UserInterface $client = null,
    ) {
        parent::__construct('Invalid API token.');
    }
}
