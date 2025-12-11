<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Central service for validating feature flag configurations.
 *
 * Coordinates validation across all strategy validators.
 */
class ValidationService
{
    /** @var array<string, StrategyValidatorInterface> */
    private array $validators = [];

    /**
     * @param iterable<StrategyValidatorInterface> $validators
     */
    public function __construct(iterable $validators)
    {
        foreach ($validators as $validator) {
            $this->validators[$validator->getStrategyName()] = $validator;
        }
    }

    /**
     * Validate complete flag configuration.
     *
     * @param array<string, mixed> $config Flag configuration
     * @return ValidationResult
     */
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        // Validate basic structure
        if (!isset($config['strategy'])) {
            $result->addError('Flag configuration missing required field "strategy"');
            return $result;
        }

        $strategy = $config['strategy'];

        if (!is_string($strategy)) {
            $result->addError('Strategy must be a string');
            return $result;
        }

        // Get appropriate validator
        $validator = $this->validators[$strategy] ?? null;

        if ($validator === null) {
            $result->addError(sprintf(
                'Unknown strategy "%s". Available strategies: %s',
                $strategy,
                implode(', ', array_keys($this->validators))
            ));
            return $result;
        }

        // Delegate to strategy-specific validator
        $strategyResult = $validator->validate($config);
        $result->merge($strategyResult);

        return $result;
    }

    /**
     * Validate and throw exception if invalid.
     *
     * @param array<string, mixed> $config Flag configuration
     * @throws ValidationException If validation fails
     * @return ValidationResult
     */
    public function validateOrThrow(array $config): ValidationResult
    {
        $result = $this->validate($config);

        if (!$result->isValid()) {
            throw new ValidationException(
                'Flag configuration validation failed',
                $result
            );
        }

        return $result;
    }

    /**
     * Check if a strategy has a validator.
     *
     * @param string $strategyName
     * @return bool
     */
    public function hasValidator(string $strategyName): bool
    {
        return isset($this->validators[$strategyName]);
    }

    /**
     * Get all available strategy names.
     *
     * @return array<int, string>
     */
    public function getAvailableStrategies(): array
    {
        return array_keys($this->validators);
    }
}
