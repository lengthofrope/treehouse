<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;

/**
 * Regex Validation Rule
 *
 * Validates that a field value matches a regular expression pattern.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Regex implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters [regex_pattern]
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

        if (empty($parameters)) {
            return false; // Pattern is required
        }

        $pattern = $parameters[0];

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters [regex_pattern]
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} field format is invalid.";
    }
}
