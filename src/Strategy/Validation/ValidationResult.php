<?php

declare(strict_types=1);

namespace Pulse\Flags\Core\Strategy\Validation;

/**
 * Result of strategy configuration validation.
 *
 * Contains errors (blocking issues) and warnings (non-blocking suggestions).
 */
class ValidationResult
{
    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /**
     * Add an error (blocking validation issue).
     *
     * @param string $message Error message
     * @return self
     */
    public function addError(string $message): self
    {
        $this->errors[] = $message;
        return $this;
    }

    /**
     * Add a warning (non-blocking suggestion).
     *
     * @param string $message Warning message
     * @return self
     */
    public function addWarning(string $message): self
    {
        $this->warnings[] = $message;
        return $this;
    }

    /**
     * Check if validation passed (no errors).
     *
     * @return bool True if no errors
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all errors.
     *
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warnings.
     *
     * @return array<int, string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if there are any warnings.
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Merge another validation result into this one.
     *
     * @param ValidationResult $other
     * @return self
     */
    public function merge(ValidationResult $other): self
    {
        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->warnings = array_merge($this->warnings, $other->getWarnings());
        return $this;
    }
}
