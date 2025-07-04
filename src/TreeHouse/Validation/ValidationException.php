<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation;

use LengthOfRope\TreeHouse\Errors\Exceptions\BaseException;

/**
 * Validation Exception
 * 
 * Thrown when validation fails. Contains detailed information about
 * validation errors for each field.
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
     * Validation should not be reported by default
     */
    protected bool $reportable = false;

    /**
     * Validation errors by field
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Original data that failed validation
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new validation exception
     *
     * @param array<string, array<string>> $errors Validation errors by field
     * @param array<string, mixed> $data Original data that failed validation
     * @param string $message Custom error message
     */
    public function __construct(
        array $errors = [],
        array $data = [],
        string $message = 'Validation failed'
    ) {
        $this->errors = $errors;
        $this->data = $data;

        $context = [
            'errors' => $errors,
            'data' => $this->sanitizeData($data),
            'field_count' => count($errors),
            'error_count' => array_sum(array_map('count', $errors))
        ];

        parent::__construct($message, 0, null, $context);
        
        $this->userMessage = 'Please check your input and try again.';
    }

    /**
     * Generate a unique error code for validation exceptions
     */
    protected function generateErrorCode(): void
    {
        if (empty($this->errorCode)) {
            $this->errorCode = 'VAL_' . str_pad((string)random_int(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get validation errors
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
     * @param string $field
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get the first error for a specific field
     *
     * @param string $field
     * @return string|null
     */
    public function getFirstError(string $field): ?string
    {
        $errors = $this->getFieldErrors($field);
        return !empty($errors) ? $errors[0] : null;
    }

    /**
     * Check if a field has errors
     *
     * @param string $field
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return !empty($this->errors[$field]);
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
     * Get the first error message from any field
     *
     * @return string|null
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
     * Get the original data that failed validation
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get a summary of validation errors
     */
    public function getSummary(): string
    {
        $fieldCount = count($this->errors);
        
        if ($fieldCount === 0) {
            return 'No validation errors';
        }
        
        if ($fieldCount === 1) {
            return 'Validation failed for 1 field';
        }
        
        return "Validation failed for {$fieldCount} fields";
    }

    /**
     * Convert exception to array format
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['errors'] = $this->errors;
        $array['data'] = $this->data;
        
        return $array;
    }

    /**
     * Sanitize data to remove sensitive information
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            // Hide potentially sensitive fields
            if (preg_match('/password|pass|pwd|secret|token|key|hash/i', $key)) {
                $sanitized[$key] = '[HIDDEN]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}
