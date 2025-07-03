<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

/**
 * Compiles template expressions into PHP code
 */
class ExpressionCompiler
{
    protected ExpressionValidator $validator;

    /**
     * Support classes auto-imported in templates
     */
    protected array $supportClasses = [
        'Collection' => 'LengthOfRope\\TreeHouse\\Support\\Collection',
        'Arr' => 'LengthOfRope\\TreeHouse\\Support\\Arr',
        'Str' => 'LengthOfRope\\TreeHouse\\Support\\Str',
        'Carbon' => 'LengthOfRope\\TreeHouse\\Support\\Carbon',
        'Uuid' => 'LengthOfRope\\TreeHouse\\Support\\Uuid',
    ];

    public function __construct(ExpressionValidator $validator = null)
    {
        $this->validator = $validator ?? new ExpressionValidator();
    }

    /**
     * Compile expression with Support class integration
     */
    public function compileExpression(string $expression): string
    {
        // First, handle automatic $ prefixing for simple variable names
        $expression = $this->addVariablePrefix($expression);
        
        // Then, handle dot notation conversion
        $expression = $this->compileDotNotation($expression);
        
        // Handle Support class static calls
        foreach ($this->supportClasses as $alias => $class) {
            $expression = preg_replace(
                '/\b' . $alias . '::/i',
                '\\' . $class . '::',
                $expression
            );
        }
        
        // Handle collection method calls on arrays
        $expression = preg_replace_callback(
            '/\$(\w+)->(\w+)\(([^)]*)\)/',
            function ($matches) {
                $var = $matches[1];
                $method = $matches[2];
                $args = $matches[3];
                
                // Check if this is a Collection method
                $collectionMethods = ['count', 'isEmpty', 'isNotEmpty', 'first', 'last', 'take', 'skip', 'where', 'filter', 'map', 'sortBy', 'groupBy', 'pluck'];
                
                if (in_array($method, $collectionMethods)) {
                    return "(is_array(\${$var}) ? thCollect(\${$var})->{$method}({$args}) : \${$var}->{$method}({$args}))";
                }
                
                return $matches[0];
            },
            $expression
        );
        
        return $expression;
    }

    /**
     * Compile brace expressions: "text {expr} more {expr2}" with validation
     */
    public function compileBraceExpressions(string $value): string
    {
        $result = preg_replace_callback(
            '/\{([^}]+)\}/',
            function($matches) {
                $expr = trim($matches[1]);
                
                // Validate brace expression first
                $this->validator->validateOrThrow($expr);
                
                // Only convert string concatenation in specific contexts, not for th:if
                $expr = $this->compileDotNotation($expr);
                $expr = $this->compileExpression($expr);
                return "' . (($expr) ?? '') . '";
            },
            "'" . addslashes($value) . "'"
        );
        
        // Clean up empty string concatenations
        $result = preg_replace("/^'' \. /", "", $result);
        $result = preg_replace("/ \. ''$/", "", $result);
        
        // Additional cleanup - remove unnecessary concatenations at start/end
        $result = preg_replace("/^'' \. \(/", "(", $result);
        $result = preg_replace("/\) \. ''$/", ")", $result);
        
        return $result;
    }

    /**
     * Compile brace expressions for boolean attributes (no string wrapping)
     */
    public function compileBooleanBraceExpression(string $value): string
    {
        // For boolean attributes, we only expect a single brace expression
        if (preg_match('/^\{([^}]+)\}$/', $value, $matches)) {
            $expr = trim($matches[1]);
            
            // Validate the boolean expression first
            $this->validator->validateOrThrow($expr);
            
            $expr = $this->compileDotNotation($expr);
            $expr = $this->compileExpression($expr);
            return $expr;
        }
        
        // Fallback to regular compilation
        return $this->compileExpression($value);
    }

    /**
     * Convert dot notation to thGetProperty calls: user.name → thGetProperty($user, 'name')
     */
    protected function compileDotNotation(string $expression): string
    {
        // Convert user.name.first → thGetProperty(thGetProperty($user, 'name'), 'first')
        // Also handles $user.name.first → thGetProperty(thGetProperty($user, 'name'), 'first') (when $ prefix already exists)
        return preg_replace_callback(
            '/\$?([a-zA-Z_]\w*)\.([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*)\b/',
            function($matches) {
                $var = $matches[1];
                $path = $matches[2];
                $keys = explode('.', $path);
                
                // Build nested thGetProperty calls
                $result = '$' . $var;
                foreach ($keys as $key) {
                    $result = "thGetProperty({$result}, '{$key}')";
                }
                
                return $result;
            },
            $expression
        );
    }

