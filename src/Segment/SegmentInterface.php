<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Segment;

/**
 * Interface for user segments in feature flag targeting.
 *
 * Segments allow grouping users by various criteria and reusing these groups
 * across multiple feature flags without duplicating user lists.
 *
 * Example use cases:
 * - Static segments: "Premium Users", "Beta Testers", "Internal Team"
 * - Dynamic segments: "Users from US", "Users with email @company.com"
 */
interface SegmentInterface
{
    /**
     * Check if a user belongs to this segment.
     *
     * @param string|int $userId The user identifier to check
     * @param array<string, mixed> $context Additional context for dynamic segments
     * @return bool True if user is in the segment
     */
    public function contains(string|int $userId, array $context = []): bool;

    /**
     * Get the segment name.
     *
     * @return string The unique segment identifier
     */
    public function getName(): string;

    /**
     * Get the segment type.
     *
     * @return string The segment type ('static', 'dynamic')
     */
    public function getType(): string;
}
