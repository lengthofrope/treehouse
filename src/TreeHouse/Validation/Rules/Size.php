<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Http\UploadedFile;

/**
 * File Size Validation Rule
 *
 * Validates that a file has an exact size in bytes.
 * For range validation, use min/max rules instead.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Size implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters [size_in_bytes]
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (empty($parameters)) {
            return false; // Size is required
        }

        $expectedSize = (int) $parameters[0];

        if ($value instanceof UploadedFile) {
            return $value->getSize() === $expectedSize;
        }

        if (is_string($value)) {
            return mb_strlen($value) === $expectedSize;
        }

        if (is_array($value)) {
            return count($value) === $expectedSize;
        }

        return false;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters [size_in_bytes]
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $size = $parameters[0] ?? 'N/A';
        return "The {$field} field must be exactly {$size} in size.";
    }
}
