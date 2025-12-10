<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Query;

use Pulse\Flags\Core\Service\PermanentFeatureFlagService;
use Pulse\Flags\Core\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to check if a feature flag is enabled for a given context.
 *
 * Tests feature flag activation with optional context (user_id, session_id)
 * and display detailed configuration information.
 *
 * @example Check flag status without context:
 * php bin/console pulse:flags:check my_feature
 *
 * @example Check flag status with user context:
 * php bin/console pulse:flags:check my_feature --user-id=123
 *
 * @example Check flag status with session context:
 * php bin/console pulse:flags:check my_feature --session-id=abc123
 *
 * The command shows:
 * - Current enabled/disabled status with context
 * - Flag type (Permanent or Persistent)
 * - Active strategy and all configuration parameters
 * - Applied context values
 */
#[AsCommand(
    name: 'pulse:flags:check',
    description: 'Check if a feature flag is enabled'
)]
class CheckFlagCommand extends Command
{
    public function __construct(
        private readonly PermanentFeatureFlagService $permanentFlagService,
        private readonly PersistentFeatureFlagService $persistentFlagService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Feature flag name',
        )->addOption(
            'user-id',
            'u',
            InputOption::VALUE_REQUIRED,
            'User ID for context',
        )->addOption(
            'session-id',
            null,
            InputOption::VALUE_REQUIRED,
            'Session ID for context',
        );
    }

    /**
     * Executes the command to check feature flag status.
     *
     * Evaluates the flag against provided context and displays detailed
     * configuration and evaluation result.
     *
     * @return int Command::SUCCESS on success, Command::FAILURE if flag not found
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        $context = [];
        if ($userId = $input->getOption('user-id')) {
            $context['user_id'] = $userId;
        }

        if ($sessionId = $input->getOption('session-id')) {
            $context['session_id'] = $sessionId;
        }

        $config = $this->permanentFlagService->getConfig($name);
        $isPermanent = null !== $config;
        $flagService = $isPermanent ? $this->permanentFlagService : $this->persistentFlagService;

        if (!$isPermanent) {
            $config = $this->persistentFlagService->getConfig($name);
        }

        if (null === $config) {
            $io->warning("Feature flag '$name' is not configured");

            return Command::FAILURE;
        }

        $isEnabled = $flagService->isEnabled($name, $context);

        $io->section('Feature Flag: ' . $name);
        if ($isEnabled) {
            $io->success('Status: ENABLED ✓');
        } else {
            $io->error('Status: DISABLED ✗');
        }

        $io->section('Configuration:');
        $io->table(
            ['Option', 'Value'],
            [
                ['Type', $isPermanent ? 'Permanent (read-only)' : 'Persistent (writable)'],
                ['Globally Enabled', ($config['enabled'] ?? false) ? 'Yes' : 'No'],
                ['Strategy', $config['strategy'] ?? 'simple'],
                ['Percentage', $config['percentage'] ?? '-'],
                ['Start Date', $config['start_date'] ?? '-'],
                ['End Date', $config['end_date'] ?? '-'],
                ['Whitelist', !empty($config['whitelist']) ? implode(', ', $config['whitelist']) : '-'],
                ['Blacklist', !empty($config['blacklist']) ? implode(', ', $config['blacklist']) : '-'],
            ]
        );

        if (!empty($context)) {
            $io->section('Context:');
            $io->table(
                ['Key', 'Value'],
                array_map(static fn ($k, $v) => [$k, $v], array_keys($context), array_values($context))
            );
        }

        return Command::SUCCESS;
    }
}
