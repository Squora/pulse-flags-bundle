<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Command;

use Pulse\FlagsBundle\Storage\DbStorage;
use Pulse\FlagsBundle\Storage\StorageInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to initialize persistent storage backend.
 *
 * Creates necessary database tables, indexes, and other storage structures
 * required for persistent feature flags. Only required for DbStorage backend;
 * other storage types (YAML) don't need initialization.
 *
 * @example Interactive initialization (asks for confirmation)
 * php bin/console pulse:flags:init-storage
 *
 * @example Force initialization without confirmation
 * php bin/console pulse:flags:init-storage --force
 *
 * For DbStorage, this command:
 * - Creates the feature flags table (default: pulse_feature_flags)
 * - Creates appropriate indexes
 * - Uses database-specific SQL (MySQL/PostgreSQL/SQLite)
 *
 * Note: Alternatively, you can use Doctrine migrations for database setup.
 */
#[AsCommand(
    name: 'pulse:flags:init-storage',
    description: 'Initialize persistent storage (create tables, indexes, etc.)'
)]
class InitStorageCommand extends Command
{
    /**
     * @param StorageInterface $persistentStorage The configured persistent storage backend
     */
    public function __construct(
        private readonly StorageInterface $persistentStorage
    ) {
        parent::__construct();
    }

    /**
     * Configures command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force initialization without confirmation'
        );
    }

    /**
     * Executes the command to initialize persistent storage.
     *
     * Only performs initialization for DbStorage backend. Other storage
     * types (YAML) are skipped with a warning message.
     *
     * @param InputInterface $input Command input with optional --force flag
     * @param OutputInterface $output Command output
     * @return int Command::SUCCESS on success or skip, Command::FAILURE on error
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->persistentStorage instanceof DbStorage) {
            if (!$input->getOption('force')) {
                if (!$io->confirm('This will create the feature_flags table in your database. Continue?', false)) {
                    $io->warning('Initialization cancelled.');

                    return Command::SUCCESS;
                }
            }

            try {
                $this->persistentStorage->initializeTable();
                $io->success('Database storage initialized successfully!');

                $io->writeln([
                    '',
                    'Table created: pulse_feature_flags',
                    'Driver: ' . $this->persistentStorage->getDriver(),
                    '',
                ]);

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $io->error('Failed to initialize storage: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $io->warning('Current persistent storage does not require initialization (not using DbStorage).');
        $io->note('Storage type: ' . get_class($this->persistentStorage));

        return Command::SUCCESS;
    }
}
