<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation;

/**
 * Rule Interface
 *
 * Contract for validation rules that can be applied to input data.
 * Each rule implements specific validation logic and error messaging.
 *
 * @package LengthOfRope\TreeHouse\Validation
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface RuleInterface
{
    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters
     * @param array $data All validation data for context
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool;

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string;
}
