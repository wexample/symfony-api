<?php

namespace Wexample\SymfonyApi\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Service\AbstractApiTokenService;

/**
 * Tokens handed out or cut off by an operator, from the console — one set of
 * commands per kind. None of them ever prints a hash; only `issue` prints a
 * secret.
 */
abstract class AbstractApiTokenCommand extends Command
{
    public function __construct(
        protected readonly AbstractApiTokenService $tokenService,
    ) {
        parent::__construct();
    }

    /**
     * Who holds this kind of token, for the help: "machine client", "user".
     */
    abstract protected function getHolderName(): string;

    protected function getClient(string $id): UserInterface
    {
        return $this->tokenService->findClient($id)
            ?? throw new InvalidArgumentException('No ' . $this->tokenService->getClientClass() . ' with id "' . $id . '".');
    }

    protected function getToken(string $reference): AbstractApiToken
    {
        return $this->tokenService->findTokenByReference($reference)
            ?? throw new InvalidArgumentException('No token named "' . $reference . '".');
    }
}
