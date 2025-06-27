<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;

/**
 * Boolean Validation Rule
 *
 * Validates that a field contains a boolean value or boolean-like value.
 * Accepts: true, false, 1, 0, "1", "0", "true", "false", "yes", "no", "on", "off"
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Boolean implements RuleInterface
{
    /**
     * Acceptable boolean values
     *
     * @var array<mixed>
     */
    protected array $booleanValues = [
        true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'
    ];

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

        if (is_string($value)) {
            $value = strtolower($value);
        }

        return in_array($value, $this->booleanValues, true);
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
        return "The {$field} field must be true or false.";
    }
}
