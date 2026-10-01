<?php

namespace Wexample\SymfonyApi\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;
use Wexample\SymfonyApi\Helper\MachineTokenHelper;

class MachineTokenService
{
    public function __construct(
        #[Autowire(param: 'api_machine_token_prefix')]
        private readonly string $prefix,
    ) {
    }

    /**
     * Gives the token a new secret and returns it in plain: the only time it
     * exists outside the client that will present it.
     */
    public function generateSecret(AbstractMachineToken $token): string
    {
        $plain = MachineTokenHelper::generateToken($this->prefix);

        $token
            ->setTokenHash(MachineTokenHelper::hashToken($plain))
            ->setHint(MachineTokenHelper::buildHint($plain, $this->prefix));

        return $plain;
    }
}
