<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;
use Psr\Log\LoggerInterface;

/**
 * Composite activation strategy for feature flags.
 *
 * Combines multiple strategies using AND/OR logic, enabling complex
 * activation rules like "10% of users AND only on weekends" or
 * "beta users OR internal team members".
 *
 * Example AND configuration (must satisfy all conditions):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'composite',
 *     'operator' => 'AND',
 *     'strategies' => [
 *         ['type' => 'percentage', 'percentage' => 50],
 *         ['type' => 'date_range', 'start_date' => '2025-01-01'],
 *     ],
 * ]
 * ```
 *
 * Example OR configuration (satisfy any condition):
 * ```php
 * [
 *     'enabled' => true,
 *     'strategy' => 'composite',
 *     'operator' => 'OR',
 *     'strategies' => [
 *         ['type' => 'user_id', 'whitelist' => [1, 2, 3]],
 *         ['type' => 'percentage', 'percentage' => 10],
 *     ],
 * ]
 * ```
 *
 * Behavior:
 * - AND operator: All strategies must return true (short-circuits on first false)
 * - OR operator: At least one strategy must return true (short-circuits on first true)
 * - Empty strategies array always returns true
 * - Unknown strategy types are logged as errors and skipped
 * - Missing type field is logged as warning and skipped
 * - Default operator is AND
 */
class CompositeStrategy implements StrategyInterface
{
    /**
     * Registry of available strategies for composition.
     *
     * @var array<string, StrategyInterface>
     */
    private array $strategies = [];

    /**
     * Optional logger for debugging composite strategy evaluation.
     *
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger = null;

    /**
     * Constructor for dependency injection.
     *
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Adds a strategy to the registry for use in composite evaluation.
     *
     * @param StrategyInterface $strategy The strategy to add
     * @return void
     */
    public function addStrategy(StrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    /**
     * Determines if the feature should be enabled based on composite logic.
     *
     * Evaluates multiple strategies using AND/OR logic. For AND operations,
     * returns false immediately when any strategy fails. For OR operations,
     * returns true immediately when any strategy succeeds.
     *
     * @param array<string, mixed> $config Configuration with 'strategies' array and 'operator' (AND/OR)
     * @param array<string, mixed> $context Runtime context passed to each strategy
     * @return bool True if the composite logic evaluates to true
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        $strategies = $config['strategies'] ?? [];

        if (empty($strategies)) {
            return true;
        }

        $operator = $config['operator'] ?? 'AND';

        foreach ($strategies as $index => $strategyConfig) {
            $strategyName = $strategyConfig['type'] ?? null;

            if (!$strategyName) {
                $this->logger?->warning('[PulseFlags] Composite strategy missing "type" field', [
                    'index' => $index,
                    'config' => $strategyConfig,
                ]);
                continue;
            }

            if (!isset($this->strategies[$strategyName])) {
                $this->logger?->error('[PulseFlags] Unknown strategy in composite configuration', [
                    'strategy' => $strategyName,
                    'index' => $index,
                    'available_strategies' => array_keys($this->strategies),
                ]);
                continue;
            }

            $strategy = $this->strategies[$strategyName];
            $result = $strategy->isEnabled($strategyConfig, $context);

            if ($operator === 'OR' && $result) {
                return true;
            }

            if ($operator === 'AND' && !$result) {
                return false;
            }
        }

        return $operator === 'AND';
    }

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name 'composite'
     */
    public function getName(): string
    {
        return FlagStrategy::COMPOSITE->value;
    }
}
