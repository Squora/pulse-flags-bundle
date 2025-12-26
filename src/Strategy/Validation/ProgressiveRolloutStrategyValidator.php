<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Validator for progressive_rollout strategy configuration.
 */
class ProgressiveRolloutStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        // Must have schedule
        if (!isset($config['schedule']) || empty($config['schedule'])) {
            $result->addError('Progressive rollout strategy requires "schedule" array');
            return $result;
        }

        if (!is_array($config['schedule'])) {
            $result->addError('schedule must be an array');
            return $result;
        }

        // Validate timezone (optional)
        if (isset($config['timezone'])) {
            if (!is_string($config['timezone'])) {
                $result->addError('timezone must be a string');
            } else {
                try {
                    new \DateTimeZone($config['timezone']);
                } catch (\Exception $e) {
                    $result->addError(sprintf('Invalid timezone: %s', $e->getMessage()));
                }
            }
        }

        // Validate each stage
        $previousDate = null;
        $previousPercentage = null;

        foreach ($config['schedule'] as $index => $stage) {
            if (!is_array($stage)) {
                $result->addError(sprintf('schedule[%d]: Stage must be an array', $index));
                continue;
            }

            // Validate percentage
            if (!isset($stage['percentage'])) {
                $result->addError(sprintf('schedule[%d]: Missing required field "percentage"', $index));
            } else {
                $percentage = $stage['percentage'];

                if (!is_numeric($percentage)) {
                    $result->addError(sprintf('schedule[%d]: percentage must be a number', $index));
                } else {
                    $percentage = (float) $percentage;

                    if ($percentage < 0 || $percentage > 100) {
                        $result->addError(sprintf('schedule[%d]: percentage must be between 0 and 100', $index));
                    }

                    // Check increasing order
                    if ($previousPercentage !== null && $percentage <= $previousPercentage) {
                        $result->addError(sprintf(
                            'schedule[%d]: percentage must increase (got %s, previous was %s)',
                            $index,
                            $percentage,
                            $previousPercentage
                        ));
                    }

                    $previousPercentage = $percentage;
                }
            }

            // Validate start_date
            if (!isset($stage['start_date'])) {
                $result->addError(sprintf('schedule[%d]: Missing required field "start_date"', $index));
            } else {
                if (!is_string($stage['start_date'])) {
                    $result->addError(sprintf('schedule[%d]: start_date must be a string', $index));
                } else {
                    try {
                        $startDate = new \DateTimeImmutable($stage['start_date']);

                        // Check chronological order
                        if ($previousDate !== null && $startDate <= $previousDate) {
                            $result->addError(sprintf(
                                'schedule[%d]: start_date must be after previous stage',
                                $index
                            ));
                        }

                        $previousDate = $startDate;
                    } catch (\Exception $e) {
                        $result->addError(sprintf(
                            'schedule[%d]: Invalid start_date format: %s',
                            $index,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        // Validate stickiness (optional, same as percentage strategy)
        if (isset($config['stickiness'])) {
            $stickiness = $config['stickiness'];

            if (!is_string($stickiness) && !is_array($stickiness)) {
                $result->addError('stickiness must be a string or array of strings');
            } elseif (is_array($stickiness) && empty($stickiness)) {
                $result->addError('stickiness array cannot be empty');
            }
        }

        return $result;
    }

    public function getStrategyName(): string
    {
        return 'progressive_rollout';
    }
}
