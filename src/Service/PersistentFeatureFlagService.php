<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pulse\FlagsBundle\Storage\StorageInterface;
use Pulse\FlagsBundle\Strategy\StrategyInterface;

/**
 * Service for managing persistent (runtime-mutable) feature flags
 *
 * Persistent flags are stored in a database and can be modified
 * at runtime via API, admin panel, or CLI commands.
 */
class PersistentFeatureFlagService implements FeatureFlagInterface
{
    /** @var array<string, StrategyInterface> */
    private array $strategies = [];

    public function __construct(
        private readonly StorageInterface $storage,
        private ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function addStrategy(StrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    public function isEnabled(string $name, array $context = []): bool
    {
        $config = $this->storage->get($name);

        if (null === $config) {
            $this->logger?->debug('Persistent feature flag not found', [
                'flag' => $name,
            ]);

            return false;
        }

        if (!($config['enabled'] ?? false)) {
            return false;
        }

        $strategyName = $config['strategy'] ?? 'simple';

        if ($strategyName === 'simple') {
            return true;
        }

        if (!isset($this->strategies[$strategyName])) {
            $this->logger?->warning('Strategy not found for persistent flag', [
                'flag' => $name,
                'strategy' => $strategyName,
            ]);

            return false;
        }

        $strategy = $this->strategies[$strategyName];
        $result = $strategy->isEnabled($config, $context);

        $this->logger?->debug('Persistent feature flag evaluated', [
            'flag' => $name,
            'strategy' => $strategyName,
            'result' => $result,
            'context' => $context,
        ]);

        return $result;
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

        $this->logger?->info('Persistent feature flag configured', [
            'flag' => $name,
            'config' => $config,
        ]);
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

        $config = array_merge($config, $options, ['enabled' => true]);
        $this->storage->set($name, $config);

        $this->logger?->info('Persistent feature flag enabled', [
            'flag' => $name,
            'options' => $options,
        ]);
    }

    /**
     * Disable a feature flag
     *
     * @param string $name Flag name
     */
    public function disable(string $name): void
    {
        $config = $this->storage->get($name) ?? [];

        $config['enabled'] = false;
        $this->storage->set($name, $config);

        $this->logger?->info('Persistent feature flag disabled', ['flag' => $name]);
    }

    /**
     * Remove a feature flag
     *
     * @param string $name Flag name
     */
    public function remove(string $name): void
    {
        $this->storage->remove($name);
        $this->logger?->info('Persistent feature flag removed', ['flag' => $name]);
    }

    public function getConfig(string $name): ?array
    {
        return $this->storage->get($name);
    }

    public function exists(string $name): bool
    {
        return $this->storage->has($name);
    }

    public function paginate(int $page = 1, int $limit = 50): array
    {
        return $this->storage->paginate($page, $limit);
    }
}
