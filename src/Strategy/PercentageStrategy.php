<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Constants\PercentageStrategy as PercentageConstants;
use Pulse\Flags\Core\Enum\FlagStrategy;

/**
 * Percentage-based rollout activation strategy for feature flags.
 *
 * Enables features for a specified percentage of users using consistent
 * hash-based bucketing. The same user ID will always get the same result,
 * ensuring stable A/B testing and gradual rollouts.
 *
 * The strategy uses CRC32 hashing to distribute users into 100 buckets (0-99).
 * Users are consistently assigned to the same bucket based on their user_id
 * or session_id, preventing flickering behavior.
 *
 * Example configuration:
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'percentage',
 *     'percentage' => 25,  // Enable for 25% of users
 * ]
 * ```
 *
 * Context requirements:
 * - 'user_id' (recommended): For consistent per-user bucketing
 * - 'session_id' (fallback): For anonymous users
 * - Returns false if neither is provided (fail-safe behavior)
 */
class PercentageStrategy implements StrategyInterface
{
    /**
     * Determines if the feature should be enabled based on percentage rollout.
     *
     * Uses consistent hash-based bucketing to ensure the same user always
     * gets the same result. Percentage of 100 or more always returns true,
     * percentage of 0 or less always returns false.
     *
     * IMPORTANT: Requires 'user_id' or 'session_id' in context for consistent bucketing.
     * Returns false if no identifier is provided (fail-safe behavior).
     *
     * @param array<string, mixed> $config Configuration array with 'percentage' key (0-100)
     * @param array<string, mixed> $context Runtime context with 'user_id' or 'session_id'
     * @return bool True if the user falls within the enabled percentage
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $percentage = $config['percentage'] ?? PercentageConstants::DEFAULT_PERCENTAGE;

        if ($percentage >= PercentageConstants::MAX_PERCENTAGE) {
            return true;
        }

        if ($percentage <= PercentageConstants::MIN_PERCENTAGE) {
            return false;
        }

        // Use user_id or session_id for consistent bucketing
        // CRITICAL FIX: Removed uniqid() fallback to ensure consistency
        $identifier = $context['user_id'] ?? $context['session_id'] ?? null;

        if ($identifier === null) {
            // Fail-safe: return false when no identifier provided
            // This ensures consistent behavior and prevents random bucketing
            return false;
        }

        // Calculate hash bucket (0-99)
        $bucket = crc32((string)$identifier) % PercentageConstants::HASH_BUCKETS;

        return $bucket < $percentage;
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'percentage'
     */
    public function getName(): string
    {
        return FlagStrategy::PERCENTAGE->value;
    }
}
