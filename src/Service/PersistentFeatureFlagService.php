<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Service;

use Pulse\Flags\Core\Constants\Pagination;
use Pulse\Flags\Core\Enum\FlagStatus;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Storage\StorageInterface;

/**
 * Service for managing persistent (runtime-mutable) feature flags
 *
 * Persistent flags are stored in a database and can be modified
 * at runtime via API, admin panel, or CLI commands.
 */
class PersistentFeatureFlagService extends AbstractFeatureFlagServiceService
{
    public function __construct(
        private readonly StorageInterface $storage
    ) {
    }

    /**
     * Configure a feature flag
     *
     * @param string $name Flag name
     * @param array<string, mixed> $config Flag configuration
     */
    public function configure(string $name, array $config): void
    {
        $this->storage->set($name, $config);
    }

    /**
     * Enable a feature flag with optional configuration
     *
     * @param string $name Flag name
     * @param array<string, mixed> $options Optional configuration (strategy, percentage, etc.)
     */
    public function enable(string $name, array $options = []): void
    {
        $config = $this->storage->get($name) ?? [];

        // Ensure strategy is always set (use simple as default)
        if (!isset($config['strategy']) && !isset($options['strategy'])) {
            $options['strategy'] = FlagStrategy::SIMPLE->value;
        }

        $config = array_merge($config, $options, ['enabled' => FlagStatus::ENABLED->toBool()]);
        $this->storage->set($name, $config);
    }

    /**
     * Disable a feature flag
     *
     * @param string $name Flag name
     */
    public function disable(string $name): void
    {
        $config = $this->storage->get($name) ?? [];

        $config['enabled'] = FlagStatus::DISABLED->toBool();
        $this->storage->set($name, $config);
    }

    /**
     * Remove a feature flag
     *
     * @param string $name Flag name
     */
    public function remove(string $name): void
    {
        $this->storage->remove($name);
    }

    public function getConfig(string $name): ?array
    {
        return $this->storage->get($name);
    }

    public function exists(string $name): bool
    {
        return $this->storage->has($name);
    }

    public function all(): array
    {
        return $this->storage->all();
    }

    public function paginate(int $page = Pagination::DEFAULT_PAGE, int $limit = Pagination::DEFAULT_LIMIT): array
    {
        return $this->storage->paginate($page, $limit);
    }
}
