<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Wexample\SymfonyApi\Entity\AbstractApiToken;
use Wexample\SymfonyApi\Entity\AbstractUserToken;
use Wexample\SymfonyApi\Service\UserTokenService;

#[AsCommand(name: 'api:user-token:list', description: 'Lists the tokens of a user, by hint.')]
class UserTokenListCommand extends AbstractApiTokenListCommand
{
    public function __construct(UserTokenService $userTokenService)
    {
        parent::__construct($userTokenService);
    }

    protected function getHolderName(): string
    {
        return 'user';
    }

    protected function getExtraHeaders(): array
    {
        return ['Scopes'];
    }

    protected function getExtraCells(AbstractApiToken $token): array
    {
        $scopes = $token instanceof AbstractUserToken ? $token->getScopes() : null;

        return [null === $scopes ? 'all' : implode(', ', $scopes)];
    }
}
