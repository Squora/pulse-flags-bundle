<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy;

use Pulse\Flags\Core\Enum\FlagStrategy;

/**
 * Simple on/off strategy
 *
 * This is the default strategy that always returns true when a flag is enabled.
 * It provides basic on/off functionality without any additional conditions.
 */
class SimpleStrategy implements StrategyInterface
{
    /**
     * Always returns true (simple on/off toggle)
     *
     * @param array<string, mixed> $config Flag configuration (not used)
     * @param array<string, mixed> $context Evaluation context (not used)
     * @return bool Always true
     */
    public function isEnabled(array $config, array $context = []): bool
    {
        return true; // If we reached here, flag is enabled
    }

    public function getName(): string
    {
        return FlagStrategy::SIMPLE->value;
    }
}
