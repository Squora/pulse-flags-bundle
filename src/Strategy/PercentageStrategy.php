<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Constants\PercentageStrategy as PercentageConstants;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Enum\HashAlgorithm;
use Pulse\Flags\Core\Strategy\Hash\HashCalculator;

/**
 * Percentage-based rollout activation strategy for feature flags.
 *
 * Enables features for a specified percentage of users using consistent
 * hash-based bucketing. The same user ID will always get the same result,
 * ensuring stable A/B testing and gradual rollouts.
 *
 * The strategy uses CRC32 hashing to distribute users into 100,000 buckets (0-99999).
 * Users are consistently assigned to the same bucket based on their user_id
 * or session_id, preventing flickering behavior.
 *
 * Supports decimal percentages up to 3 decimal places (0.001% precision):
 * - 0.125% = 125 users out of 100,000
 * - 0.5% = 500 users out of 100,000
 * - 1% = 1,000 users out of 100,000
 * - 25% = 25,000 users out of 100,000
 *
 * Example configuration (integer percentage):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'percentage',
 *     'percentage' => 25,  // Enable for 25% of users
 * ]
 * ```
 *
 * Example configuration (decimal percentage):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'percentage',
 *     'percentage' => 0.125,  // Enable for 0.125% of users (fine-grained rollout)
 * ]
 * ```
 *
 * Example configuration (custom hash algorithm):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'percentage',
 *     'percentage' => 50,
 *     'hash_algorithm' => 'murmur3',  // Options: crc32, md5, sha256, murmur3
 *     'hash_seed' => 'experiment-2025-q1',  // Seed for hash diversification
 * ]
 * ```
 *
 * Example configuration (custom stickiness for B2B):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'percentage',
 *     'percentage' => 25,
 *     'stickiness' => 'company_id',  // All users in same company get same experience
 * ]
 * ```
 *
 * Example configuration (stickiness with fallback chain):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'percentage',
 *     'percentage' => 10,
 *     'stickiness' => ['user_id', 'session_id', 'device_id'],  // Try in order
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
    private HashCalculator $hashCalculator;

    /**
     * Constructor with dependency injection.
     *
     * @param HashCalculator|null $hashCalculator Optional hash calculator (auto-created if not provided)
     */
    public function __construct(?HashCalculator $hashCalculator = null)
    {
        $this->hashCalculator = $hashCalculator ?? new HashCalculator();
    }

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
        // Support both integer and float percentage values
        $percentage = (float) ($config['percentage'] ?? PercentageConstants::DEFAULT_PERCENTAGE);

        if ($percentage >= PercentageConstants::MAX_PERCENTAGE) {
            return true;
        }

        if ($percentage <= PercentageConstants::MIN_PERCENTAGE) {
            return false;
        }

        // Get identifier using stickiness configuration
        $identifier = $this->getIdentifier($context, $config);

        if ($identifier === null) {
            // Fail-safe: return false when no identifier provided
            // This ensures consistent behavior and prevents random bucketing
            return false;
        }

        // Get hashing configuration
        $algorithmValue = $config['hash_algorithm'] ?? 'crc32';
        $algorithm = HashAlgorithm::tryFrom($algorithmValue) ?? HashAlgorithm::CRC32;
        $seed = $config['hash_seed'] ?? '';

        // Calculate hash bucket (0-99999) with higher precision
        $bucket = $this->hashCalculator->calculateBucket(
            (string)$identifier,
            $algorithm,
            $seed,
            PercentageConstants::HASH_BUCKETS
        );

        // Calculate threshold: percentage as fraction * total buckets
        // Example: 0.125% = (0.125 / 100) * 100000 = 125 buckets
        // Example: 25% = (25 / 100) * 100000 = 25000 buckets
        $threshold = ($percentage / 100) * PercentageConstants::HASH_BUCKETS;

        return $bucket < $threshold;
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

    /**
     * Get identifier from context using stickiness configuration.
     *
     * Stickiness determines which context attribute to use for hashing:
     * - String: Single attribute name (e.g., 'user_id', 'company_id')
     * - Array: Fallback chain (e.g., ['user_id', 'session_id', 'device_id'])
     * - Default: ['user_id', 'session_id']
     *
     * @param array<string, mixed> $context Runtime context with identifiers
     * @param array<string, mixed> $config Strategy configuration
     * @return string|null The identifier to use for hashing, or null if not found
     */
    private function getIdentifier(array $context, array $config): ?string
    {
        $stickiness = $config['stickiness'] ?? ['user_id', 'session_id'];

        // Support single string stickiness
        if (is_string($stickiness)) {
            $stickiness = [$stickiness];
        }

        // Try each stickiness attribute in order
        foreach ($stickiness as $key) {
            if (isset($context[$key]) && $context[$key] !== null && $context[$key] !== '') {
                return (string) $context[$key];
            }
        }

        return null;
    }
}
