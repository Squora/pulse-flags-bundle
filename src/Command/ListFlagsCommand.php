<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Command;

use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to list all feature flags in the system.
 *
 * Displays both permanent (configuration-based) and persistent (database/cache)
 * flags in a formatted table with their current status, strategy, and details.
 *
 * @example List all flags
 * php bin/console pulse:flags:list
 *
 * Output includes:
 * - Flag name
 * - Description
 * - Enabled/Disabled status
 * - Type (Permanent/Persistent)
 * - Active strategy
 * - Strategy-specific details (percentage, dates, etc.)
 */
#[AsCommand(
    name: 'pulse:flags:list',
    description: 'List all feature flags (permanent and persistent)'
)]
class ListFlagsCommand extends Command
{
    /**
     * @param PermanentFeatureFlagService $permanentFlagService Service for permanent flags
     * @param PersistentFeatureFlagService $persistentFlagService Service for persistent flags
     */
    public function __construct(
        private PermanentFeatureFlagService $permanentFlagService,
        private PersistentFeatureFlagService $persistentFlagService,
    ) {
        parent::__construct();
    }

    /**
     * Executes the command to display all feature flags.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @return int Command::SUCCESS on success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $permanentFlags = $this->getAllFlags($this->permanentFlagService);
        $persistentFlags = $this->getAllFlags($this->persistentFlagService);
        $totalFlags = count($permanentFlags) + count($persistentFlags);

        if ($totalFlags === 0) {
            $io->info('No feature flags configured');

            return Command::SUCCESS;
        }

        $rows = [];

        // Add permanent flags
        foreach ($permanentFlags as $name => $config) {
            $rows[] = $this->formatFlagRow($name, $config, 'Permanent');
        }

        // Add persistent flags
        foreach ($persistentFlags as $name => $config) {
            $rows[] = $this->formatFlagRow($name, $config, 'Persistent');
        }

        $io->table(
            ['Flag Name', 'Description', 'Status', 'Type', 'Strategy', 'Details'],
            $rows
        );

        $io->success(sprintf(
            'Found %d feature flag(s) - %d permanent, %d persistent',
            $totalFlags,
            count($permanentFlags),
            count($persistentFlags)
        ));

        return Command::SUCCESS;
    }

    /**
     * Fetches all flags from a service using pagination
     *
     * @param PermanentFeatureFlagService|PersistentFeatureFlagService $service
     * @return array<string, array<string, mixed>>
     */
    private function getAllFlags($service): array
    {
        $allFlags = [];
        $page = 1;
        $limit = 100;

        do {
            $result = $service->paginate($page, $limit);
            $allFlags = array_merge($allFlags, $result['flags']);
            $page++;
        } while ($page <= $result['pagination']['pages']);

        return $allFlags;
    }

    /**
     * Formats a single flag's data for table display.
     *
     * @param string $name Flag name
     * @param array<string, mixed> $config Flag configuration
     * @param string $type Flag type (Permanent or Persistent)
     * @return array<int, string> Formatted table row
     */
    private function formatFlagRow(string $name, array $config, string $type): array
    {
        $enabled = $config['enabled'] ?? false;
        $strategy = $config['strategy'] ?? 'simple';

        $details = [];
        if (isset($config['percentage'])) {
            $details[] = "Percentage: {$config['percentage']}%";
        }
        if (isset($config['start_date'])) {
            $details[] = "Start: {$config['start_date']}";
        }
        if (isset($config['end_date'])) {
            $details[] = "End: {$config['end_date']}";
        }

        return [
            $name,
            $config['description'] ?? '-',
            $enabled ? '<fg=green>✓ Enabled</>' : '<fg=red>✗ Disabled</>',
            $type === 'Permanent' ? '<fg=yellow>Permanent</>' : '<fg=cyan>Persistent</>',
            $strategy,
            implode(', ', $details) ?: '-',
        ];
    }
}
