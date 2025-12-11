<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Interface for strategy configuration validators.
 *
 * Validators check strategy configuration at save time to catch errors early
 * before they affect production traffic.
 */
interface StrategyValidatorInterface
{
    /**
     * Validate strategy configuration.
     *
     * @param array<string, mixed> $config Strategy configuration to validate
     * @return ValidationResult Result containing errors and warnings
     */
    public function validate(array $config): ValidationResult;

    /**
     * Get the strategy name this validator handles.
     *
     * @return string Strategy name (e.g., 'percentage', 'user_id')
     */
    public function getStrategyName(): string;
}
