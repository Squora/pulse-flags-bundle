<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Segment;

/**
 * Repository for managing user segments.
 *
 * Provides centralized access to segments defined in configuration.
 * Segments can be reused across multiple feature flags.
 */
class SegmentRepository
{
    /** @var array<string, SegmentInterface> */
    private array $segments = [];

    /**
     * Register a segment.
     *
     * @param SegmentInterface $segment The segment to register
     * @return void
     */
    public function add(SegmentInterface $segment): void
    {
        $this->segments[$segment->getName()] = $segment;
    }

    /**
     * Get a segment by name.
     *
     * @param string $name The segment name
     * @return SegmentInterface|null The segment or null if not found
     */
    public function get(string $name): ?SegmentInterface
    {
        return $this->segments[$name] ?? null;
    }

    /**
     * Check if a segment exists.
     *
     * @param string $name The segment name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->segments[$name]);
    }

    /**
     * Get all registered segments.
     *
     * @return array<string, SegmentInterface>
     */
    public function all(): array
    {
        return $this->segments;
    }

    /**
     * Get segment names.
     *
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return array_keys($this->segments);
    }

    /**
     * Remove a segment.
     *
     * @param string $name The segment name
     * @return void
     */
    public function remove(string $name): void
    {
        unset($this->segments[$name]);
    }

    /**
     * Clear all segments.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->segments = [];
    }

    /**
     * Load segments from configuration array.
     *
     * @param array<string, array<string, mixed>> $config Segments configuration
     * @return void
     */
    public function loadFromConfig(array $config): void
    {
        foreach ($config as $name => $segmentConfig) {
            $type = $segmentConfig['type'] ?? 'static';

            $segment = match ($type) {
                'static' => new StaticSegment(
                    $name,
                    $segmentConfig['user_ids'] ?? []
                ),
                'dynamic' => new DynamicSegment(
                    $name,
                    $segmentConfig['condition'] ?? '',
                    $segmentConfig['value'] ?? null
                ),
                default => null,
            };

            if ($segment !== null) {
                $this->add($segment);
            }
        }
    }
}
