<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Exact equality operator (===).
 *
 * @example subscription_tier === 'premium'
 * $operator->evaluate('premium', 'premium'); // true
 * $operator->evaluate('free', 'premium'); // false
 */
class EqualsOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        return $actual === $expected;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::EQUALS;
    }
}
