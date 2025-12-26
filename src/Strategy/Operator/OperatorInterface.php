<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Operator;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Interface for custom attribute comparison operators.
 *
 * Each operator implements specific comparison logic used
 * in the custom attribute strategy.
 */
interface OperatorInterface
{
    /**
     * Evaluate the operator against actual and expected values.
     *
     * @param mixed $actual The actual value from context
     * @param mixed $expected The expected value from configuration
     * @return bool True if the comparison matches
     */
    public function evaluate(mixed $actual, mixed $expected): bool;

    /**
     * Get the operator type this class handles.
     *
     * @return AttributeOperator
     */
    public function getOperator(): AttributeOperator;
}
