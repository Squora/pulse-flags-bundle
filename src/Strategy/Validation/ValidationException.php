<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Exception thrown when flag configuration validation fails.
 */
class ValidationException extends \RuntimeException
{
    private ValidationResult $validationResult;

    public function __construct(string $message, ValidationResult $validationResult, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validationResult = $validationResult;
    }

    /**
     * Get validation result with all errors and warnings.
     *
     * @return ValidationResult
     */
    public function getValidationResult(): ValidationResult
    {
        return $this->validationResult;
    }

    /**
     * Get all validation errors.
     *
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->validationResult->getErrors();
    }

    /**
     * Get all validation warnings.
     *
     * @return array<int, string>
     */
    public function getWarnings(): array
    {
        return $this->validationResult->getWarnings();
    }
}
