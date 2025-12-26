<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Greater than operator (>).
 *
 * @example account_age_days > 30
 * $operator->evaluate(45, 30); // true
 * $operator->evaluate(20, 30); // false
 */
class GreaterThanOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            return false;
        }

        return $actual > $expected;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::GREATER_THAN;
    }
}
