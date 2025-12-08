<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Strategy;

use Pulse\FlagsBundle\Enum\FlagStrategy;

/**
 * Date range activation strategy for feature flags.
 *
 * Enables features only within a specified date range, useful for:
 * - Time-limited promotions and campaigns
 * - Scheduled feature releases
 * - Seasonal features
 * - Automatic feature sunset/deprecation
 *
 * Example configuration (active during December 2024):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'date_range',
 *     'start_date' => '2024-12-01',
 *     'end_date' => '2024-12-31',
 * ]
 * ```
 *
 * Example configuration (active from specific date onwards):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'date_range',
 *     'start_date' => '2025-01-01',  // Active from January 1st
 *     'end_date' => null,             // No end date
 * ]
 * ```
 *
 * Context:
 * - 'current_date' (optional): Custom date for testing, defaults to now
 *
 * Behavior:
 * - Dates are inclusive (both start and end dates are included)
 * - Start date is normalized to 00:00:00 (beginning of day)
 * - End date is normalized to 23:59:59 (end of day)
 * - If only start_date: Feature enabled from that date onwards
 * - If only end_date: Feature enabled until that date
 * - Invalid dates return false (fail-safe behavior)
 */
class DateRangeStrategy implements StrategyInterface
{
    /**
     * Determines if the feature should be enabled based on date range.
     *
     * Compares current date against configured start and end dates.
     * Handles date normalization and validates date formats.
     *
     * @param array<string, mixed> $config Configuration with 'start_date' and/or 'end_date' keys
     * @param array<string, mixed> $context Runtime context with optional 'current_date' key
     * @return bool True if current date is within the configured range
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        try {
            $currentDate = $context['current_date'] ?? new \DateTimeImmutable();

            if (!$currentDate instanceof \DateTimeInterface) {
                $currentDate = new \DateTimeImmutable($currentDate);
            }

            $startDate = null;
            $endDate = null;

            if (!empty($config['start_date'])) {
                $startDate = $config['start_date'] instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($config['start_date'])
                    : new \DateTimeImmutable($config['start_date']);
                // Normalize to start of day
                $startDate = $startDate->setTime(0, 0, 0);
            }

            if (!empty($config['end_date'])) {
                $endDate = $config['end_date'] instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($config['end_date'])
                    : new \DateTimeImmutable($config['end_date']);
                // Normalize to end of day
                $endDate = $endDate->setTime(23, 59, 59);
            }

            // Check if current date is within range (inclusive boundaries)
            if ($startDate && $currentDate < $startDate) {
                return false;
            }

            if ($endDate && $currentDate > $endDate) {
                return false;
            }

            return true;
        } catch (\Exception) {
            // Invalid date format - return false
            return false;
        }
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'date_range'
     */
    public function getName(): string
    {
        return FlagStrategy::DATE_RANGE->value;
    }
}
