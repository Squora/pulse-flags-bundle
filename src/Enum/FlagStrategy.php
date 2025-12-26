<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Enum;

/**
 * Feature flag activation strategies.
 *
 * Defines available strategies for controlling when and how
 * feature flags are enabled for users.
 */
enum FlagStrategy: string
{
    /**
     * Simple on/off toggle strategy.
     * Always returns true when flag is enabled, no additional conditions.
     */
    case SIMPLE = 'simple';

    /**
     * Percentage-based rollout strategy.
     * Enables features for a specified percentage of users (0-100)
     * using consistent hash-based bucketing.
     */
    case PERCENTAGE = 'percentage';

    /**
     * User ID whitelist/blacklist strategy.
     * Controls access based on specific user IDs.
     */
    case USER_ID = 'user_id';

    /**
     * Date range strategy.
     * Enables features only within a specified time period
     * (between start_date and end_date).
     */
    case DATE_RANGE = 'date_range';

    /**
     * Composite strategy.
     * Combines multiple strategies with logical operators (AND/OR).
     */
    case COMPOSITE = 'composite';

    /**
     * Segment strategy.
     * Enables features based on user segment membership.
     * Segments are reusable groups of users defined once and referenced across flags.
     */
    case SEGMENT = 'segment';

    /**
     * Custom attribute strategy.
     * Enables features based on flexible rule-based conditions using any context attributes.
     * Supports operators like equals, in, greater_than, contains, regex, etc.
     */
    case CUSTOM_ATTRIBUTE = 'custom_attribute';

    /**
     * Progressive rollout strategy.
     * Automates gradual feature rollout by increasing percentage over time
     * based on a predefined schedule.
     */
    case PROGRESSIVE_ROLLOUT = 'progressive_rollout';

    /**
     * IP address-based strategy.
     * Enables features based on user's IP address.
     * Supports both individual IPs and CIDR ranges.
     */
    case IP = 'ip';

    /**
     * Geographic location-based strategy.
     * Enables features based on user's country, region, or city.
     * Useful for regional rollouts and compliance requirements.
     */
    case GEO = 'geo';
}
