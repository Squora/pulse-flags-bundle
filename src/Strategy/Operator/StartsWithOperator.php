<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * String starts with prefix operator.
 *
 * Example:
 * ```php
 * // user_agent starts_with 'Mozilla'
 * $operator->evaluate('Mozilla/5.0 ...', 'Mozilla'); // true
 * $operator->evaluate('Chrome/91.0 ...', 'Mozilla'); // false
 * ```
 */
class StartsWithOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        return str_starts_with($actual, $expected);
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::STARTS_WITH;
    }
}
