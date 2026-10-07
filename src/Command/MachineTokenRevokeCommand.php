<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Wexample\SymfonyApi\Service\MachineTokenService;

#[AsCommand(name: 'api:machine-token:revoke', description: 'Revokes a machine token, or every token of a client with --all.')]
class MachineTokenRevokeCommand extends AbstractApiTokenRevokeCommand
{
    public function __construct(MachineTokenService $machineTokenService)
    {
        parent::__construct($machineTokenService);
    }

    protected function getHolderName(): string
    {
        return 'machine client';
    }
}
