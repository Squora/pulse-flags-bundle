<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

use Pulse\Flags\Core\Constants\PercentageStrategy;
use Pulse\Flags\Core\Enum\FlagStrategy;

/**
 * Validator for composite feature flag strategies.
 *
 * Performs comprehensive validation of composite strategy configurations,
 * including nested composites, strategy compatibility, required context,
 * and configuration correctness for each strategy type.
 *
 * Validation rules:
 * - Empty strategies array is forbidden
 * - Unknown strategy types are forbidden
 * - SimpleStrategy cannot be used in composites (always returns true)
 * - Maximum nesting depth of 5 levels
 * - Strategy-specific validation (percentage bounds, required fields, etc.)
 *
 * Example usage:
 * ```php
 * $validator = new CompositeStrategyValidator();
 * $errors = $validator->validate($config);
 * if (!empty($errors)) {
 *     throw new \InvalidArgumentException(implode(', ', $errors));
 * }
 * ```
 */
class CompositeStrategyValidator
{
    /**
     * Maximum allowed nesting depth for composite strategies.
     *
     * Prevents excessive recursion and maintains reasonable complexity.
     */
    private const MAX_NESTING_DEPTH = 5;

    /**
     * Strategy types that cannot be used within composite strategies.
     *
     * SimpleStrategy is incompatible because it always returns true,
     * making its inclusion in a composite meaningless.
     */
    private const INCOMPATIBLE_STRATEGIES = [FlagStrategy::SIMPLE->value];

    /**
     * Maps strategy types to their required context keys.
     *
     * Used to analyze what context data is needed for composite evaluation.
     * Percentage strategy can use either user_id OR session_id.
     */
    private const REQUIRED_CONTEXT = [
        FlagStrategy::PERCENTAGE->value => ['user_id', 'session_id'],
        FlagStrategy::USER_ID->value => ['user_id'],
        FlagStrategy::DATE_RANGE->value => [],
    ];

    /**
     * Valid strategy types that can be used in composites.
     *
     * Used for validating strategy type values.
     */
    private const VALID_STRATEGIES = [
        FlagStrategy::PERCENTAGE->value,
        FlagStrategy::USER_ID->value,
        FlagStrategy::DATE_RANGE->value,
        FlagStrategy::COMPOSITE->value,
    ];

    /**
     * Validates a composite strategy configuration.
     *
     * Performs recursive validation for nested composites and checks:
     * - Nesting depth limits
     * - Empty strategies arrays
     * - Unknown or incompatible strategy types
     * - Strategy-specific configuration validity
     *
     * @param array<string, mixed> $config The composite strategy configuration
     * @param int $depth Current nesting depth (for recursive validation)
     * @return array<string> List of validation error messages (empty if valid)
     */
    public function validate(array $config, int $depth = 0): array
    {
        $errors = [];

        // Check nesting depth
        if ($depth > self::MAX_NESTING_DEPTH) {
            $errors[] = sprintf(
                'Maximum nesting depth of %d exceeded',
                self::MAX_NESTING_DEPTH
            );
            return $errors;
        }

        // Validate strategies array exists and is not empty
        if (!isset($config['strategies'])) {
            $errors[] = 'Composite strategy must have a "strategies" field';
            return $errors;
        }

        $strategies = $config['strategies'];

        if (!is_array($strategies)) {
            $errors[] = 'The "strategies" field must be an array';
            return $errors;
        }

        if (empty($strategies)) {
            $errors[] = 'Composite strategy requires at least one sub-strategy';
            return $errors;
        }

        // Validate each sub-strategy
        foreach ($strategies as $index => $strategyConfig) {
            if (!is_array($strategyConfig)) {
                $errors[] = sprintf('Strategy at index %d must be an array', $index);
                continue;
            }

            $strategyErrors = $this->validateStrategy($strategyConfig, $depth + 1, $index);
            $errors = array_merge($errors, $strategyErrors);
        }

        return $errors;
    }

    /**
     * Validates a single strategy configuration within a composite.
     *
     * @param array<string, mixed> $config Strategy configuration
     * @param int $depth Current nesting depth
     * @param int $index Strategy index in the parent composite
     * @return array<string> List of validation error messages
     */
    private function validateStrategy(array $config, int $depth, int $index): array
    {
        $errors = [];

        // Validate strategy type is present
        if (!isset($config['type'])) {
            $errors[] = sprintf('Strategy at index %d is missing "type" field', $index);
            return $errors;
        }

        $strategyType = $config['type'];

        // Validate strategy type is known
        if (!in_array($strategyType, self::VALID_STRATEGIES, true)) {
            $errors[] = sprintf(
                'Unknown strategy type "%s" at index %d. Valid types: %s',
                $strategyType,
                $index,
                implode(', ', self::VALID_STRATEGIES)
            );
            return $errors;
        }

        // Validate strategy is not incompatible
        if (in_array($strategyType, self::INCOMPATIBLE_STRATEGIES, true)) {
            $errors[] = sprintf(
                'Strategy type "%s" at index %d is incompatible with composite strategies',
                $strategyType,
                $index
            );
            return $errors;
        }

        // Validate strategy-specific configuration
        switch ($strategyType) {
            case FlagStrategy::PERCENTAGE->value:
                $errors = array_merge($errors, $this->validatePercentageStrategy($config, $index));
                break;

            case FlagStrategy::USER_ID->value:
                $errors = array_merge($errors, $this->validateUserIdStrategy($config, $index));
                break;

            case FlagStrategy::DATE_RANGE->value:
                $errors = array_merge($errors, $this->validateDateRangeStrategy($config, $index));
                break;

            case FlagStrategy::COMPOSITE->value:
                // Recursive validation for nested composite
                $nestedErrors = $this->validate($config, $depth);
                foreach ($nestedErrors as $error) {
                    $errors[] = sprintf('Nested composite at index %d: %s', $index, $error);
                }
                break;
        }

        return $errors;
    }

