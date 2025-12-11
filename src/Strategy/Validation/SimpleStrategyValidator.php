<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Validator for simple strategy configuration.
 *
 * Simple strategy only requires the 'enabled' field.
 */
class SimpleStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        // Simple strategy is always valid - it only checks the 'enabled' field
        // The 'enabled' field is optional and defaults to false

        if (isset($config['enabled']) && !is_bool($config['enabled'])) {
            $result->addError('The "enabled" field must be a boolean value');
        }

        return $result;
    }

    public function getStrategyName(): string
    {
        return 'simple';
    }
}
