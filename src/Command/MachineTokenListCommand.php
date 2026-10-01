<?php

namespace Wexample\SymfonyApi\Command;

use DateTimeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'api:machine-token:list', description: 'Lists the tokens of a machine client, by hint.')]
class MachineTokenListCommand extends AbstractMachineTokenCommand
{
    protected function configure(): void
    {
        $this->addArgument('client', InputArgument::REQUIRED, 'Id of the machine client');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        foreach ($this->machineTokenService->findTokens($this->getClient($input->getArgument('client'))) as $token) {
            $rows[] = [
                (string) $token->getId(),
                $token->getHint(),
                $token->getLabel(),
                $this->formatDate($token->getDateCreated()),
                $this->formatDate($token->getDateLastUsed()),
                $this->formatDate($token->getDateExpiration()),
                $this->formatDate($token->getDateRevoked()),
            ];
        }

        (new SymfonyStyle($input, $output))->table(
            ['Id', 'Hint', 'Label', 'Created', 'Last used', 'Expires', 'Revoked'],
            $rows
        );

        return self::SUCCESS;
    }

    private function formatDate(?DateTimeInterface $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? '';
    }
}