    /**
     * Add $ prefix to simple variable names in th: commands
     */
    protected function addVariablePrefix(string $expression): string
    {
        // Don't process if expression already contains $ (already has variables)
        // or if it contains PHP operators/functions that indicate it's already PHP code
        if (str_contains($expression, '$') ||
            preg_match('/\(|\)|->|::|==|!=|&&|\|\||[\[\]]|\+|\-|\*|\//', $expression)) {
            return $expression;
        }
        
        // Don't process quoted strings
        if (preg_match('/^[\'"][^\'"]*[\'"]$/', trim($expression))) {
            return $expression;
        }
        
        // Don't process numeric values or boolean literals
        if (preg_match('/^(true|false|null|\d+(\.\d+)?)$/i', trim($expression))) {
            return $expression;
        }
        
        // Don't process Support class calls (Str::something, Arr::something, etc.)
        foreach ($this->supportClasses as $alias => $class) {
            if (str_starts_with($expression, $alias . '::')) {
                return $expression;
            }
        }
        
        // Handle simple variable names (just letters, numbers, underscores)
        // Examples: "title" -> "$title", "user_name" -> "$user_name"
        if (preg_match('/^[a-zA-Z_]\w*$/', trim($expression))) {
            return '$' . trim($expression);
        }
        
        // Handle simple dot notation without $ prefix
        // Examples: "user.name" -> "$user.name" (will be further processed by compileDotNotation)
        if (preg_match('/^[a-zA-Z_]\w*\.[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*$/', trim($expression))) {
            return '$' . trim($expression);
        }
        
        return $expression;
    }

    /**
     * Convert + operators to PHP concatenation . for string concatenation
     */
    protected function convertStringConcatenation(string $expression): string
    {
        // Be very conservative - only convert + to . when we're sure it's string concatenation
        // Only convert when we have quoted strings on at least one side
        
        // Pattern 1: variable/property + 'string'
        $expression = preg_replace(
            '/((?:thGetProperty\([^)]+\)|\$\w+))\s*\+\s*(\'[^\']*\'|"[^"]*")/',
            '$1 . $2',
            $expression
        );
        
        // Pattern 2: 'string' + variable/property
        $expression = preg_replace(
            '/(\'[^\']*\'|"[^"]*")\s*\+\s*((?:thGetProperty\([^)]+\)|\$\w+))/',
            '$1 . $2',
            $expression
        );
        
        // Pattern 3: 'string' + 'string'
        $expression = preg_replace(
            '/(\'[^\']*\'|"[^"]*")\s*\+\s*(\'[^\']*\'|"[^"]*")/',
            '$1 . $2',
            $expression
        );
        
        return $expression;
    }

    /**
     * Check if expression is dot notation: user.name, config.db.host, etc.
     */
    public function isDotNotation(string $expression): bool
    {
        return (bool) preg_match('/^[a-zA-Z_]\w*\.[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*$/', $expression);
    }

    /**
     * Check if expression is static text (no variables or PHP syntax)
     */
    public function isStaticText(string $expression): bool
    {
        // Check if it contains PHP variables or PHP-specific syntax
        // Allow : and . for CSS and normal text, but not when part of PHP operators
        return !preg_match('/\$|\[|\]|\(|\)|->|::|==|!=|&&|\|\||(\?[^:]*:)/', $expression);
    }

    /**
     * Compile text expression (escaped)
     */
    public function compileTextExpression(string $expression): string
    {
        // Handle brace expressions in text - validation happens in compileBraceExpressions
        if (str_contains($expression, '{')) {
            $compiledValue = $this->compileBraceExpressions($expression);
        } else {
            // For non-brace expressions, validate if they look unsafe
            if ($this->validator->looksLikeUnsafeExpression($expression)) {
                throw new \RuntimeException("Invalid template expression: {$expression}. Only dot notation, basic operators, and framework helpers are allowed.");
            }
            $compiledValue = $this->compileExpression($expression);
        }
        
        return "<?php echo thEscape({$compiledValue}); ?>";
    }

    /**
     * Compile HTML expression (raw)
     */
    public function compileHtmlExpression(string $expression): string
    {
        // Handle brace expressions in HTML
        if (str_contains($expression, '{')) {
            $compiledValue = $this->compileBraceExpressions($expression);
        } else {
            // For non-brace expressions, validate if they look unsafe
            if ($this->validator->looksLikeUnsafeExpression($expression)) {
                throw new \RuntimeException("Invalid template expression: {$expression}. Only dot notation, basic operators, and framework helpers are allowed.");
            }
            $compiledValue = $this->compileExpression($expression);
        }
        
        return "<?php echo thRaw({$compiledValue}); ?>";
    }

    /**
     * Compile raw expression (unescaped, replaces entire element)
     */
    public function compileRawExpression(string $expression): string
    {
        // Handle brace expressions in raw content
        if (str_contains($expression, '{')) {
            $compiledValue = $this->compileBraceExpressions($expression);
        } else {
            $compiledValue = $this->compileExpression($expression);
        }
        
        return "<?php echo thRaw({$compiledValue}); ?>";
    }
}