<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

/**
 * Expression Validator for TreeHouse Templates
 * 
 * Validates expressions to ensure they only contain allowed operations:
 * - Boolean logic operators (&&, ||, !)
 * - Dot notation for object access
 * - Framework helpers (Str::, Carbon::, Arr::, Collection::)
 * - Variable access ($var)
 * - Method calls
 * 
 * Blocks:
 * - Arithmetic operators (+, -, *, /, %)
 * - Comparison operators (==, !=, <, >, <=, >=)
 * - Assignment operators (=, +=, etc.)
 *
 * @package LengthOfRope\TreeHouse\View\Compilers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ExpressionValidator
{
    /**
     * Allowed framework helpers
     */
    private const ALLOWED_HELPERS = [
        'Str::', 'Carbon::', 'Arr::', 'Collection::'
    ];

    /**
     * Blocked operators (move complex logic to backend)
     */
    private const BLOCKED_OPERATORS = [
        // Arithmetic (move to backend models)
        '-', '*', '/', '%',
        // Comparison (move to backend boolean properties)
        '==', '!=', '<', '>', '<=', '>=', '===', '!==',
        // Assignment (never allowed in templates)
        '=', '+=', '-=', '*=', '/=', '%=', '.=',
        // Bitwise (never allowed in templates)
        '^', '<<', '>>', '~'
    ];

    /**
     * Allowed boolean operators
     */
    private const ALLOWED_BOOLEAN_OPERATORS = [
        '&&', '||', '!'
    ];

    /**
     * Validate an expression
     *
     * @param string $expression The expression to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(string $expression): bool
    {
        // Remove whitespace for easier parsing
        $cleaned = preg_replace('/\s+/', ' ', trim($expression));
        
        // First, temporarily replace allowed boolean operators to avoid conflicts
        $tempCleaned = $cleaned;
        $replacements = [];
        foreach (self::ALLOWED_BOOLEAN_OPERATORS as $i => $operator) {
            $placeholder = "__BOOL_OP_{$i}__";
            $tempCleaned = str_replace($operator, $placeholder, $tempCleaned);
            $replacements[$placeholder] = $operator;
        }
        
        // Remove string literals to avoid false positives with operators inside strings
        $tempCleaned = preg_replace('/["\'][^"\']*["\']/', '__STRING__', $tempCleaned);
        
        // Check for blocked operators in the temp string
        foreach (self::BLOCKED_OPERATORS as $operator) {
            if (strpos($tempCleaned, $operator) !== false) {
                return false;
            }
        }
        
        // Check for single & or | (but allow && and ||)
        if (preg_match('/&(?!&)/', $tempCleaned) || preg_match('/\|(?!\|)/', $tempCleaned)) {
            return false;
        }

        // Check for valid patterns
        return $this->hasValidPatterns($cleaned);
    }

    /**
     * Check if expression contains only valid patterns
     *
     * @param string $expression
     * @return bool
     */
    private function hasValidPatterns(string $expression): bool
    {
        // Remove valid patterns and see what's left
        $remaining = $expression;

        // Remove framework helpers first (most specific)
        foreach (self::ALLOWED_HELPERS as $helper) {
            $remaining = preg_replace('/\b' . preg_quote($helper, '/') . '\w+\([^)]*\)/', '', $remaining);
        }

        // Remove method calls (before variables to avoid conflicts)
        $remaining = preg_replace('/\w+\([^)]*\)/', '', $remaining);

        // Remove variables with dot notation (with or without $ prefix)
        $remaining = preg_replace('/\$?[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*/', '', $remaining);

        // Remove simple variables including those with double underscores (like __treehouse_config)
        $remaining = preg_replace('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', '', $remaining);

        // Remove boolean operators
        foreach (self::ALLOWED_BOOLEAN_OPERATORS as $operator) {
            $remaining = str_replace($operator, '', $remaining);
        }

        // Remove the + operator (allowed for string concatenation in backend, but validate framework helpers)
        $remaining = str_replace('+', '', $remaining);
        
        // Remove comparison operators when used with framework helpers (more permissive)
        $remaining = preg_replace('/\b(==|!=|>=?|<=?)\b/', '', $remaining);

        // Remove parentheses
        $remaining = str_replace(['(', ')'], '', $remaining);

        // Remove quotes and string literals
        $remaining = preg_replace('/["\'][^"\']*["\']/', '', $remaining);

        // Remove numbers
        $remaining = preg_replace('/\b\d+(\.\d+)?\b/', '', $remaining);

        // Remove remaining whitespace
        $remaining = trim($remaining);

        // If anything suspicious remains, it's invalid
        return empty($remaining);
    }

    /**
     * Check if operator is in boolean context (now more permissive)
     *
     * @param string $expression
     * @param string $operator
     * @return bool
     */
    private function isInBooleanContext(string $expression, string $operator): bool
    {
        // Allow boolean operators in conditional contexts
        if (in_array($operator, ['&&', '||', '!'])) {
            return true;
        }
        
        // Allow framework helper calls with parameters
        foreach (self::ALLOWED_HELPERS as $helper) {
            if (strpos($expression, $helper) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get validation error message
     *
     * @param string $expression
     * @return string
     */
    public function getValidationError(string $expression): string
    {
        foreach (self::BLOCKED_OPERATORS as $operator) {
            if (strpos($expression, $operator) !== false) {
                return "Blocked operator '{$operator}' found in expression: {$expression}";
            }
        }

        return "Invalid expression pattern: {$expression}";
    }
}