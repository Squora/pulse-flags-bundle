<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Less than operator (<).
 *
 * Example:
 * ```php
 * // login_count < 5
 * $operator->evaluate(3, 5); // true
 * $operator->evaluate(7, 5); // false
 * ```
 */
class LessThanOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            return false;
        }

        return $actual < $expected;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::LESS_THAN;
    }
}
