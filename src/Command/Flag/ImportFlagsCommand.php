<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Flag;

use Pulse\Flags\Core\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Command to import feature flags from file.
 *
 * Supports JSON and YAML formats.
 */
#[AsCommand(
    name: 'pulse:flags:import',
    description: 'Import feature flags from file'
)]
class ImportFlagsCommand extends Command
{
    public function __construct(
        private readonly PersistentFeatureFlagService $persistentFlagService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'File path to import from')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Import format (json, yaml)', 'json')
            ->addOption('merge', 'm', InputOption::VALUE_NONE, 'Merge with existing flags instead of replacing')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate import without making changes')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command imports feature flags from a file.

<info>Import from JSON file:</info>
  <info>php %command.full_name% flags.json</info>

<info>Import from YAML file:</info>
  <info>php %command.full_name% flags.yaml --format=yaml</info>

<info>Merge with existing flags:</info>
  <info>php %command.full_name% flags.json --merge</info>

<info>Dry run (preview changes):</info>
  <info>php %command.full_name% flags.json --dry-run</info>

<comment>Note:</comment> This command only works with persistent flags (database).
Permanent flags (YAML) must be edited manually.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        $format = $input->getOption('format');
        $merge = $input->getOption('merge');
        $dryRun = $input->getOption('dry-run');

        // Check file exists
        if (!file_exists($file)) {
            $io->error(sprintf('File not found: %s', $file));
            return Command::FAILURE;
        }

        // Read file
        $content = file_get_contents($file);
        if ($content === false) {
            $io->error(sprintf('Failed to read file: %s', $file));
            return Command::FAILURE;
        }

        // Parse content
        try {
            $flags = match ($format) {
                'yaml' => Yaml::parse($content),
                'json' => json_decode($content, true, 512, JSON_THROW_ON_ERROR),
                default => throw new \InvalidArgumentException(sprintf('Unsupported format: %s', $format)),
            };
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to parse file: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if (!is_array($flags) || empty($flags)) {
            $io->error('File contains no valid flags');
            return Command::FAILURE;
        }

        // Display import preview
        $io->title(sprintf('Importing %d flag(s) from %s', count($flags), $file));

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        $io->section('Flags to Import');
        $rows = [];
        foreach ($flags as $name => $config) {
            $strategy = $config['strategy'] ?? 'simple';
            $enabled = ($config['enabled'] ?? false) ? 'Yes' : 'No';
            $status = $this->persistentFlagService->exists($name) ? '<comment>Update</comment>' : '<info>New</info>';

            if ($merge && !$this->persistentFlagService->exists($name)) {
                $status = '<info>Add</info>';
            } elseif ($merge) {
                $status = '<comment>Keep Existing</comment>';
            }

            $rows[] = [$name, $strategy, $enabled, $status];
        }

        $io->table(['Flag Name', 'Strategy', 'Enabled', 'Status'], $rows);

        // Confirm import
        if (!$dryRun && !$io->confirm('Proceed with import?', false)) {
            $io->warning('Import cancelled');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success('Dry run completed - no changes made');
            return Command::SUCCESS;
        }

        // Import flags
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($flags as $name => $config) {
            try {
                $exists = $this->persistentFlagService->exists($name);

                if ($merge && $exists) {
                    $skipped++;
                    continue;
                }

                if ($exists) {
                    // Update existing flag
                    $this->persistentFlagService->update($name, $config);
                    $updated++;
                } else {
                    // Create new flag
                    $this->persistentFlagService->create($name, $config);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[$name] = $e->getMessage();
            }
        }

        // Display results
        $io->newLine();
        $io->section('Import Results');

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total in file', count($flags)],
                ['Imported (new)', $imported],
                ['Updated', $updated],
                ['Skipped (merge mode)', $skipped],
                ['Errors', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $io->error('Import completed with errors:');
            foreach ($errors as $flagName => $error) {
                $io->writeln(sprintf('  <error>%s:</error> %s', $flagName, $error));
            }
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Successfully imported %d flag(s) (%d new, %d updated, %d skipped)',
            $imported + $updated,
            $imported,
            $updated,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
