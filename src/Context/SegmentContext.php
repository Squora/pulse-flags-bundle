<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Context;

/**
 * Context for segment-based strategy (SegmentStrategy).
 *
 * Holds user_id (required) and additional attributes for dynamic segment matching.
 * Dynamic segments can match on any context attribute (email, country, etc.).
 */
final class SegmentContext implements ContextInterface
{
    /**
     * @param string $userId User identifier (required)
     * @param array<string, mixed> $attributes Additional attributes for dynamic segments
     */
    public function __construct(
        private readonly string $userId,
        private readonly array $attributes = []
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function toArray(): array
    {
        return array_merge(
            ['user_id' => $this->userId],
            $this->attributes
        );
    }
}
