<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Service;

interface FeatureFlagInterface
{
    /**
     * Check if a feature flag is enabled
     *
     * @param string $name Flag name
     * @param array<string, mixed> $context Context for strategy evaluation (user_id, session_id, current_date, etc.)
     * @return bool True if a flag is enabled
     */
    public function isEnabled(string $name, array $context = []): bool;

    /**
     * Get flag configuration
     *
     * @param string $name Flag name
     * @return array<string, mixed>|null Flag configuration or null if not found
     */
    public function getConfig(string $name): ?array;

    /**
     * Check if a flag exists
     *
     * @param string $name Flag name
     * @return bool True if a flag exists
     */
    public function exists(string $name): bool;

    /**
     * Get paginated flags
     *
     * @param int $page Page number (1-indexed, defaults to 1)
     * @param int $limit Items per page (max 100, defaults to 50)
     * @return array{flags: array<string, array<string, mixed>>, pagination: array{total: int, page: int, pages: int, limit: int}}
     */
    public function paginate(int $page = 1, int $limit = 50): array;
}
