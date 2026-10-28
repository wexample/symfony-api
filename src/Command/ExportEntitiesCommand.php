<?php

namespace Wexample\SymfonyApi\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Helper\EntityHierarchyHelper;

/**
 * The pseudocode of the entities, as the front reads it: one json per entity,
 * and, for an entity of a hierarchy, what the mapping alone knows — the
 * discriminator the api serves with every row, as a property of the entity.
 */
#[AsCommand(name: 'api:export:entities')]
class ExportEntitiesCommand extends Command
{
    public const VISIBILITY_DEFAULT = 'public';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ManagerRegistry $doctrine,
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

        if (! is_dir($sourceDir)) {
            $output->writeln(sprintf('<error>Source directory not found: %s</error>', $sourceDir));

            return Command::FAILURE;
        }

        if (! is_dir($outputDir)) {
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
            $data = $this->describeHierarchy($data);

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

    /**
     * What the entity is within its hierarchy, when it has one: the name of
     * the discriminator and the value this class stores in it, and that
     * discriminator among the properties, since the api serves it as one and
     * the browser refuses a property its schema does not list.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function describeHierarchy(array $data): array
    {
        $class = $this->findMappedClass($data['name']);

        if (! $class || ! EntityHierarchyHelper::isInHierarchy($class)) {
            return $data;
        }

        $property = EntityHierarchyHelper::getDiscriminatorName($class);

        $data['discriminator'] = [
            'property' => $property,
            'value' => EntityHierarchyHelper::getDiscriminatorValue($class),
        ];
        $data['properties'][] = [
            'name' => $property,
            'type' => 'string',
            'readOnly' => true,
        ];

        return $data;
    }

    /**
     * The mapped entity the pseudocode file is named after, if any: an entity
     * with no table is in the pseudocode and not in the mapping.
     */
    private function findMappedClass(string $name): ?string
    {
        foreach ($this->doctrine->getManagers() as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                if (ClassHelper::getTableizedName($metadata->getName()) === $name) {
                    return $metadata->getName();
                }
            }
        }

        return null;
    }

    private function resolvePath(string $base, string $path): string
    {
        return str_starts_with($path, '/') ? $path : $base . '/' . $path;
    }
}
