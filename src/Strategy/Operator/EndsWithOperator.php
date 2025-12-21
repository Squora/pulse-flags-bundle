<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * String ends with suffix operator.
 *
 * @example email ends_with '.edu'
 * $operator->evaluate('student@university.edu', '.edu'); // true
 * $operator->evaluate('student@gmail.com', '.edu'); // false
 */
class EndsWithOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        return str_ends_with($actual, $expected);
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::ENDS_WITH;
    }
}
