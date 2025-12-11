<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Less than or equals operator (<=).
 *
 * Example:
 * ```php
 * // age <= 65
 * $operator->evaluate(65, 65); // true
 * $operator->evaluate(55, 65); // true
 * $operator->evaluate(75, 65); // false
 * ```
 */
class LessThanOrEqualsOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            return false;
        }

        return $actual <= $expected;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::LESS_THAN_OR_EQUALS;
    }
}
