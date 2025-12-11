<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Constants;

/**
 * Percentage strategy configuration constants.
 *
 * Defines boundaries and behavior for percentage-based feature flag rollouts.
 */
final class PercentageStrategy
{
    /**
     * Minimum percentage value (fully disabled).
     */
    public const MIN_PERCENTAGE = 0;

    /**
     * Maximum percentage value (fully enabled).
     */
    public const MAX_PERCENTAGE = 100;

    /**
     * Default percentage when not specified.
     * 100 means the feature is fully enabled.
     */
    public const DEFAULT_PERCENTAGE = 100;

    /**
     * Number of buckets for consistent hashing.
     * 100,000 for higher precision rollouts (0.001% granularity).
     * This allows fine-grained rollouts like 0.125%, 0.5%, 1%, etc.
     */
    public const HASH_BUCKETS = 100000;

    /**
     * Number of decimal places supported for percentage values.
     * Supports percentages like 0.125, 1.5, 10.75.
     */
    public const PRECISION_DECIMALS = 3;

    private function __construct()
    {
        // Prevent instantiation
    }
}
