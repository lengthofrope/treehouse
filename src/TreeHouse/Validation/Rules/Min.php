<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Http\UploadedFile;

/**
 * Min Validation Rule
 *
 * Validates that a field has a minimum value, length, or size.
 * - For strings: validates minimum character length
 * - For numbers: validates minimum numeric value
 * - For arrays: validates minimum number of elements
 * - For files: validates minimum file size in bytes
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Min implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters [min_value]
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (empty($parameters)) {
            return false; // Min value is required
        }

        $min = (float) $parameters[0];

        if (is_numeric($value)) {
            return (float) $value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        if ($value instanceof UploadedFile) {
            return $value->getSize() >= $min;
        }

        return false;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters [min_value]
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $min = $parameters[0] ?? 'N/A';
        return "The {$field} field must be at least {$min}.";
    }
}
