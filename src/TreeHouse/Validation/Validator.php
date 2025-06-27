<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation;

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Str;
use LengthOfRope\TreeHouse\Http\UploadedFile;
use InvalidArgumentException;

// Import helper functions
require_once __DIR__ . '/../Support/helpers.php';

/**
 * Input Validator
 *
 * Comprehensive validation system with built-in rules and custom rule support.
 * Provides field-level validation with detailed error messages and data transformation.
 *
 * Features:
 * - Built-in validation rules (required, email, numeric, etc.)
 * - Custom rule registration and usage
 * - Nested array validation with dot notation
 * - File upload validation
 * - Conditional validation rules
 * - Error message customization
 * - Data transformation and filtering
 *
 * @package LengthOfRope\TreeHouse\Validation
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Validator
{
    /**
     * The data being validated
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * The validation rules
     *
     * @var array<string, array<string>>
     */
    protected array $rules = [];

    /**
     * Custom error messages
     *
     * @var array<string, string>
     */
    protected array $messages = [];

    /**
     * Custom field labels
     *
     * @var array<string, string>
     */
    protected array $labels = [];

    /**
     * Validation errors
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Registered validation rules
     *
     * @var array<string, RuleInterface>
     */
    protected static array $customRules = [];

    /**
     * Built-in validation rules
     *
     * @var array<string, string>
     */
    protected array $builtinRules = [
        'required' => Rules\Required::class,
        'email' => Rules\Email::class,
        'numeric' => Rules\Numeric::class,
        'integer' => Rules\Integer::class,
        'string' => Rules\StringRule::class,
        'boolean' => Rules\Boolean::class,
        'array' => Rules\ArrayRule::class,
        'min' => Rules\Min::class,
        'max' => Rules\Max::class,
        'between' => Rules\Between::class,
        'in' => Rules\In::class,
        'not_in' => Rules\NotIn::class,
        'regex' => Rules\Regex::class,
        'alpha' => Rules\Alpha::class,
        'alpha_num' => Rules\AlphaNum::class,
        'alpha_dash' => Rules\AlphaDash::class,
        'url' => Rules\Url::class,
        'ip' => Rules\Ip::class,
        'date' => Rules\Date::class,
        'confirmed' => Rules\Confirmed::class,
        'same' => Rules\Same::class,
        'different' => Rules\Different::class,
        'file' => Rules\FileRule::class,
        'image' => Rules\Image::class,
        'mimes' => Rules\Mimes::class,
        'size' => Rules\Size::class
    ];

    /**
     * Create a new validator instance
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, array<string>|string> $rules Validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $labels Custom field labels
     */
    public function __construct(
        array $data = [],
        array $rules = [],
        array $messages = [],
        array $labels = []
    ) {
        $this->data = $data;
        $this->rules = $this->parseRules($rules);
        $this->messages = $messages;
        $this->labels = $labels;
    }

    /**
     * Create a validator instance
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, array<string>|string> $rules Validation rules
     * @param array<string, string> $messages Custom error messages
     * @param array<string, string> $labels Custom field labels
     * @return static
     */
    public static function make(
        array $data,
        array $rules,
        array $messages = [],
        array $labels = []
    ): static {
        return new static($data, $rules, $messages, $labels);
    }

    /**
     * Validate the data
     *
     * @return array<string, mixed> Validated data
     * @throws ValidationException
     */
    public function validate(): array
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors, $this->data);
        }

        return $this->getValidatedData();
    }

    /**
     * Check if validation passes
     *
     * @return bool True if validation passes
     */
    public function passes(): bool
    {
        try {
            $this->validate();
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Check if validation fails
     *
     * @return bool True if validation fails
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get validation errors
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        if (empty($this->errors)) {
            $this->passes(); // Run validation to populate errors
        }

        return $this->errors;
    }

    /**
     * Get validated data (only fields with rules)
     *
     * @return array<string, mixed>
     */
    public function getValidatedData(): array
    {
        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if (Arr::has($this->data, $field)) {
                Arr::set($validated, $field, Arr::get($this->data, $field));
            }
        }

        return $validated;
    }

    /**
     * Register a custom validation rule
     *
     * @param string $name Rule name
     * @param RuleInterface $rule Rule instance
     * @return void
     */
    public static function extend(string $name, RuleInterface $rule): void
    {
        static::$customRules[$name] = $rule;
    }

    /**
     * Register multiple custom rules
     *
     * @param array<string, RuleInterface> $rules Custom rules
     * @return void
     */
    public static function extendMany(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            static::extend($name, $rule);
        }
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name
     * @param array<string> $rules Field rules
     * @return void
     */
    protected function validateField(string $field, array $rules): void
    {
        $value = Arr::get($this->data, $field);

        foreach ($rules as $rule) {
            if (!$this->validateRule($field, $value, $rule)) {
                // Skip remaining rules if validation fails for this field
                break;
            }
        }
    }

    /**
     * Validate a single rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule string
     * @return bool True if validation passes
     */
    protected function validateRule(string $field, mixed $value, string $rule): bool
    {
        [$ruleName, $parameters] = $this->parseRule($rule);

        $ruleInstance = $this->getRuleInstance($ruleName);

        if (!$ruleInstance->passes($value, $parameters, $this->data)) {
            $this->addError($field, $ruleName, $parameters, $ruleInstance);
            return false;
        }

        return true;
    }

    /**
     * Get rule instance
     *
     * @param string $ruleName Rule name
     * @return RuleInterface
     * @throws InvalidArgumentException
     */
    protected function getRuleInstance(string $ruleName): RuleInterface
    {
        // Check custom rules first
        if (isset(static::$customRules[$ruleName])) {
            return static::$customRules[$ruleName];
        }

        // Check built-in rules
        if (isset($this->builtinRules[$ruleName])) {
            $ruleClass = $this->builtinRules[$ruleName];
            return new $ruleClass();
        }

        throw new InvalidArgumentException("Validation rule '{$ruleName}' not found");
    }

    /**
     * Add validation error
     *
     * @param string $field Field name
     * @param string $ruleName Rule name
     * @param array<string> $parameters Rule parameters
     * @param RuleInterface $ruleInstance Rule instance
     * @return void
     */
    protected function addError(string $field, string $ruleName, array $parameters, RuleInterface $ruleInstance): void
    {
        $customKey = "{$field}.{$ruleName}";
        
        if (isset($this->messages[$customKey])) {
            $message = $this->messages[$customKey];
        } else {
            $fieldLabel = $this->labels[$field] ?? $this->getFieldLabel($field);
            $message = $ruleInstance->message($fieldLabel, $parameters);
        }

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Parse validation rules
     *
     * @param array<string, array<string>|string> $rules Raw rules
     * @return array<string, array<string>>
     */
    protected function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $parsed[$field] = $fieldRules;
        }

        return $parsed;
    }

    /**
     * Parse a single rule
     *
     * @param string $rule Rule string
     * @return array{0: string, 1: array<string>}
     */
    protected function parseRule(string $rule): array
    {
        if (strpos($rule, ':') === false) {
            return [$rule, []];
        }

        [$ruleName, $parameterString] = explode(':', $rule, 2);
        
        // Special handling for regex rule - don't split on commas
        if ($ruleName === 'regex') {
            return [$ruleName, [trim($parameterString)]];
        }
        
        $parameters = explode(',', $parameterString);

        return [$ruleName, array_map('trim', $parameters)];
    }

    /**
     * Get field label for error messages
     *
     * @param string $field Field name
     * @return string Field label
     */
    protected function getFieldLabel(string $field): string
    {
        // Convert dot notation to readable format
        $label = str_replace('.', ' ', $field);
        
        // Convert snake_case to Title Case
        return Str::title(str_replace('_', ' ', $label));
    }
}
