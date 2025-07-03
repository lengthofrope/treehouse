<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

use RuntimeException;

/**
 * Validates template expressions for security and syntax safety
 */
class ExpressionValidator
{
    /**
     * Framework helper classes that are allowed
     */
    protected array $allowedHelpers = ['Str', 'Carbon', 'Arr', 'Collection', 'Uuid'];

    /**
     * Validate brace expressions for security and syntax safety
     */
    public function isValidBraceExpression(string $expression): bool
    {
        $expr = trim($expression);
        
        // ❌ Reject any PHP tags
        if (preg_match('/<\?php|\?>/', $expr)) {
            return false;
        }
        
        // ❌ Reject native PHP functions (specific function names)
        if (preg_match('/\b(strlen|strtoupper|array_|count|implode|explode|eval|exec|system|shell_exec|passthru|file_get_contents|fopen|fwrite)\s*\(/', $expr)) {
            return false;
        }
        
        // ❌ Reject complex arithmetic (multiplication, division, modulo, but allow addition)
        if (preg_match('/[\*\/\%]/', $expr)) {
            return false;
        }
        
        // ❌ Reject complex numeric operations with decimal points
        if (preg_match('/\d+\.\d+\s*[\*\/\%]/', $expr)) {
            return false;
        }
        
        // ✅ Allow clean dot notation: user.name, config.db.host
        if (preg_match('/^[a-zA-Z_]\w*\.[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*$/', $expr)) {
            return true;
        }
        
        // ✅ Allow simple variables: user, title
        if (preg_match('/^[a-zA-Z_]\w*$/', $expr)) {
            return true;
        }
        
        // ✅ Allow string literals: 'text', "text"
        if (preg_match('/^[\'"][^\'"]*[\'"]$/', $expr)) {
            return true;
        }
        
        // ✅ Allow framework helper calls: Str::upper(user.name)
        foreach ($this->allowedHelpers as $helper) {
            if (preg_match("/^{$helper}::\w+\([^)]*\)$/", $expr)) {
                return true;
            }
        }
        
        // ✅ Allow basic safe operators with logical operators
        if ($this->hasOnlyBasicOperators($expr)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if expression contains only basic safe operators including logical operators
     */
    protected function hasOnlyBasicOperators(string $expr): bool
    {
        // Allow basic safe operators including logical operators
        $allowedOperators = ['+', '==', '!=', '>', '<', '>=', '<=', '&&', '||', '!'];
        
        // Check if expression contains only dot notation, strings, parentheses, and basic operators
        $cleanExpr = preg_replace('/[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*|[\'"][^\'"]*[\'"]/', 'VAR', $expr);
        $cleanExpr = preg_replace('/\s+/', ' ', trim($cleanExpr));
        
        // Valid patterns: "VAR + VAR", "VAR == VAR", "VAR && VAR", "!(VAR)", etc.
        $pattern = '/^(!?\(?VAR\)?)(\s*(\+|==|!=|>=?|<=?|&&|\|\|)\s*(!?\(?VAR\)?))*$/';
        if (preg_match($pattern, $cleanExpr)) {
            // Ensure no complex operators are present
            if (!preg_match('/\*|\/|%|<<|>>|\^|&[^&]|\|[^\|]/', $expr)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if expression looks like an unsafe expression that should be blocked
     */
    public function looksLikeUnsafeExpression(string $expression): bool
    {
        $expr = trim($expression);
        
        // Check for PHP tags
        if (preg_match('/<\?php|\?>/', $expr)) {
            return true;
        }
        
        // Check for native PHP functions
        if (preg_match('/\b(strlen|strtoupper|array_|count|implode|explode|eval|exec|system|shell_exec|passthru|file_get_contents|fopen|fwrite)\s*\(/', $expr)) {
            return true;
        }
        
        // Check for complex arithmetic (multiplication, division, modulo with decimal points)
        if (preg_match('/\d+\.\d+\s*[\*\/\%]/', $expr)) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate expression and throw exception if invalid
     */
    public function validateOrThrow(string $expression): void
    {
        if (!$this->isValidBraceExpression($expression)) {
            throw new RuntimeException("Invalid template expression: {{$expression}}. Only dot notation, basic operators, and framework helpers are allowed.");
        }
    }
}