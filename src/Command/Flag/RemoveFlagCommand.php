<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Flag;

use Pulse\Flags\Core\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to remove a feature flag completely from storage.
 *
 * Permanently deletes persistent (writable) feature flags from the database.
 * This operation cannot be undone.
 *
 * @example Remove a flag completely
 * php bin/console pulse:flags:remove my_feature
 *
 * Note: Only works with persistent flags. Permanent flags are read-only
 * and defined in configuration files.
 */
#[AsCommand(
    name: 'pulse:flags:remove',
    description: 'Remove a feature flag completely from storage'
)]
class RemoveFlagCommand extends Command
{
    public function __construct(
        private readonly PersistentFeatureFlagService $flagService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Feature flag name to remove',
        );
    }

    /**
     * Executes the command to remove a feature flag.
     *
     * Permanently deletes the flag from persistent storage.
     *
     * @return int Command::SUCCESS on success, Command::FAILURE if flag not found
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        if (!$this->flagService->exists($name)) {
            $io->warning("Feature flag $name does not exist");

            return Command::FAILURE;
        }

        $this->flagService->remove($name);
        $io->success("Feature flag $name removed permanently");

        return Command::SUCCESS;
    }
}
