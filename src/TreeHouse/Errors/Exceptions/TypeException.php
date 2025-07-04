<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use Throwable;
use TypeError;

/**
 * Type Exception
 * 
 * Handles strict typing errors in PHP 8.4+. Provides detailed information
 * about type mismatches and helps developers understand and fix type-related
 * issues in their code.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TypeException extends BaseException
{
    /**
     * Default error severity for type errors
     */
    protected string $severity = 'high';

    /**
     * Default HTTP status code for type errors
     */
    protected int $statusCode = 500;

    /**
     * Expected type
     */
    protected ?string $expectedType = null;

    /**
     * Actual type received
     */
    protected ?string $actualType = null;

    /**
     * Parameter or variable name
     */
    protected ?string $parameterName = null;

    /**
     * Function or method name where the error occurred
     */
    protected ?string $functionName = null;

    /**
     * Create a new type exception
     *
     * @param string $message Exception message
     * @param string|null $expectedType Expected type
     * @param string|null $actualType Actual type received
     * @param string|null $parameterName Parameter name
     * @param string|null $functionName Function/method name
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = 'Type error occurred',
        ?string $expectedType = null,
        ?string $actualType = null,
        ?string $parameterName = null,
        ?string $functionName = null,
        ?Throwable $previous = null,
        array $context = []
    ) {
        $this->expectedType = $expectedType;
        $this->actualType = $actualType;
        $this->parameterName = $parameterName;
        $this->functionName = $functionName;

        // Add type information to context
        $context = array_merge($context, [
            'expected_type' => $expectedType,
            'actual_type' => $actualType,
            'parameter_name' => $parameterName,
            'function_name' => $functionName,
        ]);

        parent::__construct($message, 0, $previous, $context);
        
        $this->userMessage = 'A type error occurred. Please contact support if this problem persists.';
    }

    /**
     * Get the expected type
     */
    public function getExpectedType(): ?string
    {
        return $this->expectedType;
    }

    /**
     * Get the actual type received
     */
    public function getActualType(): ?string
    {
        return $this->actualType;
    }

    /**
     * Get the parameter name
     */
    public function getParameterName(): ?string
    {
        return $this->parameterName;
    }

    /**
     * Get the function name
     */
    public function getFunctionName(): ?string
    {
        return $this->functionName;
    }

    /**
     * Create exception from PHP TypeError
     *
     * @param TypeError $typeError
     * @return static
     */
    public static function fromTypeError(TypeError $typeError): static
    {
        $message = $typeError->getMessage();
        
        // Parse TypeError message to extract type information
        $expectedType = null;
        $actualType = null;
        $parameterName = null;
        $functionName = null;

        // Try to parse common TypeError patterns
        if (preg_match('/must be of (?:the )?type ([^,]+), ([^,\s]+) given/', $message, $matches)) {
            $expectedType = trim($matches[1]);
            $actualType = trim($matches[2]);
        }

        if (preg_match('/Argument #\d+ \(\$(\w+)\)/', $message, $matches)) {
            $parameterName = $matches[1];
        }

        if (preg_match('/passed to ([^,]+)/', $message, $matches)) {
            $functionName = trim($matches[1]);
        }

        return new static(
            $message,
            $expectedType,
            $actualType,
            $parameterName,
            $functionName,
            $typeError
        );
    }

    /**
     * Create exception for invalid parameter type
     *
     * @param string $parameterName
     * @param string $expectedType
     * @param mixed $actualValue
     * @param string|null $functionName
     * @return static
     */
    public static function invalidParameterType(
        string $parameterName,
        string $expectedType,
        mixed $actualValue,
        ?string $functionName = null
    ): static {
        $actualType = gettype($actualValue);
        $message = "Parameter '{$parameterName}' must be of type {$expectedType}, {$actualType} given";
        
        if ($functionName) {
            $message .= " in {$functionName}()";
        }

        return new static(
            $message,
            $expectedType,
            $actualType,
            $parameterName,
            $functionName,
            null,
            ['actual_value' => $actualValue]
        );
    }

    /**
     * Create exception for invalid return type
     *
     * @param string $expectedType
     * @param mixed $actualValue
     * @param string|null $functionName
     * @return static
     */
    public static function invalidReturnType(
        string $expectedType,
        mixed $actualValue,
        ?string $functionName = null
    ): static {
        $actualType = gettype($actualValue);
        $message = "Return value must be of type {$expectedType}, {$actualType} returned";
        
        if ($functionName) {
            $message .= " from {$functionName}()";
        }

        return new static(
            $message,
            $expectedType,
            $actualType,
            null,
            $functionName,
            null,
            ['actual_value' => $actualValue]
        );
    }

    /**
     * Create exception for invalid property type
     *
     * @param string $propertyName
     * @param string $expectedType
     * @param mixed $actualValue
     * @param string|null $className
     * @return static
     */
    public static function invalidPropertyType(
        string $propertyName,
        string $expectedType,
        mixed $actualValue,
        ?string $className = null
    ): static {
        $actualType = gettype($actualValue);
        $message = "Property '{$propertyName}' must be of type {$expectedType}, {$actualType} given";
        
        if ($className) {
            $message .= " in class {$className}";
        }

        return new static(
            $message,
            $expectedType,
            $actualType,
            $propertyName,
            $className,
            null,
            ['actual_value' => $actualValue]
        );
    }

    /**
     * Create exception for union type mismatch
     *
     * @param array<string> $expectedTypes
     * @param mixed $actualValue
     * @param string|null $parameterName
     * @param string|null $functionName
     * @return static
     */
    public static function unionTypeMismatch(
        array $expectedTypes,
        mixed $actualValue,
        ?string $parameterName = null,
        ?string $functionName = null
    ): static {
        $actualType = gettype($actualValue);
        $expectedTypeString = implode('|', $expectedTypes);
        
        $message = "Value must be of type {$expectedTypeString}, {$actualType} given";
        
        if ($parameterName) {
            $message = "Parameter '{$parameterName}' " . lcfirst($message);
        }
        
        if ($functionName) {
            $message .= " in {$functionName}()";
        }

        return new static(
            $message,
            $expectedTypeString,
            $actualType,
            $parameterName,
            $functionName,
            null,
            [
                'expected_types' => $expectedTypes,
                'actual_value' => $actualValue,
            ]
        );
    }

    /**
     * Get a detailed error description for developers
     */
    public function getDetailedDescription(): string
    {
        $description = "Type Error Details:\n";
        
        if ($this->functionName) {
            $description .= "Function: {$this->functionName}\n";
        }
        
        if ($this->parameterName) {
            $description .= "Parameter: {$this->parameterName}\n";
        }
        
        if ($this->expectedType) {
            $description .= "Expected Type: {$this->expectedType}\n";
        }
        
        if ($this->actualType) {
            $description .= "Actual Type: {$this->actualType}\n";
        }
        
        $description .= "Message: {$this->getMessage()}\n";
        $description .= "File: {$this->getFile()}:{$this->getLine()}";
        
        return $description;
    }

    /**
     * Convert to array with type-specific information
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['type_info'] = [
            'expected_type' => $this->expectedType,
            'actual_type' => $this->actualType,
            'parameter_name' => $this->parameterName,
            'function_name' => $this->functionName,
        ];
        
        return $array;
    }
}