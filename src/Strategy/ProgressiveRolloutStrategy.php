<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;
use Psr\Log\LoggerInterface;

/**
 * Progressive rollout strategy with automated percentage increases over time.
 *
 * Enables gradual feature rollout by automatically increasing the percentage
 * of enabled users based on a predefined schedule. Similar to LaunchDarkly's
 * progressive delivery and Unleash's gradual rollout patterns.
 *
 * Benefits:
 * - Automate gradual rollouts without manual intervention
 * - Reduce risk by slowly increasing user exposure
 * - Monitor metrics at each stage before expanding
 * - Easy rollback by adjusting schedule
 *
 * Example configuration (simple schedule):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'progressive_rollout',
 *     'schedule' => [
 *         ['percentage' => 1, 'start_date' => '2025-01-01'],
 *         ['percentage' => 5, 'start_date' => '2025-01-03'],
 *         ['percentage' => 25, 'start_date' => '2025-01-07'],
 *         ['percentage' => 50, 'start_date' => '2025-01-10'],
 *         ['percentage' => 100, 'start_date' => '2025-01-15'],
 *     ],
 *     'stickiness' => 'user_id',
 * ]
 * ```
 *
 * Example configuration (with timezone):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'progressive_rollout',
 *     'schedule' => [
 *         ['percentage' => 0.1, 'start_date' => '2025-01-01 00:00:00'],
 *         ['percentage' => 1, 'start_date' => '2025-01-01 12:00:00'],
 *         ['percentage' => 5, 'start_date' => '2025-01-02 00:00:00'],
 *         ['percentage' => 100, 'start_date' => '2025-01-07 00:00:00'],
 *     ],
 *     'stickiness' => ['user_id', 'session_id'],
 *     'timezone' => 'America/New_York',
 * ]
 * ```
 *
 * Schedule requirements:
 * - Must be sorted by start_date (ascending)
 * - Each stage must have 'percentage' and 'start_date'
 * - Percentage can be decimal (0.1% = 0.1)
 * - Dates can include time for precise control
 *
 * Context requirements:
 * - Same as PercentageStrategy (user_id, session_id, or custom stickiness attribute)
 */
class ProgressiveRolloutStrategy implements StrategyInterface
{
    private PercentageStrategy $percentageStrategy;
    private ?LoggerInterface $logger;

    /**
     * @param PercentageStrategy $percentageStrategy Underlying percentage strategy
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(PercentageStrategy $percentageStrategy, ?LoggerInterface $logger = null)
    {
        $this->percentageStrategy = $percentageStrategy;
        $this->logger = $logger;
    }

    /**
     * Determines if the feature should be enabled based on progressive rollout schedule.
     *
     * Calculates current percentage from schedule and delegates to PercentageStrategy.
     *
     * @param array<string, mixed> $config Configuration with 'schedule' array
     * @param array<string, mixed> $context Runtime context with user identifier
     * @return bool True if user is in current percentage bucket
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $schedule = $config['schedule'] ?? [];

        if (empty($schedule)) {
            $this->logger?->warning('Progressive rollout strategy has no schedule configured');
            return false;
        }

        // Get current percentage from schedule
        $currentPercentage = $this->getCurrentPercentage($schedule, $config);

        if ($currentPercentage === null) {
            $this->logger?->debug('Progressive rollout not started yet');
            return false;
        }

        $this->logger?->debug('Progressive rollout current stage', [
            'current_percentage' => $currentPercentage,
            'schedule_stages' => count($schedule),
        ]);

        // Create config for percentage strategy
        $percentageConfig = [
            'percentage' => $currentPercentage,
        ];

        // Pass through stickiness configuration
        if (isset($config['stickiness'])) {
            $percentageConfig['stickiness'] = $config['stickiness'];
        }

        // Pass through hash algorithm configuration
        if (isset($config['hash_algorithm'])) {
            $percentageConfig['hash_algorithm'] = $config['hash_algorithm'];
        }

        // Pass through hash seed configuration
        if (isset($config['hash_seed'])) {
            $percentageConfig['hash_seed'] = $config['hash_seed'];
        }

        // Delegate to percentage strategy
        return $this->percentageStrategy->isEnabled($percentageConfig, $context);
    }

    /**
     * Calculate current percentage based on schedule and current time.
     *
     * @param array<int, array<string, mixed>> $schedule Rollout schedule
     * @param array<string, mixed> $config Full configuration
     * @return float|null Current percentage or null if rollout hasn't started
     */
    private function getCurrentPercentage(array $schedule, array $config): ?float
    {
        // Get current time with optional timezone support
        $timezone = null;
        if (!empty($config['timezone'])) {
            try {
                $timezone = new \DateTimeZone($config['timezone']);
            } catch (\Exception $e) {
                $this->logger?->error('Invalid timezone in progressive rollout', [
                    'timezone' => $config['timezone'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $now = new \DateTimeImmutable('now', $timezone);

        // Find the active stage
        $currentPercentage = null;

        foreach ($schedule as $index => $stage) {
            if (!isset($stage['percentage']) || !isset($stage['start_date'])) {
                $this->logger?->warning('Progressive rollout stage missing required fields', [
                    'stage_index' => $index,
                    'stage' => $stage,
                ]);
                continue;
            }

            try {
                $startDate = new \DateTimeImmutable($stage['start_date'], $timezone);

                // If this stage has started, update current percentage
                if ($now >= $startDate) {
                    $currentPercentage = (float) $stage['percentage'];

                    $this->logger?->debug('Progressive rollout stage active', [
                        'stage_index' => $index,
                        'percentage' => $currentPercentage,
                        'start_date' => $stage['start_date'],
                    ]);
                } else {
                    // Stages are sorted, so we can stop here
                    break;
                }
            } catch (\Exception $e) {
                $this->logger?->error('Invalid date in progressive rollout schedule', [
                    'stage_index' => $index,
                    'start_date' => $stage['start_date'] ?? 'missing',
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $currentPercentage;
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'progressive_rollout'
     */
    public function getName(): string
    {
        return FlagStrategy::PROGRESSIVE_ROLLOUT->value;
    }
}
