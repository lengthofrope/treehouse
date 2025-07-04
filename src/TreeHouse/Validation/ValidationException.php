<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

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
 * - Enhanced error handling with BaseException features
 *
 * @package LengthOfRope\TreeHouse\Validation
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ValidationException extends BaseException
{
    /**
     * Default error severity for validation errors
     */
    protected string $severity = 'low';

    /**
     * Default HTTP status code for validation errors
     */
    protected int $statusCode = 422;

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
        
        // Add validation-specific context
        $context = [
            'validation_errors' => $errors,
            'field_count' => count($errors),
            'error_count' => array_sum(array_map('count', $errors)),
            'failed_fields' => array_keys($errors),
        ];
        
        parent::__construct($message, 0, null, $context);
        
        $this->userMessage = 'The provided data is invalid. Please check the errors and try again.';
        
        // Validation errors usually shouldn't be reported (they're user errors)
        $this->reportable = false;
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
        $array = parent::toArray();
        
        // Add validation-specific information
        $array['validation'] = [
            'errors' => $this->errors,
            'data' => $this->data,
            'summary' => $this->getSummary(),
        ];
        
        return $array;
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
