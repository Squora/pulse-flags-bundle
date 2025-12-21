<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Inequality operator (!==).
 *
 * @example subscription_tier !== 'free'
 * $operator->evaluate('premium', 'free'); // true
 * $operator->evaluate('free', 'free'); // false
 */
class NotEqualsOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        return $actual !== $expected;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::NOT_EQUALS;
    }
}
