<?php

namespace Wexample\SymfonyApi\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyApi\Service\UserTokenService;

#[AsCommand(name: 'api:user-token:issue', description: 'Issues a token to a user and prints its secret, once.')]
class UserTokenIssueCommand extends AbstractApiTokenIssueCommand
{
    public function __construct(
        private readonly UserTokenService $userTokenService,
    ) {
        parent::__construct($userTokenService);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A role of the user the token is limited to; repeat it for several, omit it for all');
    }

    protected function getHolderName(): string
    {
        return 'user';
    }

    protected function issue(
        UserInterface $client,
        ?DateTimeImmutable $dateExpiration,
        ?string $label,
        InputInterface $input
    ): string {
        $scopes = $input->getOption('scope');

        return $this->userTokenService->issue($client, $dateExpiration, $label, $scopes ?: null);
    }
}
