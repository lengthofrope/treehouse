<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Http\UploadedFile;

/**
 * MIME Types Validation Rule
 *
 * Validates that a field contains a file with acceptable MIME types.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Mimes implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (list of acceptable MIME types)
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (!($value instanceof UploadedFile)) {
            return false;
        }

        if (!$value->isValid()) {
            return false;
        }

        if (empty($parameters)) {
            return false; // MIME types are required
        }

        $mimeType = $value->getMimeType();
        return in_array($mimeType, $parameters, true);
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters (list of acceptable MIME types)
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $types = implode(', ', $parameters);
        return "The {$field} field must be a file of type: {$types}.";
    }
}
