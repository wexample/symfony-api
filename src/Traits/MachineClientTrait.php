<?php

namespace Wexample\SymfonyApi\Traits;

use Wexample\SymfonyApi\Interface\MachineClientInterface;

/**
 * The security half of a machine client: the machine role and nothing else,
 * no credential to erase.
 */
trait MachineClientTrait
{
    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [MachineClientInterface::ROLE];
    }

    public function eraseCredentials(): void
    {
    }
}
