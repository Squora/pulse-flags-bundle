<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;

/**
 * Custom attribute-based activation strategy for feature flags.
 *
 * Enables features based on flexible rule-based conditions using any context attributes.
 * Similar to LaunchDarkly's custom rules and Unleash's flexible rollout strategies.
 *
 * Benefits:
 * - Target users by any custom attribute without code changes
 * - Support complex conditions with multiple operators
 * - Combine multiple rules with AND logic
 * - No need to predefine segments for simple conditions
 *
 * Available operators:
 * - equals, not_equals: Exact comparison
 * - in, not_in: Array membership
 * - greater_than, less_than, greater_than_or_equals, less_than_or_equals: Numeric comparison
 * - contains, not_contains: Substring matching
 * - starts_with, ends_with: String prefix/suffix
 * - regex: Regular expression matching
 *
 * Example configuration (single rule):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'custom_attribute',
 *     'rules' => [
 *         [
 *             'attribute' => 'subscription_tier',
 *             'operator' => 'equals',
 *             'value' => 'premium',
 *         ],
 *     ],
 * ]
 * ```
 *
 * Example configuration (multiple rules with AND logic):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'custom_attribute',
 *     'rules' => [
 *         [
 *             'attribute' => 'subscription_tier',
 *             'operator' => 'in',
 *             'values' => ['premium', 'enterprise'],
 *         ],
 *         [
 *             'attribute' => 'account_age_days',
 *             'operator' => 'greater_than',
 *             'value' => 30,
 *         ],
 *         [
 *             'attribute' => 'email',
 *             'operator' => 'not_contains',
 *             'value' => '@competitor.com',
 *         ],
 *     ],
 * ]
 * ```
 *
 * Example configuration (regional feature):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'custom_attribute',
 *     'rules' => [
 *         [
 *             'attribute' => 'country',
 *             'operator' => 'in',
 *             'values' => ['US', 'CA', 'GB'],
 *         ],
 *     ],
 * ]
 * ```
 *
 * Context requirements:
 * - Context must contain the attributes referenced in rules
 * - Missing attributes cause rule to fail (return false)
 */
class CustomAttributeStrategy implements StrategyInterface
{
    /** @var array<string, OperatorInterface> */
    private array $operators = [];

    /**
     * @param iterable<OperatorInterface> $operators Available operators
     */
    public function __construct(iterable $operators)
    {
        foreach ($operators as $operator) {
            $this->operators[$operator->getOperator()->value] = $operator;
        }
    }

    /**
     * Determines if the feature should be enabled based on custom attribute rules.
     *
     * Uses AND logic: ALL rules must pass for the feature to be enabled.
     *
     * @param array<string, mixed> $config Configuration with 'rules' array
     * @param array<string, mixed> $context Runtime context with user attributes
     * @return bool True if all rules match
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $rules = $config['rules'] ?? [];

        if (empty($rules)) {
            return false;
        }

        // AND logic: all rules must pass
        foreach ($rules as $index => $rule) {
            if (!$this->evaluateRule($rule, $context, $index)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single rule.
     *
     * @param array<string, mixed> $rule Rule configuration
     * @param array<string, mixed> $context Runtime context
     * @param int $index Rule index for logging
     * @return bool True if rule passes
     */
    private function evaluateRule(array $rule, array $context, int $index): bool
    {
        $attribute = $rule['attribute'] ?? null;
        $operatorName = $rule['operator'] ?? null;

        if (!$attribute || !$operatorName) {
            return false;
        }

        // Check if attribute exists in context
        if (!array_key_exists($attribute, $context)) {
            return false;
        }

        $actualValue = $context[$attribute];

        // Get operator
        $operator = $this->operators[$operatorName] ?? null;

        if ($operator === null) {
            return false;
        }

        // Get expected value (support both 'value' and 'values' for array operators)
        $expectedValue = $rule['values'] ?? $rule['value'] ?? null;

        // Evaluate rule
        try {
            return $operator->evaluate($actualValue, $expectedValue);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'custom_attribute'
     */
    public function getName(): string
    {
        return FlagStrategy::CUSTOM_ATTRIBUTE->value;
    }
}
