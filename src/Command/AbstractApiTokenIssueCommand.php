<?php

namespace Wexample\SymfonyApi\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractApiTokenIssueCommand extends AbstractApiTokenCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('client', InputArgument::REQUIRED, 'Id of the ' . $this->getHolderName())
            ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'Expiration date, any format DateTimeImmutable reads ("+1 year", "2027-01-01")')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Free text telling the tokens of the ' . $this->getHolderName() . ' apart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expires = $input->getOption('expires');

        $plain = $this->issue(
            $this->getClient($input->getArgument('client')),
            $expires ? new DateTimeImmutable($expires) : null,
            $input->getOption('label'),
            $input
        );

        $io->warning('This secret is shown once and cannot be read again. Hand it over now.');
        $output->writeln($plain);

        return self::SUCCESS;
    }

    /**
     * What a kind adds to the issue from its own options.
     */
    protected function issue(
        UserInterface $client,
        ?DateTimeImmutable $dateExpiration,
        ?string $label,
        InputInterface $input
    ): string {
        return $this->tokenService->issue($client, $dateExpiration, $label);
    }
}
