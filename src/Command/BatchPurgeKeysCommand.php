<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyApi\Service\BatchReceiverService;

#[AsCommand(name: 'api:batch:purge-keys', description: 'Deletes the batch item keys older than wexample_symfony_api.batch.retention.')]
class BatchPurgeKeysCommand extends Command
{
    public function __construct(
        private readonly BatchReceiverService $batchReceiverService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->batchReceiverService->purgeExpiredRecords();
        (new SymfonyStyle($input, $output))->success($count . ' key(s) deleted.');

        return self::SUCCESS;
    }
}
