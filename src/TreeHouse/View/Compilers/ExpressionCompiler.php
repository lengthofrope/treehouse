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
 * @since   2.0.0
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
            $variable = ($hasPrefix ? '$' : '$') . $matches[2]; // Always add $ prefix
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
     * Compile a conditional expression for th:if
     *
     * @param string $expression
     * @return string
     */
    public function compileConditional(string $expression): string
    {
        $compiled = $this->compileExpression($expression);
        
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
        $compiled = $this->compileExpression($expression);
        
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
        $compiled = $this->compileExpression($expression);
        
        // No escaping for raw output
        return "(string)({$compiled})";
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

        if (preg_match('/^(\w+)\s*:\s*(.+)$/', $expression, $matches)) {
            // Simple format: item : items
            $value = '$' . $matches[1];
            $iterable = $this->compileExpression($matches[2]);
            return ['iterable' => $iterable, 'key' => null, 'value' => $value];
        }

        if (preg_match('/^(\w+),\s*(\w+)\s*:\s*(.+)$/', $expression, $matches)) {
            // Key-value format: key,item : items
            $key = '$' . $matches[1];
            $value = '$' . $matches[2];
            $iterable = $this->compileExpression($matches[3]);
            return ['iterable' => $iterable, 'key' => $key, 'value' => $value];
        }

        if (preg_match('/^(\w+)\s+in\s+(.+)$/', $expression, $matches)) {
            // Alternative format: item in items
            $value = '$' . $matches[1];
            $iterable = $this->compileExpression($matches[2]);
            return ['iterable' => $iterable, 'key' => null, 'value' => $value];
        }

        throw new \InvalidArgumentException("Invalid repeat expression: {$expression}");
    }
}