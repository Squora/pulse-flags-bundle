<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Command;

use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to create a new persistent feature flag.
 *
 * Creates a new feature flag in persistent storage with minimal configuration.
 * The flag can be enabled or disabled on creation. Additional configuration
 * (strategies, etc.) can be added later via the enable command or
 * through the admin panel.
 *
 * @example php bin/console pulse:flags:create my_new_feature
 * @example php bin/console pulse:flags:create my_new_feature 1
 * @example php bin/console pulse:flags:create my_new_feature true
 *
 * Note: This command creates only persistent flags. To define permanent
 * (static) flags, add them to the YAML configuration files.
 */
#[AsCommand(
    name: 'pulse:flags:create',
    description: 'Create a new feature flag'
)]
class CreateFlagCommand extends Command
{
    public function __construct(
        private readonly PersistentFeatureFlagService $flagService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'The name of the feature flag',
        )->addArgument(
            'enabled',
            InputArgument::OPTIONAL,
            'Enable flag by default (1 = enabled, 0 = disabled)',
            '0',
        );
    }

    /**
     * Executes the command to create a new feature flag.
     *
     * Creates a flag with minimal configuration (name and enabled status).
     * Fails if flag already exists.
     *
     * @param InputInterface $input Command input with flag name and optional enabled status
     * @param OutputInterface $output Command output
     * @return int Command::SUCCESS on success, Command::FAILURE if flag exists
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $enabledArg = $input->getArgument('enabled');
        $enabled = (bool) filter_var($enabledArg, FILTER_VALIDATE_BOOLEAN);

        if ($this->flagService->exists($name)) {
            $io->error(sprintf('Feature flag "%s" already exists', $name));

            return Command::FAILURE;
        }

        $this->flagService->configure($name, ['enabled' => $enabled]);

        $io->success(sprintf(
            'Feature flag "%s" created with status: %s',
            $name,
            $enabled ? 'Enabled' : 'Disabled',
        ));

        return Command::SUCCESS;
    }
}
