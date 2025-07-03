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
 * @since   2.0.0
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
     * Blocked operators
     */
    private const BLOCKED_OPERATORS = [
        // Arithmetic (except + which is allowed for string concatenation)
        '-', '*', '/', '%',
        // Comparison
        '==', '!=', '<', '>', '<=', '>=', '===', '!==',
        // Assignment
        '=', '+=', '-=', '*=', '/=', '%=', '.=',
        // Bitwise
        '&', '|', '^', '<<', '>>', '~'
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
        
        // Check for blocked operators
        foreach (self::BLOCKED_OPERATORS as $operator) {
            if (strpos($cleaned, $operator) !== false) {
                // Special case: allow != in boolean context but not comparison
                if ($operator === '!=' && $this->isInBooleanContext($cleaned, $operator)) {
                    continue;
                }
                return false;
            }
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

        // Remove framework helpers
        foreach (self::ALLOWED_HELPERS as $helper) {
            $remaining = preg_replace('/\b' . preg_quote($helper, '/') . '\w+\([^)]*\)/', '', $remaining);
        }

        // Remove variables with dot notation (with or without $ prefix)
        $remaining = preg_replace('/\$?\w+(\.\w+)*/', '', $remaining);

        // Remove method calls
        $remaining = preg_replace('/\w+\([^)]*\)/', '', $remaining);

        // Remove boolean operators
        foreach (self::ALLOWED_BOOLEAN_OPERATORS as $operator) {
            $remaining = str_replace($operator, '', $remaining);
        }

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
     * Check if operator is in boolean context
     *
     * @param string $expression
     * @param string $operator
     * @return bool
     */
    private function isInBooleanContext(string $expression, string $operator): bool
    {
        // For now, be conservative and don't allow != at all
        // This can be refined later if needed
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