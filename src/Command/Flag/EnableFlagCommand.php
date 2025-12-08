<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Command\Flag;

use Pulse\FlagsBundle\Constants\PercentageStrategy as PercentageConstants;
use Pulse\FlagsBundle\Enum\FlagStatus;
use Pulse\FlagsBundle\Enum\FlagStrategy;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to enable a feature flag with optional strategy configuration.
 *
 * Enables persistent (writable) feature flags and allows configuring activation
 * strategies directly from the command line.
 *
 * @example Simple enable
 * php bin/console pulse:flags:enable my_feature
 *
 * @example Enable with percentage rollout
 * php bin/console pulse:flags:enable my_feature --percentage=25
 *
 * @example Enable with date range
 * php bin/console pulse:flags:enable my_feature --start-date=2025-01-01 --end-date=2025-12-31
 *
 * @example Enable with user whitelist
 * php bin/console pulse:flags:enable my_feature --whitelist=123 --whitelist=456
 *
 * Note: Only works with persistent flags. Permanent flags are read-only.
 */
#[AsCommand(
    name: 'pulse:flags:enable',
    description: 'Enable a feature flag'
)]
class EnableFlagCommand extends Command
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
            'Feature flag name',
        )->addOption(
            'strategy',
            's',
            InputOption::VALUE_REQUIRED,
            'Strategy to use',
            FlagStrategy::SIMPLE->value,
        )->addOption(
            'percentage',
            'p',
            InputOption::VALUE_REQUIRED,
            'Percentage for rollout (0-100)',
        )->addOption(
            'start-date',
            null,
            InputOption::VALUE_REQUIRED,
            'Start date (Y-m-d)',
        )->addOption(
            'end-date',
            null,
            InputOption::VALUE_REQUIRED,
            'End date (Y-m-d)',
        )->addOption(
            'whitelist',
            'w',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'User IDs to whitelist',
        );
    }

    /**
     * Executes the command to enable a feature flag.
     *
     * Configures the flag with enabled=true and applies any provided strategy settings.
     * Strategy is automatically determined based on provided options.
     *
     * @return int Command::SUCCESS on success, Command::FAILURE on validation errors
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $strategy = $input->getOption('strategy');

        $config = [
            'enabled' => FlagStatus::ENABLED->toBool(),
            'strategy' => $strategy,
        ];

        if ($percentage = $input->getOption('percentage')) {
            $percentage = (int) $percentage;
            if ($percentage < PercentageConstants::MIN_PERCENTAGE || $percentage > PercentageConstants::MAX_PERCENTAGE) {
                $io->error(sprintf('Percentage must be between %d and %d', PercentageConstants::MIN_PERCENTAGE, PercentageConstants::MAX_PERCENTAGE));

                return Command::FAILURE;
            }

            $config['percentage'] = $percentage;
            $config['strategy'] = FlagStrategy::PERCENTAGE->value;
        }

        if ($startDate = $input->getOption('start-date')) {
            $config['start_date'] = $startDate;
            $config['strategy'] = FlagStrategy::DATE_RANGE->value;
        }

        if ($endDate = $input->getOption('end-date')) {
            $config['end_date'] = $endDate;
            $config['strategy'] = FlagStrategy::DATE_RANGE->value;
        }

        if ($whitelist = $input->getOption('whitelist')) {
            $config['whitelist'] = $whitelist;
            $config['strategy'] = FlagStrategy::USER_ID->value;
        }

        $this->flagService->configure($name, $config);
        $io->success("Feature flag '$name' enabled");

        $io->section('Configuration:');
        $io->table(
            ['Option', 'Value'],
            [
                ['Enabled', '✓ Yes'],
                ['Strategy', $config['strategy']],
                ['Percentage', $config['percentage'] ?? '-'],
                ['Start Date', $config['start_date'] ?? '-'],
                ['End Date', $config['end_date'] ?? '-'],
                ['Whitelist', !empty($config['whitelist']) ? implode(', ', $config['whitelist']) : '-'],
            ]
        );

        return Command::SUCCESS;
    }
}
