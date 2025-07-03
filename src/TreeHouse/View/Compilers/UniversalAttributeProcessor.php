<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers;

use DOMElement;
use RuntimeException;

/**
 * Processes universal th: attributes (th:href, th:data-*, etc.)
 */
class UniversalAttributeProcessor
{
    protected ExpressionCompiler $compiler;
    protected array $booleanAttributePlaceholders = [];

    /**
     * Boolean HTML attributes
     */
    protected array $booleanAttributes = [
        'selected', 'checked', 'disabled', 'readonly', 'multiple', 'autofocus',
        'autoplay', 'controls', 'defer', 'hidden', 'loop', 'open', 'required',
        'reversed', 'scoped', 'seamless', 'itemscope', 'novalidate', 'allowfullscreen',
        'formnovalidate', 'default', 'inert', 'truespeed'
    ];

    public function __construct(ExpressionCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Process dynamic attribute with universal th: prefix support
     */
    public function processDynamicAttribute(DOMElement $node, string $attrName, string $expression): void
    {
        // Handle different types of expressions
        if (str_contains($expression, '{')) {
            // Brace syntax: "static text {$dynamic} more text"
            if ($this->isBooleanAttribute($attrName)) {
                // For boolean attributes, compile the expression inside braces directly
                $compiledValue = $this->compiler->compileBooleanBraceExpression($expression);
            } else {
                $compiledValue = $this->compiler->compileBraceExpressions($expression);
            }
        } elseif ($this->compiler->isDotNotation($expression)) {
            // Dot notation: "user.name"
            $compiledValue = $this->compiler->compileExpression($expression);
        } elseif ($this->compiler->isStaticText($expression)) {
            // Static text: just output as string literal
            $compiledValue = "'" . addslashes($expression) . "'";
        } else {
            // Dynamic PHP expression: "$user['id']"
            $compiledValue = $this->compiler->compileExpression($expression);
        }
        
        // Handle boolean attributes (selected, checked, disabled, etc.)
        if ($this->isBooleanAttribute($attrName)) {
            // For boolean attributes, we need special handling since DOM normalizes them
            // Use a temporary non-boolean attribute that we'll replace in generatePhp
            $placeholder = "___TH_BOOL_" . uniqid() . "___";
            $this->booleanAttributePlaceholders[$placeholder] = [
                'condition' => $compiledValue,
                'attribute' => $attrName
            ];
            // Use a temporary attribute name that won't be normalized
            $node->setAttribute("data-th-bool-{$attrName}", $placeholder);
        } else {
            // For regular attributes, handle null values by not outputting anything
            $php = "<?php \$val = {$compiledValue}; if (\$val !== null) echo \$val; ?>";
            $node->setAttribute($attrName, $php);
        }
    }

    /**
     * Check if an attribute is a boolean attribute
     */
    protected function isBooleanAttribute(string $attrName): bool
    {
        return in_array(strtolower($attrName), $this->booleanAttributes);
    }

    /**
     * Get boolean attribute placeholders for post-processing
     */
    public function getBooleanAttributePlaceholders(): array
    {
        return $this->booleanAttributePlaceholders;
    }

    /**
     * Reset boolean attribute placeholders
     */
    public function resetBooleanAttributePlaceholders(): void
    {
        $this->booleanAttributePlaceholders = [];
    }

    /**
     * Process boolean attribute placeholders in generated HTML
     */
    public function processBooleanAttributePlaceholders(string $html): string
    {
        // Process boolean attribute placeholders
        foreach ($this->booleanAttributePlaceholders as $placeholder => $config) {
            // Find and replace the temporary data-th-bool-* attributes
            $tempAttr = "data-th-bool-{$config['attribute']}=\"{$placeholder}\"";
            $replacement = "<?php if ({$config['condition']}) echo '{$config['attribute']}'; ?>";
            $html = str_replace($tempAttr, $replacement, $html);
        }
        
        return $html;
    }
}