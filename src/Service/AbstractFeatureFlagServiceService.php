<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Service;

use Pulse\Flags\Core\Constants\Pagination;
use Pulse\Flags\Core\Context\ContextInterface;
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

    public function addStrategy(StrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    public function isEnabled(string $name, ContextInterface $context): bool
    {
        // Convert context to array for strategies
        $contextArray = $context->toArray();

        $config = $this->getConfig($name);
        if (null === $config) {
            return false;
        }

        $enabled = $config['enabled'] ?? false;
        if (!$enabled) {
            return false;
        }

        $strategyName = $config['strategy'] ?? FlagStrategy::SIMPLE->value;
        if (FlagStrategy::SIMPLE->value === $strategyName) {
            return true;
        }

        if (!isset($this->strategies[$strategyName])) {
            return false;
        }

        return $this->strategies[$strategyName]->isEnabled($config, $contextArray);
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
}
