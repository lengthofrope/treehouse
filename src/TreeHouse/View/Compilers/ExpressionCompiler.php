<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

/**
 * Expression Compiler for TreeHouse Templates
 * 
 * Compiles template expressions to PHP code:
 * - Converts dot notation to thGetProperty() calls
 * - Handles boolean logic operators
 * - Preserves framework helpers
 * - Validates expressions before compilation
 *
 * @package LengthOfRope\TreeHouse\View\Compilers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ExpressionCompiler
{
    private ExpressionValidator $validator;

    public function __construct(ExpressionValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Compile an expression to PHP code
     *
     * @param string $expression The template expression
     * @return string Compiled PHP expression
     * @throws \InvalidArgumentException If expression is invalid
     */
    public function compileExpression(string $expression): string
    {
        $expression = trim($expression);

        // Validate expression first
        if (!$this->validator->validate($expression)) {
            throw new \InvalidArgumentException(
                $this->validator->getValidationError($expression)
            );
        }

        // Compile the expression
        return $this->doCompile($expression);
    }

    /**
     * Perform the actual compilation
     *
     * @param string $expression
     * @return string
     */
    private function doCompile(string $expression): string
    {
        // Convert dot notation to thGetProperty() calls
        $compiled = $this->compileDotNotation($expression);
        
        // Handle bare variable names (convert to $variable)
        $compiled = $this->compileBareVariables($compiled);

        // Handle boolean operators (already valid PHP)
        // No conversion needed for &&, ||, !

        return $compiled;
    }

    /**
     * Convert dot notation to thGetProperty() calls
     *
     * @param string $expression
     * @return string
     */
    private function compileDotNotation(string $expression): string
    {
        // Pattern to match variable.property chains
        // Matches: $user.name, user.name, $user.profile.email, user.profile.email, etc.
        $pattern = '/(\$?)(\w+)((?:\.\w+)+)/';

        return preg_replace_callback($pattern, function ($matches) {
            $hasPrefix = !empty($matches[1]);
            $variable = '$' . $matches[2]; // Always add $ prefix for main variable
            $properties = substr($matches[3], 1); // Remove leading dot
            $propertyChain = explode('.', $properties);

            // Build nested thGetProperty calls
            $result = $variable;
            foreach ($propertyChain as $property) {
                $result = "thGetProperty({$result}, '{$property}')";
            }

            return $result;
        }, $expression);
    }

    /**
     * Convert bare variable names to PHP variables
     *
     * @param string $expression
     * @return string
     */
    private function compileBareVariables(string $expression): string
    {
        // Pattern to match bare variable names that aren't already prefixed with $
        // and aren't part of function calls or already processed dot notation
        // This should match standalone words that look like variables but exclude thGetProperty calls
        $pattern = '/\b(?<![\$\w])([a-zA-Z_][a-zA-Z0-9_]*)\b(?!\s*\(|::|->|\')/';
        
        return preg_replace_callback($pattern, function ($matches) {
            $word = $matches[1];
            
            // Skip PHP keywords, constants, and framework helpers
            $skipWords = [
                'true', 'false', 'null', 'and', 'or', 'xor', 'not',
                'if', 'else', 'elseif', 'endif', 'while', 'endwhile',
                'for', 'endfor', 'foreach', 'endforeach', 'switch',
                'case', 'default', 'break', 'continue', 'function',
                'return', 'class', 'interface', 'trait', 'extends',
                'implements', 'public', 'private', 'protected',
                'static', 'final', 'abstract', 'const', 'var',
                'Str', 'Carbon', 'Arr', 'Collection', 'thGetProperty'
            ];
            
            if (in_array($word, $skipWords, true)) {
                return $word;
            }
            
            // Convert to PHP variable
            return '$' . $word;
        }, $expression);
    }

    /**
     * Compile a conditional expression for th:if
     *
     * @param string $expression
     * @return string
     */
    public function compileConditional(string $expression): string
    {
        // Strip braces only if it's a simple variable expression (no operators or spaces)
        $cleanExpression = trim($expression);
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}$/', $cleanExpression, $matches)) {
            $cleanExpression = trim($matches[1]);
        }
        
        $compiled = $this->compileExpression($cleanExpression);
        
        // Wrap in boolean context to ensure proper evaluation
        return "({$compiled})";
    }

    /**
     * Compile a text expression for th:text
     *
     * @param string $expression
     * @return string
     */
    public function compileText(string $expression): string
    {
        // Strip braces only if it's a simple variable expression (no operators or spaces)
        $cleanExpression = trim($expression);
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}$/', $cleanExpression, $matches)) {
            $cleanExpression = trim($matches[1]);
        }
        
        $compiled = $this->compileExpression($cleanExpression);
        
        // Ensure output is escaped
        return "htmlspecialchars((string)({$compiled}), ENT_QUOTES, 'UTF-8')";
    }

    /**
     * Compile a raw expression for th:html
     *
     * @param string $expression
     * @return string
     */
    public function compileRaw(string $expression): string
    {
        // Strip braces only if it's a simple variable expression (no operators or spaces)
        $cleanExpression = trim($expression);
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}$/', $cleanExpression, $matches)) {
            $cleanExpression = trim($matches[1]);
        }
        
        $compiled = $this->compileExpression($cleanExpression);
        
        // No escaping for raw output
        return "(string)({$compiled})";
    }

    /**
     * Compile mixed brace expressions with text for attributes
     *
     * @param string $content
     * @return string
     */
    public function compileMixedText(string $content): string
    {
        // Handle mixed brace expressions and text (like "{users.john.name} Avatar")
        $compiled = preg_replace_callback('/\{([^}]+)\}/', function ($matches) {
            $expression = trim($matches[1]);
            $compiledExpression = $this->compileExpression($expression);
            return "' . htmlspecialchars((string)({$compiledExpression}), ENT_QUOTES, 'UTF-8') . '";
        }, $content);
        
        // If we had replacements, wrap in concatenation
        if ($compiled !== $content) {
            // Remove leading/trailing concatenation if content starts/ends with replacement
            $compiled = "'" . $compiled . "'";
            $compiled = str_replace("'' . ", "", $compiled);
            $compiled = str_replace(" . ''", "", $compiled);
            
            // Clean up empty quotes
            if ($compiled === "''") {
                return "''";
            }
        } else {
            // No brace expressions, just a literal string
            $compiled = "'" . addslashes($content) . "'";
        }
        
        return $compiled;
    }

    /**
     * Compile an iteration expression for th:repeat
     *
     * @param string $expression
     * @return array{iterable: string, key: string|null, value: string}
     */
    public function compileIteration(string $expression): array
    {
        // Parse different repeat formats:
        // "item : items" -> foreach($items as $item)
        // "key,item : items" -> foreach($items as $key => $item)
        // "item in items" -> foreach($items as $item)
        // "item items" -> foreach($items as $item) [legacy format]
        // "key,item items" -> foreach($items as $key => $item) [legacy format]

        if (preg_match('/^(\w+)\s*:\s*(.+)$/', $expression, $matches)) {
            // Simple format: item : items
            $value = '$' . $matches[1];
            $iterableExpr = $this->stripBraces(trim($matches[2]));
            $iterable = $this->compileExpression($iterableExpr);
            return ['iterable' => $iterable, 'key' => null, 'value' => $value];
        }

        if (preg_match('/^(\w+),\s*(\w+)\s*:\s*(.+)$/', $expression, $matches)) {
            // Key-value format: key,item : items
            $key = '$' . $matches[1];
            $value = '$' . $matches[2];
            $iterableExpr = $this->stripBraces(trim($matches[3]));
            $iterable = $this->compileExpression($iterableExpr);
            return ['iterable' => $iterable, 'key' => $key, 'value' => $value];
        }

        if (preg_match('/^(\w+)\s+in\s+(.+)$/', $expression, $matches)) {
            // Alternative format: item in items
            $value = '$' . $matches[1];
            $iterableExpr = $this->stripBraces(trim($matches[2]));
            $iterable = $this->compileExpression($iterableExpr);
            return ['iterable' => $iterable, 'key' => null, 'value' => $value];
        }

        // Legacy formats for backward compatibility
        if (preg_match('/^(\w+),\s*(\w+)\s+(.+)$/', $expression, $matches)) {
            // Key-value legacy format: key,item items
            $key = '$' . $matches[1];
            $value = '$' . $matches[2];
            $iterableExpr = $this->stripBraces(trim($matches[3]));
            $iterable = $this->compileExpression($iterableExpr);
            return ['iterable' => $iterable, 'key' => $key, 'value' => $value];
        }

        if (preg_match('/^(\w+)\s+(.+)$/', $expression, $matches)) {
            // Simple legacy format: item items
            $value = '$' . $matches[1];
            $iterableExpr = $this->stripBraces(trim($matches[2]));
            $iterable = $this->compileExpression($iterableExpr);
            return ['iterable' => $iterable, 'key' => null, 'value' => $value];
        }

        throw new \InvalidArgumentException("Invalid repeat expression: {$expression}");
    }

    /**
     * Strip braces from simple variable expressions
     *
     * @param string $expression
     * @return string
     */
    private function stripBraces(string $expression): string
    {
        // Strip braces only if it's a simple variable expression (no operators or spaces)
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}$/', $expression, $matches)) {
            return trim($matches[1]);
        }
        
        return $expression;
    }
}