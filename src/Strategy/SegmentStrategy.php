<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Segment\SegmentRepository;
use Psr\Log\LoggerInterface;

/**
 * Segment-based activation strategy for feature flags.
 *
 * Enables features based on user segment membership. Segments are reusable
 * groups of users defined once and referenced across multiple flags.
 *
 * Benefits:
 * - Define user groups once, reuse everywhere
 * - Eliminates repetitive whitelist configurations
 * - Easier management of user groups
 * - Supports both static (explicit user lists) and dynamic (rule-based) segments
 *
 * Example configuration (single segment):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'segment',
 *     'segments' => ['premium_users'],
 * ]
 * ```
 *
 * Example configuration (multiple segments with OR logic):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'segment',
 *     'segments' => ['premium_users', 'beta_testers', 'internal_team'],
 * ]
 * ```
 *
 * Segment definitions (in pulse_flags configuration):
 * ```yaml
 * pulse_flags:
 *     segments:
 *         premium_users:
 *             type: 'static'
 *             user_ids: ['1', '2', '3']
 *
 *         beta_testers:
 *             type: 'static'
 *             user_ids: ['10', '20', '30']
 *
 *         internal_team:
 *             type: 'dynamic'
 *             condition: 'email_domain'
 *             value: 'company.com'
 *
 *         us_users:
 *             type: 'dynamic'
 *             condition: 'country'
 *             value: 'US'
 * ```
 *
 * Context requirements:
 * - 'user_id' (required): The user identifier to check
 * - Additional context fields for dynamic segments (e.g., 'email', 'country')
 */
class SegmentStrategy implements StrategyInterface
{
    private SegmentRepository $segmentRepository;
    private ?LoggerInterface $logger;

    /**
     * @param SegmentRepository $segmentRepository Repository containing segment definitions
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(SegmentRepository $segmentRepository, ?LoggerInterface $logger = null)
    {
        $this->segmentRepository = $segmentRepository;
        $this->logger = $logger;
    }

    /**
     * Determines if the feature should be enabled based on segment membership.
     *
     * Uses OR logic: if user belongs to ANY of the specified segments, returns true.
     *
     * @param array<string, mixed> $config Configuration with 'segments' array
     * @param array<string, mixed> $context Runtime context with 'user_id' and other attributes
     * @return bool True if user is in any of the segments
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $userId = $context['user_id'] ?? null;

        if ($userId === null) {
            $this->logger?->warning('Segment strategy requires user_id in context');
            return false;
        }

        $segmentNames = $config['segments'] ?? [];

        if (empty($segmentNames)) {
            $this->logger?->warning('Segment strategy has no segments configured');
            return false;
        }

        // OR logic: user must be in at least one segment
        foreach ($segmentNames as $segmentName) {
            $segment = $this->segmentRepository->get($segmentName);

            if ($segment === null) {
                $this->logger?->error('Segment not found', [
                    'segment' => $segmentName,
                    'available_segments' => $this->segmentRepository->getNames(),
                ]);
                continue;
            }

            if ($segment->contains($userId, $context)) {
                $this->logger?->debug('User found in segment', [
                    'user_id' => $userId,
                    'segment' => $segmentName,
                    'segment_type' => $segment->getType(),
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'segment'
     */
    public function getName(): string
    {
        return FlagStrategy::SEGMENT->value;
    }
}
