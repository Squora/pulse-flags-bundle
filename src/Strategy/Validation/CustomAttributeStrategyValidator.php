<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

use Pulse\Flags\Core\Enum\AttributeOperator;

/**
 * Validator for custom_attribute strategy configuration.
 */
class CustomAttributeStrategyValidator implements StrategyValidatorInterface
{
    public function validate(array $config): ValidationResult
    {
        $result = new ValidationResult();

        // Must have rules
        if (!isset($config['rules']) || empty($config['rules'])) {
            $result->addError('Custom attribute strategy requires "rules" array');
            return $result;
        }

        if (!is_array($config['rules'])) {
            $result->addError('rules must be an array');
            return $result;
        }

        // Validate each rule
        foreach ($config['rules'] as $index => $rule) {
            if (!is_array($rule)) {
                $result->addError(sprintf('rules[%d]: Rule must be an array', $index));
                continue;
            }

            $this->validateRule($rule, $index, $result);
        }

        return $result;
    }

    /**
     * Validate a single rule.
     *
     * @param array<string, mixed> $rule
     * @param int $index
     * @param ValidationResult $result
     * @return void
     */
    private function validateRule(array $rule, int $index, ValidationResult $result): void
    {
        // Must have attribute
        if (!isset($rule['attribute']) || empty($rule['attribute'])) {
            $result->addError(sprintf('rules[%d]: Missing required field "attribute"', $index));
        } elseif (!is_string($rule['attribute'])) {
            $result->addError(sprintf('rules[%d]: attribute must be string', $index));
        }

        // Must have operator
        if (!isset($rule['operator']) || empty($rule['operator'])) {
            $result->addError(sprintf('rules[%d]: Missing required field "operator"', $index));
        } else {
            $operator = $rule['operator'];
            $validOperators = array_column(AttributeOperator::cases(), 'value');

            if (!in_array($operator, $validOperators, true)) {
                $result->addError(sprintf(
                    'rules[%d]: Invalid operator "%s". Valid options: %s',
                    $index,
                    $operator,
                    implode(', ', $validOperators)
                ));
            }
        }

        // Must have value or values
        $hasValue = isset($rule['value']);
        $hasValues = isset($rule['values']);

        if (!$hasValue && !$hasValues) {
            $result->addError(sprintf('rules[%d]: Missing required field "value" or "values"', $index));
        }

        // Validate values is array for array operators
        if ($hasValues && !is_array($rule['values'])) {
            $result->addError(sprintf('rules[%d]: "values" must be an array', $index));
        }

        // Validate regex pattern
        if (isset($rule['operator']) && $rule['operator'] === 'regex' && isset($rule['value'])) {
            $pattern = $rule['value'];
            if (!is_string($pattern)) {
                $result->addError(sprintf('rules[%d]: regex pattern must be string', $index));
            } elseif (@preg_match($pattern, '') === false) {
                $result->addError(sprintf(
                    'rules[%d]: Invalid regex pattern "%s"',
                    $index,
                    $pattern
                ));
            }
        }
    }

    public function getStrategyName(): string
    {
        return 'custom_attribute';
    }
}
