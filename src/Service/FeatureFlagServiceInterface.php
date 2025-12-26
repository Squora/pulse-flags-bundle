<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Service;

use Pulse\Flags\Core\Constants\Pagination;
use Pulse\Flags\Core\Context\ContextInterface;

interface FeatureFlagServiceInterface
{
    /**
     * Check if a feature flag is enabled
     *
     * @param string $name Flag name
     * @param ContextInterface $context Context for strategy evaluation
     * @return bool True if a flag is enabled
     */
    public function isEnabled(string $name, ContextInterface $context): bool;

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
     * Get all flags
     *
     * @return array<string, array<string, mixed>> Associative array of flag names to configurations
     */
    public function all(): array;

    /**
     * Get paginated flags
     *
     * @param int $page Page number (1-indexed)
     * @param int $limit Items per page (capped at MAX_LIMIT)
     * @return array{flags: array<string, array<string, mixed>>, pagination: array{total: int, page: int, pages: int, limit: int}}
     */
    public function paginate(int $page = Pagination::DEFAULT_PAGE, int $limit = Pagination::DEFAULT_LIMIT): array;
}
