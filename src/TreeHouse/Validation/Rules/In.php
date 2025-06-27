<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;

/**
 * In Validation Rule
 *
 * Validates that a field value is included in a list of acceptable values.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class In implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (list of acceptable values)
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        return in_array((string) $value, $parameters, true);
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters (list of acceptable values)
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $values = implode(', ', $parameters);
        return "The selected {$field} is invalid. Must be one of: {$values}.";
    }
}
