<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * NOT IN operator - value does not exist in array.
 *
 * @example country NOT IN ['CN', 'RU']
 * $operator->evaluate('US', ['CN', 'RU']); // true
 * $operator->evaluate('CN', ['CN', 'RU']); // false
 */
class NotInOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            return true;
        }

        return !in_array($actual, $expected, true);
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::NOT_IN;
    }
}
