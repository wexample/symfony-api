<?php

namespace Wexample\SymfonyApi\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Wexample\SymfonyApi\Entity\AbstractMachineToken;
use Wexample\SymfonyApi\Interface\MachineClientInterface;
use Wexample\SymfonyApi\Service\MachineTokenService;

/**
 * Machine tokens are handed out by an operator, from the console. None of
 * these commands ever prints a hash; only `issue` prints a secret.
 */
abstract class AbstractMachineTokenCommand extends Command
{
    public function __construct(
        protected readonly MachineTokenService $machineTokenService,
    ) {
        parent::__construct();
    }

    protected function getClient(string $id): MachineClientInterface
    {
        return $this->machineTokenService->findClient($id)
            ?? throw new InvalidArgumentException('No ' . $this->machineTokenService->getClientClass() . ' with id "' . $id . '".');
    }

    protected function getToken(string $reference): AbstractMachineToken
    {
        return $this->machineTokenService->findTokenByReference($reference)
            ?? throw new InvalidArgumentException('No machine token named "' . $reference . '".');
    }
}
