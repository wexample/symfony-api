<?php

namespace Wexample\SymfonyApi\Command;

use DateTimeInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyApi\Entity\AbstractApiToken;

abstract class AbstractApiTokenListCommand extends AbstractApiTokenCommand
{
    protected function configure(): void
    {
        $this->addArgument('client', InputArgument::REQUIRED, 'Id of the ' . $this->getHolderName());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        foreach ($this->tokenService->findTokens($this->getClient($input->getArgument('client'))) as $token) {
            $rows[] = [
                (string) $token->getId(),
                $token->getHint(),
                $token->getLabel(),
                $this->formatDate($token->getDateCreated()),
                $this->formatDate($token->getDateLastUsed()),
                $this->formatDate($token->getDateExpiration()),
                $this->formatDate($token->getDateRevoked()),
                ...$this->getExtraCells($token),
            ];
        }

        (new SymfonyStyle($input, $output))->table(
            ['Id', 'Hint', 'Label', 'Created', 'Last used', 'Expires', 'Revoked', ...$this->getExtraHeaders()],
            $rows
        );

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function getExtraHeaders(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function getExtraCells(AbstractApiToken $token): array
    {
        return [];
    }

    private function formatDate(?DateTimeInterface $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? '';
    }
}
