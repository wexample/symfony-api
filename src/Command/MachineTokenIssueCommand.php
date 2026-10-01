<?php

namespace Wexample\SymfonyApi\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'api:machine-token:issue', description: 'Issues a token to a machine client and prints its secret, once.')]
class MachineTokenIssueCommand extends AbstractMachineTokenCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('client', InputArgument::REQUIRED, 'Id of the machine client')
            ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'Expiration date, any format DateTimeImmutable reads ("+1 year", "2027-01-01")')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Free text telling the tokens of the client apart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expires = $input->getOption('expires');

        $plain = $this->machineTokenService->issue(
            $this->getClient($input->getArgument('client')),
            $expires ? new DateTimeImmutable($expires) : null,
            $input->getOption('label')
        );

        $io->warning('This secret is shown once and cannot be read again. Hand it to the client now.');
        $output->writeln($plain);

        return self::SUCCESS;
    }
}
