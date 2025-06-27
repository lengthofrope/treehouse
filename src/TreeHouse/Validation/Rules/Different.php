<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Support\Arr;

/**
 * Different Validation Rule
 *
 * Validates that a field has a different value from another field.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Different implements RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters [other_field]
     * @param array $data All validation data for context
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if (empty($parameters)) {
            return false; // Other field is required
        }

        $otherField = $parameters[0];
        $otherValue = Arr::get($data, $otherField);

        return $value !== $otherValue;
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters [other_field]
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        $otherField = $parameters[0] ?? 'other field';
        return "The {$field} and {$otherField} must be different.";
    }
}
