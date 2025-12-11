<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Validator for date_range strategy configuration.
 */
class DateRangeStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        $hasStartDate = isset($config['start_date']) && !empty($config['start_date']);
        $hasEndDate = isset($config['end_date']) && !empty($config['end_date']);

        // Must have at least start_date
        if (!$hasStartDate) {
            $result->addError('Date range strategy requires "start_date"');
        }

        // Validate start_date format
        if ($hasStartDate) {
            if (!is_string($config['start_date'])) {
                $result->addError('start_date must be a string');
            } else {
                try {
                    $startDate = new \DateTimeImmutable($config['start_date']);
                } catch (\Exception $e) {
                    $result->addError(sprintf(
                        'Invalid start_date format: %s',
                        $e->getMessage()
                    ));
                }
            }
        }

        // Validate end_date format (optional)
        if ($hasEndDate) {
            if (!is_string($config['end_date'])) {
                $result->addError('end_date must be a string');
            } else {
                try {
                    $endDate = new \DateTimeImmutable($config['end_date']);

                    // Check logical order
                    if (isset($startDate) && $endDate <= $startDate) {
                        $result->addError('end_date must be after start_date');
                    }

                    // Warn if end_date is in the past
                    $now = new \DateTimeImmutable();
                    if ($endDate < $now) {
                        $result->addWarning('end_date is in the past - feature will always be disabled');
                    }
                } catch (\Exception $e) {
                    $result->addError(sprintf(
                        'Invalid end_date format: %s',
                        $e->getMessage()
                    ));
                }
            }
        }

        // Validate timezone (optional)
        if (isset($config['timezone'])) {
            if (!is_string($config['timezone'])) {
                $result->addError('timezone must be a string');
            } else {
                try {
                    new \DateTimeZone($config['timezone']);
                } catch (\Exception $e) {
                    $result->addError(sprintf(
                        'Invalid timezone: %s',
                        $e->getMessage()
                    ));
                }
            }
        }

        return $result;
    }

    public function getStrategyName(): string
    {
        return 'date_range';
    }
}
