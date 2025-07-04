<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

/**
 * Invalid Argument Exception
 * 
 * Thrown when an invalid argument is passed to a method or function.
 * This exception indicates a programming error where the caller has
 * provided an argument that doesn't meet the expected criteria.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class InvalidArgumentException extends BaseException
{
    /**
     * Default error severity for invalid arguments
     */
    protected string $severity = 'medium';

    /**
     * Default HTTP status code for invalid arguments
     */
    protected int $statusCode = 400;

    /**
     * Whether this exception should be reported (usually not for invalid arguments)
     */
    protected bool $reportable = false;

    /**
     * Create a new invalid argument exception
     *
     * @param string $message Exception message
     * @param string|null $argumentName Name of the invalid argument
     * @param mixed $argumentValue Value of the invalid argument
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = 'Invalid argument provided',
        ?string $argumentName = null,
        mixed $argumentValue = null,
        array $context = []
    ) {
        // Add argument details to context
        if ($argumentName !== null) {
            $context['argument_name'] = $argumentName;
        }
        
        if ($argumentValue !== null) {
            $context['argument_value'] = $argumentValue;
            $context['argument_type'] = gettype($argumentValue);
        }

        parent::__construct($message, 0, null, $context);
        
        $this->userMessage = 'Invalid input provided. Please check your request and try again.';
    }

    /**
     * Create exception for invalid type
     *
     * @param string $argumentName
     * @param string $expectedType
     * @param mixed $actualValue
     * @return static
     */
    public static function invalidType(string $argumentName, string $expectedType, mixed $actualValue): static
    {
        $actualType = gettype($actualValue);
        $message = "Argument '{$argumentName}' must be of type {$expectedType}, {$actualType} given";
        
        return new static($message, $argumentName, $actualValue, [
            'expected_type' => $expectedType,
            'actual_type' => $actualType,
        ]);
    }

    /**
     * Create exception for invalid value
     *
     * @param string $argumentName
     * @param mixed $value
     * @param array<mixed> $allowedValues
     * @return static
     */
    public static function invalidValue(string $argumentName, mixed $value, array $allowedValues = []): static
    {
        $message = "Invalid value for argument '{$argumentName}'";
        
        if (!empty($allowedValues)) {
            $allowedString = implode(', ', array_map('strval', $allowedValues));
            $message .= ". Allowed values: {$allowedString}";
        }
        
        return new static($message, $argumentName, $value, [
            'allowed_values' => $allowedValues,
        ]);
    }

    /**
     * Create exception for missing required argument
     *
     * @param string $argumentName
     * @return static
     */
    public static function missingRequired(string $argumentName): static
    {
        $message = "Required argument '{$argumentName}' is missing";
        
        return new static($message, $argumentName, null, [
            'required' => true,
        ]);
    }
}