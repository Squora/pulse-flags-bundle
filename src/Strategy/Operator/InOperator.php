<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * IN operator - value exists in array.
 *
 * @example country IN ['US', 'CA', 'GB']
 * $operator->evaluate('US', ['US', 'CA', 'GB']); // true
 * $operator->evaluate('FR', ['US', 'CA', 'GB']); // false
 */
class InOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            return false;
        }

        return in_array($actual, $expected, true);
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::IN;
    }
}
