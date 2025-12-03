<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Strategy;

/**
 * Interface for feature flag activation strategies.
 *
 * Strategies determine whether a feature flag should be enabled based on
 * the flag configuration and runtime context (user ID, session, date, etc.).
 */
interface StrategyInterface
{
    /**
     * Determines if a feature should be enabled based on the configuration and context.
     *
     * @param array<string, mixed> $config The flag configuration containing strategy-specific parameters
     * @param array<string, mixed> $context Runtime context (user_id, session_id, current_date, etc.)
     * @return bool True if the feature should be enabled, false otherwise
     */
    public function isEnabled(array $config, array $context = []): bool;

    /**
     * Returns the unique identifier for this strategy.
     *
     * @return string The strategy name (e.g., 'percentage', 'user_id', 'date_range')
     */
    public function getName(): string;
}
