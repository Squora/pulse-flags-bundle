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
 * Console command to disable a feature flag.
 *
 * Disables persistent (writable) feature flags by setting enabled=false
 * while keeping the configuration in storage.
 *
 * @example Disable an existing feature flag:
 * php bin/console pulse:flags:disable my_feature
 *
 * Note: Only works with persistent flags. Permanent flags are read-only.
 * To remove a flag completely, use pulse:flags:remove command.
 */
#[AsCommand(
    name: 'pulse:flags:disable',
    description: 'Disable a feature flag'
)]
class DisableFlagCommand extends Command
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
            'Feature flag name to disable',
        );
    }

    /**
     * Executes the command to disable a feature flag.
     *
     * Sets the flag's enabled status to false while keeping the configuration.
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

        $this->flagService->disable($name);
        $io->success("Feature flag $name disabled");

        return Command::SUCCESS;
    }
}
