<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Storage;

/**
 * Interface for feature flag storage backends.
 *
 * Defines the contract for storing and retrieving feature flag configurations
 * across different storage mechanisms (database, YAML, PHP, etc.).
 */
interface StorageInterface
{
    /**
     * Retrieves the configuration for a specific feature flag.
     *
     * @param string $name The unique flag identifier
     * @return array<string, mixed>|null The flag configuration array or null if not found
     */
    public function get(string $name): ?array;

    /**
     * Stores or updates the configuration for a feature flag.
     *
     * @param string $name The unique flag identifier
     * @param array<string, mixed> $config The flag configuration to store
     * @return void
     */
    public function set(string $name, array $config): void;

    /**
     * Removes a feature flag from storage.
     *
     * @param string $name The unique flag identifier
     * @return void
     */
    public function remove(string $name): void;

    /**
     * Checks if a feature flag exists in storage.
     *
     * @param string $name The unique flag identifier
     * @return bool True if the flag exists, false otherwise
     */
    public function has(string $name): bool;

    /**
     * Retrieves all feature flags from storage.
     *
     * @return array<string, array<string, mixed>> Associative array of flag names to configurations
     */
    public function all(): array;

    /**
     * Retrieves paginated feature flags from storage.
     *
     * Returns flags and pagination metadata in a structured format.
     * Page numbers are 1-indexed. Limit is capped at 100.
     *
     * @param int $page Page number (1-indexed, defaults to 1)
     * @param int $limit Items per page (max 100, defaults to 50)
     * @return array{flags: array<string, array<string, mixed>>, pagination: array{total: int, page: int, pages: int, limit: int}}
     */
    public function paginate(int $page = 1, int $limit = 50): array;

    /**
     * Removes all feature flags from storage.
     *
     * @return void
     */
    public function clear(): void;
}
