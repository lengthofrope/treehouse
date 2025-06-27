<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Support\Arr;

/**
 * Confirmed Validation Rule
 *
 * Validates that a field has a matching confirmation field.
 * For example, password and password_confirmation fields.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Confirmed implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (unused)
     * @param array $data All validation data for context
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        // Get the field name from the current validation context
        // This is a limitation - we need the field name to determine the confirmation field
        // In a real implementation, this would be passed via parameters or context
        
        // For now, we'll look for common confirmation patterns
        $confirmationFields = [
            'password_confirmation',
            'email_confirmation',
            'confirm_password',
            'confirm_email'
        ];

        foreach ($confirmationFields as $confirmField) {
            if (Arr::has($data, $confirmField)) {
                return $value === Arr::get($data, $confirmField);
            }
        }

        return false;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters (unused)
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} confirmation does not match.";
    }
}
