<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * String does not contain substring operator.
 *
 * @example email not_contains '@competitor.com'
 * $operator->evaluate('john@company.com', '@competitor.com'); // true
 * $operator->evaluate('john@competitor.com', '@competitor.com'); // false
 */
class NotContainsOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return true;
        }

        return !str_contains($actual, $expected);
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::NOT_CONTAINS;
    }
}
