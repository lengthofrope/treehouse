<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;

/**
 * Required Validation Rule
 *
 * Validates that a field is present and not empty.
 * Considers null, empty string, empty array, and whitespace-only strings as empty.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Required implements RuleInterface
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
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
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
        return "The {$field} field is required.";
    }
}
