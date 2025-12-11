<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pulse\Flags\Core\Constants\Pagination;
use Pulse\Flags\Core\Enum\FlagStrategy;
use Pulse\Flags\Core\Strategy\StrategyInterface;

/**
 * Abstract base service for feature flag management
 *
 * Provides common functionality for both permanent and persistent feature flags,
 * including strategy management and flag evaluation logic.
 */
abstract class AbstractFeatureFlagServiceService implements FeatureFlagServiceInterface
{
    /** @var array<string, StrategyInterface> */
    protected array $strategies = [];

    protected ?LoggerInterface $logger = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function addStrategy(StrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    public function isEnabled(string $name, array $context = []): bool
    {
        $startTime = microtime(true);

        $config = $this->getConfig($name);
        if (null === $config) {
            $this->logger?->info('Feature flag not found', [
                'flag' => $name,
                'flag_type' => $this->getLogPrefix(),
                'result' => false,
                'reason' => 'not_found',
            ]);

            return false;
        }

        $enabled = $config['enabled'] ?? false;
        if (!$enabled) {
            $this->logger?->info('Feature flag disabled', [
                'flag' => $name,
                'flag_type' => $this->getLogPrefix(),
                'result' => false,
                'reason' => 'disabled',
            ]);

            return false;
        }

        $strategyName = $config['strategy'] ?? FlagStrategy::SIMPLE->value;
        if (FlagStrategy::SIMPLE->value === $strategyName) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger?->info('Feature flag evaluated', [
                'flag' => $name,
                'flag_type' => $this->getLogPrefix(),
                'strategy' => $strategyName,
                'result' => true,
                'duration_ms' => round($duration, 2),
            ]);

            return true;
        }

        if (!isset($this->strategies[$strategyName])) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger?->error('Strategy not found for feature flag', [
                'flag' => $name,
                'flag_type' => $this->getLogPrefix(),
                'strategy' => $strategyName,
                'result' => false,
                'reason' => 'strategy_not_found',
                'available_strategies' => array_keys($this->strategies),
                'duration_ms' => round($duration, 2),
            ]);

            return false;
        }

        $strategy = $this->strategies[$strategyName];
        $result = $strategy->isEnabled($config, $context);
        $duration = (microtime(true) - $startTime) * 1000;

        $this->logger?->info('Feature flag evaluated', [
            'flag' => $name,
            'flag_type' => $this->getLogPrefix(),
            'strategy' => $strategyName,
            'result' => $result,
            'duration_ms' => round($duration, 2),
            'context_keys' => array_keys($context),
            'context_size' => count($context),
        ]);

        return $result;
    }

    /**
     * Get flag configuration
     *
     * @param string $name Flag name
     * @return array<string, mixed>|null Flag configuration or null if not found
     */
    abstract public function getConfig(string $name): ?array;

    /**
     * Check if flag exists
     *
     * @param string $name Flag name
     * @return bool True if flag exists
     */
    abstract public function exists(string $name): bool;

    /**
     * Get paginated list of flags
     *
     * @param int $page Page number (1-indexed)
     * @param int $limit Items per page (capped at MAX_LIMIT)
     * @return array{flags: array<string, array<string, mixed>>, pagination: array{total: int, page: int, pages: int, limit: int}}
     */
    abstract public function paginate(int $page = Pagination::DEFAULT_PAGE, int $limit = Pagination::DEFAULT_LIMIT): array;

    /**
     * Get log message prefix for this service type
     *
     * @return string Log prefix (e.g., "Permanent", "Persistent")
     */
    abstract protected function getLogPrefix(): string;
}
