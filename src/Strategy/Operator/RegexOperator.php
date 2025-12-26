<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Regular expression match operator.
 *
 * @example phone_number regex '/^\+1/'
 * $operator->evaluate('+1-555-1234', '/^\+1/'); // true
 * $operator->evaluate('+44-555-1234', '/^\+1/'); // false
 */
class RegexOperator implements OperatorInterface
{
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        // Suppress warnings for invalid regex patterns
        return @preg_match($expected, $actual) === 1;
    }

    public function getOperator(): AttributeOperator
    {
        return AttributeOperator::REGEX;
    }
}
