<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Http\UploadedFile;

/**
 * Between Validation Rule
 *
 * Validates that a field has a value, length, or size between a minimum and maximum.
 * - For strings: validates character length is between min and max
 * - For numbers: validates numeric value is between min and max
 * - For arrays: validates number of elements is between min and max
 * - For files: validates file size in bytes is between min and max
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Between implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters [min_value, max_value]
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (count($parameters) < 2) {
            return false; // Min and max values are required
        }

        $min = (float) $parameters[0];
        $max = (float) $parameters[1];

        if (is_string($value)) {
            $length = mb_strlen($value);
            return $length >= $min && $length <= $max;
        }

        if (is_numeric($value)) {
            $numValue = (float) $value;
            return $numValue >= $min && $numValue <= $max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= $min && $count <= $max;
        }

        if ($value instanceof UploadedFile) {
            $size = $value->getSize();
            return $size >= $min && $size <= $max;
        }

        return false;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters [min_value, max_value]
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $min = $parameters[0] ?? 'N/A';
        $max = $parameters[1] ?? 'N/A';
        return "The {$field} field must be between {$min} and {$max}.";
    }
}
