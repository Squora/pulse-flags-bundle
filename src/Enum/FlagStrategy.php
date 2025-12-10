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
}
