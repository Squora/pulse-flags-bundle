<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Composite context that combines multiple contexts.
 *
 * Used when you need to pass data for multiple strategies at once.
 */
final class CompositeContext implements ContextInterface
{
    private array $contexts = [];

    /**
     * @param ContextInterface ...$contexts
     */
    public function __construct(ContextInterface ...$contexts)
    {
        foreach ($contexts as $context) {
            $this->contexts[] = $context;
        }
    }

    /**
     * @return ContextInterface[]
     */
    public function getContexts(): array
    {
        return $this->contexts;
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->contexts as $context) {
            $result = array_merge($result, $context->toArray());
        }
        return $result;
    }
}
