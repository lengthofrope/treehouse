<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;

/**
 * Alpha Dash Validation Rule
 *
 * Validates that a field contains only alphanumeric characters, dashes, and underscores.
 * (a-z, A-Z, 0-9, -, _)
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AlphaDash implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (unused)
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
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
        return "The {$field} field must contain only letters, numbers, dashes, and underscores.";
    }
}
