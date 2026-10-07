<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractApiTokenRevokeCommand extends AbstractApiTokenCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('reference', InputArgument::REQUIRED, 'Token id or hint; with --all, the ' . $this->getHolderName() . ' id')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Revoke every token of the ' . $this->getHolderName());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reference = $input->getArgument('reference');

        if ($input->getOption('all')) {
            $count = $this->tokenService->revokeAll($this->getClient($reference));
            $io->success($count . ' token(s) revoked.');

            return self::SUCCESS;
        }

        $token = $this->getToken($reference);
        $this->tokenService->revoke($token);
        $io->success('Token ' . $token->getHint() . ' revoked.');

        return self::SUCCESS;
    }
}
