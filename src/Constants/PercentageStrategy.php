<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Constants;

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
     * Users are distributed into 100 buckets (0-99) using CRC32.
     */
    public const HASH_BUCKETS = 100;

    private function __construct()
    {
        // Prevent instantiation
    }
}
