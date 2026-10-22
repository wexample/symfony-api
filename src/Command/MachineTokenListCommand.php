<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Wexample\SymfonyApi\Service\MachineTokenService;

#[AsCommand(name: 'api:machine-token:list', description: 'Lists the tokens of a machine client, by hint.')]
class MachineTokenListCommand extends AbstractApiTokenListCommand
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
