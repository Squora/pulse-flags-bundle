<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Segment;

/**
 * Static segment with predefined list of user IDs.
 *
 * Use for segments where membership is explicitly defined:
 * - Beta testers
 * - Premium users
 * - Internal team members
 * - VIP customers
 *
 * Example configuration:
 * ```yaml
 * premium_users:
 *     type: 'static'
 *     user_ids: ['1', '2', '3', '123', '456']
 * ```
 */
class StaticSegment implements SegmentInterface
{
    private string $name;
    /** @var array<string|int, true> Hash set for O(1) lookup */
    private array $userIds;

    /**
     * @param string $name Segment identifier
     * @param array<int, string|int> $userIds List of user IDs in this segment
     */
    public function __construct(string $name, array $userIds)
    {
        $this->name = $name;
        // Convert to hash set for O(1) lookup performance
        $this->userIds = array_flip($userIds);
    }

    /**
     * {@inheritdoc}
     */
    public function contains(string|int $userId, array $context = []): bool
    {
        return isset($this->userIds[$userId]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'static';
    }

    /**
     * Get all user IDs in this segment.
     *
     * @return array<int, string|int>
     */
    public function getUserIds(): array
    {
        return array_keys($this->userIds);
    }

    /**
     * Get count of users in segment.
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->userIds);
    }
}