    /**
     * Validates percentage strategy configuration.
     *
     * @param array<string, mixed> $config Strategy configuration
     * @param int $index Strategy index
     * @return array<string> List of validation error messages
     */
    private function validatePercentageStrategy(array $config, int $index): array
    {
        $errors = [];

        if (!isset($config['percentage'])) {
            $errors[] = sprintf(
                'Percentage strategy at index %d is missing "percentage" field',
                $index
            );
            return $errors;
        }

        $percentage = $config['percentage'];

        if (!is_numeric($percentage)) {
            $errors[] = sprintf(
                'Percentage strategy at index %d has non-numeric percentage value',
                $index
            );
            return $errors;
        }

        $percentage = (float) $percentage;

        if ($percentage < PercentageStrategy::MIN_PERCENTAGE || $percentage > PercentageStrategy::MAX_PERCENTAGE) {
            $errors[] = sprintf(
                'Percentage strategy at index %d has invalid percentage value %.2f (must be between %d and %d)',
                $index,
                $percentage,
                PercentageStrategy::MIN_PERCENTAGE,
                PercentageStrategy::MAX_PERCENTAGE
            );
        }

        return $errors;
    }

    /**
     * Validates user ID strategy configuration.
     *
     * @param array<string, mixed> $config Strategy configuration
     * @param int $index Strategy index
     * @return array<string> List of validation error messages
     */
    private function validateUserIdStrategy(array $config, int $index): array
    {
        $errors = [];

        $hasWhitelist = isset($config['whitelist']);
        $hasBlacklist = isset($config['blacklist']);

        if (!$hasWhitelist && !$hasBlacklist) {
            $errors[] = sprintf(
                'User ID strategy at index %d must have either "whitelist" or "blacklist"',
                $index
            );
            return $errors;
        }

        if ($hasWhitelist) {
            if (!is_array($config['whitelist'])) {
                $errors[] = sprintf(
                    'User ID strategy at index %d has non-array whitelist',
                    $index
                );
            } elseif (empty($config['whitelist'])) {
                $errors[] = sprintf(
                    'User ID strategy at index %d has empty whitelist',
                    $index
                );
            }
        }

        if ($hasBlacklist) {
            if (!is_array($config['blacklist'])) {
                $errors[] = sprintf(
                    'User ID strategy at index %d has non-array blacklist',
                    $index
                );
            } elseif (empty($config['blacklist'])) {
                $errors[] = sprintf(
                    'User ID strategy at index %d has empty blacklist',
                    $index
                );
            }
        }

        return $errors;
    }

    /**
     * Validates date range strategy configuration.
     *
     * @param array<string, mixed> $config Strategy configuration
     * @param int $index Strategy index
     * @return array<string> List of validation error messages
     */
    private function validateDateRangeStrategy(array $config, int $index): array
    {
        $errors = [];

        $hasStartDate = !empty($config['start_date']);
        $hasEndDate = !empty($config['end_date']);

        if (!$hasStartDate && !$hasEndDate) {
            $errors[] = sprintf(
                'Date range strategy at index %d must have at least "start_date" or "end_date"',
                $index
            );
            return $errors;
        }

        $startDate = null;
        $endDate = null;

        // Validate start_date
        if ($hasStartDate) {
            try {
                $startDate = $config['start_date'] instanceof \DateTimeInterface
                    ? $config['start_date']
                    : new \DateTimeImmutable($config['start_date']);
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    'Date range strategy at index %d has invalid start_date: %s',
                    $index,
                    $e->getMessage()
                );
            }
        }

        // Validate end_date
        if ($hasEndDate) {
            try {
                $endDate = $config['end_date'] instanceof \DateTimeInterface
                    ? $config['end_date']
                    : new \DateTimeImmutable($config['end_date']);
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    'Date range strategy at index %d has invalid end_date: %s',
                    $index,
                    $e->getMessage()
                );
            }
        }

        // Validate start_date <= end_date
        if ($startDate && $endDate && $startDate > $endDate) {
            $errors[] = sprintf(
                'Date range strategy at index %d has start_date after end_date',
                $index
            );
        }

        return $errors;
    }

    /**
     * Analyzes required context for a composite strategy.
     *
     * Returns a merged list of all context keys required by any strategy
     * within the composite (including nested composites). For percentage
     * strategy, returns both 'user_id' and 'session_id' since either can
     * be used.
     *
     * @param array<string, mixed> $config The composite strategy configuration
     * @return array<string> List of required context keys
     */
    public function getRequiredContext(array $config): array
    {
        if (!isset($config['strategies']) || !is_array($config['strategies'])) {
            return [];
        }

        $requiredContext = [];

        foreach ($config['strategies'] as $strategyConfig) {
            if (!is_array($strategyConfig) || !isset($strategyConfig['type'])) {
                continue;
            }

            $strategyType = $strategyConfig['type'];

            // Recursively get context for nested composites
            if ($strategyType === FlagStrategy::COMPOSITE->value) {
                $nestedContext = $this->getRequiredContext($strategyConfig);
                $requiredContext = array_merge($requiredContext, $nestedContext);
                continue;
            }

            // Get context for known strategies
            if (isset(self::REQUIRED_CONTEXT[$strategyType])) {
                $requiredContext = array_merge(
                    $requiredContext,
                    self::REQUIRED_CONTEXT[$strategyType]
                );
            }
        }

        return array_unique($requiredContext);
    }
}
