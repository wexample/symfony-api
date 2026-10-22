<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Wexample\SymfonyApi\Service\UserTokenService;

#[AsCommand(name: 'api:user-token:revoke', description: 'Revokes a user token, or every token of a user with --all.')]
class UserTokenRevokeCommand extends AbstractApiTokenRevokeCommand
{
    public function __construct(UserTokenService $userTokenService)
    {
        parent::__construct($userTokenService);
    }

    protected function getHolderName(): string
    {
        return 'user';
    }
}
