<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Context for custom attribute-based strategy (CustomAttributeStrategy).
 *
 * Holds arbitrary key-value pairs for flexible rule evaluation.
 */
final class CustomAttributeContext implements ContextInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly array $attributes = []
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
