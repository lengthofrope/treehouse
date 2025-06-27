<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation;

use Exception;

/**
 * Validation Exception
 *
 * Exception thrown when validation fails. Contains validation errors
 * and provides methods to access error messages and data.
 *
 * Features:
 * - Field-specific error messages
 * - Multiple errors per field support
 * - Original data preservation
 * - JSON serialization for API responses
 *
 * @package LengthOfRope\TreeHouse\Validation
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ValidationException extends Exception
{
    /**
     * The validation errors
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * The original data being validated
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new validation exception
     *
     * @param array<string, array<string>> $errors Validation errors
     * @param array<string, mixed> $data Original data
     * @param string $message Exception message
     */
    public function __construct(array $errors, array $data = [], string $message = 'Validation failed')
    {
        $this->errors = $errors;
        $this->data = $data;
        
        parent::__construct($message);
    }

    /**
     * Get all validation errors
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     *
     * @param string $field Field name
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get the first error message for a field
     *
     * @param string $field Field name
     * @return string|null First error message or null
     */
    public function getFirstError(string $field): ?string
    {
        $errors = $this->getFieldErrors($field);
        return $errors[0] ?? null;
    }

    /**
     * Check if a field has errors
     *
     * @param string $field Field name
     * @return bool True if field has errors
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get all error messages as a flat array
     *
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        $messages = [];
        
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        
        return $messages;
    }

    /**
     * Get the first error message from all fields
     *
     * @return string|null First error message or null
     */
    public function getFirstMessage(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        
        return null;
    }

    /**
     * Get the original data
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Convert errors to JSON-serializable format
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'data' => $this->data
        ];
    }

    /**
     * Get a simple error message summary
     *
     * @return string Error summary
     */
    public function getSummary(): string
    {
        $count = count($this->errors);
        
        if ($count === 0) {
            return 'No validation errors';
        }
        
        if ($count === 1) {
            return 'Validation failed for 1 field';
        }
        
        return "Validation failed for {$count} fields";
    }
}
