<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\AttributeOperator;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\Operator\OperatorInterface;
use Psr\Log\LoggerInterface;

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

    private ?LoggerInterface $logger;

    /**
     * @param iterable<OperatorInterface> $operators Available operators
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(iterable $operators, ?LoggerInterface $logger = null)
    {
        foreach ($operators as $operator) {
            $this->operators[$operator->getOperator()->value] = $operator;
        }

        $this->logger = $logger;
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
            $this->logger?->warning('Custom attribute strategy has no rules configured');
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
            $this->logger?->error('Custom attribute rule missing required fields', [
                'rule_index' => $index,
                'attribute' => $attribute,
                'operator' => $operatorName,
            ]);
            return false;
        }

        // Check if attribute exists in context
        if (!array_key_exists($attribute, $context)) {
            $this->logger?->debug('Attribute not found in context', [
                'rule_index' => $index,
                'attribute' => $attribute,
                'available_attributes' => array_keys($context),
            ]);
            return false;
        }

        $actualValue = $context[$attribute];

        // Get operator
        $operator = $this->operators[$operatorName] ?? null;

        if ($operator === null) {
            $this->logger?->error('Unknown operator in custom attribute rule', [
                'rule_index' => $index,
                'operator' => $operatorName,
                'available_operators' => array_keys($this->operators),
            ]);
            return false;
        }

        // Get expected value (support both 'value' and 'values' for array operators)
        $expectedValue = $rule['values'] ?? $rule['value'] ?? null;

        // Evaluate rule
        try {
            $result = $operator->evaluate($actualValue, $expectedValue);

            $this->logger?->debug('Custom attribute rule evaluated', [
                'rule_index' => $index,
                'attribute' => $attribute,
                'operator' => $operatorName,
                'actual_value' => $actualValue,
                'expected_value' => $expectedValue,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('Error evaluating custom attribute rule', [
                'rule_index' => $index,
                'attribute' => $attribute,
                'operator' => $operatorName,
                'error' => $e->getMessage(),
            ]);
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
