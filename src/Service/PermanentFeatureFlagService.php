<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pulse\FlagsBundle\Strategy\StrategyInterface;

/**
 * Service for managing permanent (read-only) feature flags
 *
 * Permanent flags are loaded from configuration files (YAML, INI, PHP)
 * and cannot be modified at runtime.
 */
class PermanentFeatureFlagService implements FeatureFlagInterface
{
    /** @var array<string, StrategyInterface> */
    private array $strategies = [];

    /**
     * @param array<string, array<string, mixed>> $permanentFlags Permanent flags loaded from config files
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        private readonly array $permanentFlags,
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
        $config = $this->getConfig($name);

        if (null === $config) {
            $this->logger?->debug('Permanent feature flag not found', [
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
            $this->logger?->warning('Strategy not found for permanent flag', [
                'flag' => $name,
                'strategy' => $strategyName,
            ]);

            return false;
        }

        $strategy = $this->strategies[$strategyName];
        $result = $strategy->isEnabled($config, $context);

        $this->logger?->debug('Permanent feature flag evaluated', [
            'flag' => $name,
            'strategy' => $strategyName,
            'result' => $result,
            'context' => $context,
        ]);

        return $result;
    }

    public function getConfig(string $name): ?array
    {
        return $this->permanentFlags[$name] ?? null;
    }

    public function exists(string $name): bool
    {
        return isset($this->permanentFlags[$name]);
    }

    public function paginate(int $page = 1, int $limit = 50): array
    {
        $limit = min($limit, 100);
        $page = max($page, 1);
        $offset = ($page - 1) * $limit;

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
