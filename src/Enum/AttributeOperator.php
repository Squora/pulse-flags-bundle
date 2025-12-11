<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Enum;

/**
 * Operators for custom attribute matching.
 *
 * Used in custom attribute strategies to define comparison logic
 * between context attributes and expected values.
 */
enum AttributeOperator: string
{
    /**
     * Exact equality (===).
     * Example: subscription_tier equals 'premium'
     */
    case EQUALS = 'equals';

    /**
     * Inequality (!==).
     * Example: subscription_tier not_equals 'free'
     */
    case NOT_EQUALS = 'not_equals';

    /**
     * Value in array (in_array).
     * Example: country in ['US', 'CA', 'GB']
     */
    case IN = 'in';

    /**
     * Value not in array (!in_array).
     * Example: country not_in ['CN', 'RU']
     */
    case NOT_IN = 'not_in';

    /**
     * Greater than comparison (>).
     * Example: account_age_days greater_than 30
     */
    case GREATER_THAN = 'greater_than';

    /**
     * Less than comparison (<).
     * Example: login_count less_than 5
     */
    case LESS_THAN = 'less_than';

    /**
     * Greater than or equals comparison (>=).
     * Example: subscription_price greater_than_or_equals 99.99
     */
    case GREATER_THAN_OR_EQUALS = 'greater_than_or_equals';

    /**
     * Less than or equals comparison (<=).
     * Example: age less_than_or_equals 65
     */
    case LESS_THAN_OR_EQUALS = 'less_than_or_equals';

    /**
     * String contains substring (str_contains).
     * Example: email contains '@company.com'
     */
    case CONTAINS = 'contains';

    /**
     * String does not contain substring.
     * Example: email not_contains '@competitor.com'
     */
    case NOT_CONTAINS = 'not_contains';

    /**
     * Regular expression match (preg_match).
     * Example: phone_number regex '/^\+1/'
     */
    case REGEX = 'regex';

    /**
     * Starts with prefix.
     * Example: user_agent starts_with 'Mozilla'
     */
    case STARTS_WITH = 'starts_with';

    /**
     * Ends with suffix.
     * Example: email ends_with '.edu'
     */
    case ENDS_WITH = 'ends_with';
}
