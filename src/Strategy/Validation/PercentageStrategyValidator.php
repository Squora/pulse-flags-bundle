<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

use Pulse\Flags\Core\Constants\PercentageStrategy as PercentageConstants;
use Pulse\Flags\Core\Enum\HashAlgorithm;

/**
 * Validator for percentage strategy configuration.
 */
class PercentageStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        // Validate percentage
        if (!isset($config['percentage'])) {
            $result->addError('Percentage strategy requires "percentage" field');
        } else {
            $percentage = $config['percentage'];

            if (!is_numeric($percentage)) {
                $result->addError('Percentage must be a number');
            } else {
                $percentage = (float) $percentage;

                if ($percentage < 0 || $percentage > 100) {
                    $result->addError('Percentage must be between 0 and 100');
                }

                // Check precision (max 3 decimal places for 100,000 buckets)
                if ($percentage > 0 && $percentage < 0.001) {
                    $result->addWarning('Percentage below 0.001% may not have statistical significance with 100,000 buckets');
                }
            }
        }

        // Validate hash algorithm (optional)
        if (isset($config['hash_algorithm'])) {
            $algorithm = $config['hash_algorithm'];
            $validAlgorithms = array_column(HashAlgorithm::cases(), 'value');

            if (!in_array($algorithm, $validAlgorithms, true)) {
                $result->addError(sprintf(
                    'Invalid hash_algorithm "%s". Valid options: %s',
                    $algorithm,
                    implode(', ', $validAlgorithms)
                ));
            }
        }

        // Validate stickiness (optional)
        if (isset($config['stickiness'])) {
            $stickiness = $config['stickiness'];

            if (!is_string($stickiness) && !is_array($stickiness)) {
                $result->addError('Stickiness must be a string or array of strings');
            } elseif (is_array($stickiness) && empty($stickiness)) {
                $result->addError('Stickiness array cannot be empty');
            }
        }

        // Validate hash seed (optional)
        if (isset($config['hash_seed']) && !is_string($config['hash_seed'])) {
            $result->addError('Hash seed must be a string');
        }

        return $result;
    }

    public function getStrategyName(): string
    {
        return 'percentage';
    }
}
