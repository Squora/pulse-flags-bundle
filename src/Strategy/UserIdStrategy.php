<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;

/**
 * User ID-based activation strategy for feature flags.
 *
 * Enables features based on whitelist or blacklist of specific user IDs.
 * Useful for targeting specific users for beta testing, internal testing,
 * or excluding problematic users from new features.
 *
 * Example whitelist configuration (only specific users):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'user_id',
 *     'whitelist' => [123, 456, 789], // Only these user IDs
 * ]
 * ```
 *
 * Example blacklist configuration (all except specific users):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'user_id',
 *     'blacklist' => [999, 888],  // All users except these
 * ]
 * ```
 *
 * Context requirements:
 * - 'user_id' (required): The user identifier to check against lists
 *
 * Behavior:
 * - If whitelist is provided: Only users in the list are enabled
 * - If blacklist is provided: All users except those in the list are enabled
 * - If neither is provided: All users are enabled
 * - If no user_id in context: Feature is disabled
 * - Whitelist takes precedence over blacklist
 */
class UserIdStrategy implements StrategyInterface
{
    /**
     * Determines if the feature should be enabled based on user ID lists.
     *
     * Checks the user_id from context against whitelist or blacklist.
     * Uses O(1) hash lookup for optimal performance with large lists.
     *
     * @param array<string, mixed> $config Configuration with 'whitelist' and/or 'blacklist' keys
     * @param array<string, mixed> $context Runtime context with 'user_id' key
     * @return bool True if the user is allowed access based on the lists
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $userId = $context['user_id'] ?? null;

        if ($userId === null) {
            return false;
        }

        // Check whitelist - O(1) hash lookup using array_flip
        if (!empty($config['whitelist'])) {
            $whitelist = array_flip($config['whitelist']);
            return isset($whitelist[$userId]);
        }

        // Check blacklist - O(1) hash lookup using array_flip
        if (!empty($config['blacklist'])) {
            $blacklist = array_flip($config['blacklist']);
            return !isset($blacklist[$userId]);
        }

        return true;
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'user_id'
     */
    public function getName(): string
    {
        return FlagStrategy::USER_ID->value;
    }
}
