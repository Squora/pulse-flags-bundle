<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Greater than or equals operator (>=).
 *
 * @example subscription_price >= 99.99
 * $operator->evaluate(99.99, 99.99); // true
 * $operator->evaluate(149.99, 99.99); // true
 * $operator->evaluate(49.99, 99.99);  // false
 */
class GreaterThanOrEqualsOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            return false;
        }

        return $actual >= $expected;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::GREATER_THAN_OR_EQUALS;
    }
}
