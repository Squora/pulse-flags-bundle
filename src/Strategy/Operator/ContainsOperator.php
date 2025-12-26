<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * String contains substring operator.
 *
 * @example email contains '@company.com'
 * $operator->evaluate('john@company.com', '@company.com'); // true
 * $operator->evaluate('john@gmail.com', '@company.com'); // false
 */
class ContainsOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        return str_contains($actual, $expected);
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::CONTAINS;
    }
}
