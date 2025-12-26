<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Command\Query;

use Pulse\Flags\Core\Constants\Pagination;
use Pulse\Flags\Core\Service\PermanentFeatureFlagService;
use Pulse\Flags\Core\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to list all feature flags in the system.
 *
 * Displays both permanent (configuration-based) and persistent (database/cache)
 * flags in a formatted table with their current status, strategy, and details.
 *
 * @example List all flags with pagination
 * php bin/console pulse:flags:list
 * php bin/console pulse:flags:list --page=2 --limit=20
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
    public function __construct(
        private readonly PermanentFeatureFlagService $permanentFlagService,
        private readonly PersistentFeatureFlagService $persistentFlagService,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command options.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'page',
                'p',
                InputOption::VALUE_REQUIRED,
                'Page number (1-indexed)',
                Pagination::DEFAULT_PAGE
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Items per page',
                Pagination::DEFAULT_LIMIT
            );
    }

    /**
     * Executes the command to display all feature flags.
     *
     * @return int Command::SUCCESS on success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $page = max(1, (int) $input->getOption('page'));
        $limit = min(Pagination::MAX_LIMIT, max(1, (int) $input->getOption('limit')));

        // Get paginated results for both types
        $permanentResult = $this->permanentFlagService->paginate($page, $limit);
        $persistentResult = $this->persistentFlagService->paginate($page, $limit);

        $permanentFlags = $permanentResult['flags'];
        $persistentFlags = $persistentResult['flags'];
        $permanentPagination = $permanentResult['pagination'];
        $persistentPagination = $persistentResult['pagination'];

        $totalFlagsOnPage = count($permanentFlags) + count($persistentFlags);
        $totalFlagsOverall = $permanentPagination['total'] + $persistentPagination['total'];

        if (0 === $totalFlagsOverall) {
            $io->info('No feature flags configured');
            return Command::SUCCESS;
        }

        if (0 === $totalFlagsOnPage) {
            $io->warning(sprintf('Page %d is empty. Total flags: %d', $page, $totalFlagsOverall));
            return Command::SUCCESS;
        }

        $rows = [];

        // Add permanent flags
        if (!empty($permanentFlags)) {
            foreach ($permanentFlags as $name => $config) {
                $rows[] = $this->formatFlagRow($name, $config, 'Permanent');
            }
        }

        // Add persistent flags
        if (!empty($persistentFlags)) {
            foreach ($persistentFlags as $name => $config) {
                $rows[] = $this->formatFlagRow($name, $config, 'Persistent');
            }
        }

        $io->table(
            ['Flag Name', 'Description', 'Status', 'Type', 'Strategy', 'Details'],
            $rows,
        );

        // Pagination info
        $maxPages = max($permanentPagination['pages'], $persistentPagination['pages']);
        $io->note(sprintf(
            'Page %d of %d | Showing %d of %d total flags (%d permanent, %d persistent)',
            $page,
            $maxPages,
            $totalFlagsOnPage,
            $totalFlagsOverall,
            $permanentPagination['total'],
            $persistentPagination['total']
        ));

        if ($page < $maxPages) {
            $io->text(sprintf(
                'Run <comment>pulse:flags:list --page=%d</comment> to see next page',
                $page + 1
            ));
        }

        return Command::SUCCESS;
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
