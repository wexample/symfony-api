<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Wexample\SymfonyApi\Service\MachineTokenService;

#[AsCommand(name: 'api:machine-token:issue', description: 'Issues a token to a machine client and prints its secret, once.')]
class MachineTokenIssueCommand extends AbstractApiTokenIssueCommand
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
