<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Http\UploadedFile;

/**
 * Max Validation Rule
 *
 * Validates that a field has a maximum value, length, or size.
 * - For strings: validates maximum character length
 * - For numbers: validates maximum numeric value
 * - For arrays: validates maximum number of elements
 * - For files: validates maximum file size in bytes
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Max implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters [max_value]
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (empty($parameters)) {
            return false; // Max value is required
        }

        $max = (float) $parameters[0];

        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        if ($value instanceof UploadedFile) {
            return $value->getSize() <= $max;
        }

        return false;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters [max_value]
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $max = $parameters[0] ?? 'N/A';
        return "The {$field} field must not be greater than {$max}.";
    }
}
