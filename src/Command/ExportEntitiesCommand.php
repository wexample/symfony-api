<?php

namespace Wexample\SymfonyApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'api:export:entities')]
class ExportEntitiesCommand extends Command
{
    public const VISIBILITY_DEFAULT = 'public';

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'YAML source directory', 'pseudocode/entity')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'JSON output directory', 'front/data/entity')
            ->addOption('visibility', null, InputOption::VALUE_REQUIRED, 'Visibility label to export', self::VISIBILITY_DEFAULT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = $this->kernel->getProjectDir();
        $visibility = $input->getOption('visibility');

        $sourceDir = $this->resolvePath($projectDir, $input->getOption('source'));
        $outputDir = $this->resolvePath($projectDir, $input->getOption('output'));

        if (!is_dir($sourceDir)) {
            $output->writeln(sprintf('<error>Source directory not found: %s</error>', $sourceDir));
            return Command::FAILURE;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $exported = 0;
        $removed = 0;

        foreach (glob($sourceDir . '/*.yml') as $yamlFile) {
            $data = Yaml::parseFile($yamlFile) ?? [];
            $fileVisibility = $data['visibility'] ?? self::VISIBILITY_DEFAULT;
            $jsonFile = $outputDir . '/' . basename($yamlFile, '.yml') . '.json';

            if ($fileVisibility !== $visibility) {
                if (file_exists($jsonFile)) {
                    unlink($jsonFile);
                    $removed++;
                }
                continue;
            }

            unset($data['visibility']);

            // Flatten pseudocode items wrapper — extract the entity class directly
            if (isset($data['items'][0])) {
                $data = $data['items'][0];
            }

            $data['name'] = basename($yamlFile, '.yml');

            file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            $exported++;
        }

        $output->writeln(sprintf(
            'Exported %d entities (visibility: %s) to %s — removed %d stale files.',
            $exported,
            $visibility,
            $outputDir,
            $removed
        ));

        return Command::SUCCESS;
    }

    private function resolvePath(string $base, string $path): string
    {
        return str_starts_with($path, '/') ? $path : $base . '/' . $path;
    }
}
