<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Service;

use Pulse\Flags\Core\Constants\Pagination;

/**
 * Service for managing permanent (read-only) feature flags
 *
 * Permanent flags are loaded from configuration files (YAML, INI, PHP)
 * and cannot be modified at runtime.
 */
class PermanentFeatureFlagService extends AbstractFeatureFlagServiceService
{
    /**
     * @param array<string, array<string, mixed>> $permanentFlags Permanent flags loaded from config files
     */
    public function __construct(
        private readonly array $permanentFlags
    ) {
    }

    public function getConfig(string $name): ?array
    {
        return $this->permanentFlags[$name] ?? null;
    }

    public function exists(string $name): bool
    {
        return isset($this->permanentFlags[$name]);
    }

    public function all(): array
    {
        return $this->permanentFlags;
    }

    public function paginate(int $page = Pagination::DEFAULT_PAGE, int $limit = Pagination::DEFAULT_LIMIT): array
    {
        $limit = min($limit, Pagination::MAX_LIMIT);
        $page = max($page, Pagination::MIN_PAGE);
        $offset = ($page - Pagination::MIN_PAGE) * $limit;

        $total = count($this->permanentFlags);
        $flagsSlice = array_slice($this->permanentFlags, $offset, $limit, true);

        return [
            'flags' => $flagsSlice,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pages' => (int) ceil($total / $limit),
                'limit' => $limit,
            ],
        ];
    }
}
