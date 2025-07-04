<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:field directive
 * 
 * Handles form field binding with automatic name, id, and value setting
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class FieldProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        $compiledExpression = $this->expressionCompiler->compileExpression($expression);
        
        // Extract field name from expression (e.g., "user.name" -> "name")
        $fieldName = $this->extractFieldName($expression);
        $fieldId = str_replace('.', '_', $expression);
        
        // Set the name attribute
        if (!$node->hasAttribute('name')) {
            $node->setAttribute('name', $fieldName);
        }
        
        // Set the id attribute
        if (!$node->hasAttribute('id')) {
            $node->setAttribute('id', $fieldId);
        }
        
        // Set the value attribute based on input type
        $inputType = $node->getAttribute('type') ?: 'text';
        $tagName = strtolower($node->tagName);
        
        if ($tagName === 'input') {
            $this->handleInputField($node, $inputType, $compiledExpression);
        } elseif ($tagName === 'textarea') {
            $this->handleTextareaField($node, $compiledExpression);
        } elseif ($tagName === 'select') {
            $this->handleSelectField($node, $compiledExpression);
        }
        
        // Remove the th:field attribute
        $node->removeAttribute('th:field');
    }
    
    /**
     * Handle input field value setting
     */
    private function handleInputField(DOMElement $node, string $inputType, string $compiledExpression): void
    {
        switch ($inputType) {
            case 'checkbox':
            case 'radio':
                // For checkboxes and radios, set checked attribute based on value
                $phpCode = "<?php\n";
                $phpCode .= "\$__fieldValue = {$compiledExpression};\n";
                $phpCode .= "\$__inputValue = '" . ($node->getAttribute('value') ?: '1') . "';\n";
                $phpCode .= "if (\$__fieldValue == \$__inputValue) {\n";
                $phpCode .= "    echo ' checked=\"checked\"';\n";
                $phpCode .= "}\n";
                $phpCode .= "?>";
                
                // Use marker for checked attribute
                $marker = "<!--TH_PHP_ATTR:" . base64_encode($phpCode) . "-->";
                $node->setAttribute('__th_checked', $marker);
                break;
                
            default:
                // For text inputs, set value attribute
                if (!$node->hasAttribute('value')) {
                    $phpCode = "<?php echo htmlspecialchars({$compiledExpression} ?? '', ENT_QUOTES, 'UTF-8'); ?>";
                    $marker = "<!--TH_PHP_ATTR:" . base64_encode($phpCode) . "-->";
                    $node->setAttribute('value', $marker);
                }
                break;
        }
    }
    
    /**
     * Handle textarea field content setting
     */
    private function handleTextareaField(DOMElement $node, string $compiledExpression): void
    {
        $phpCode = "<?php echo htmlspecialchars({$compiledExpression} ?? '', ENT_QUOTES, 'UTF-8'); ?>";
        $this->replaceContent($node, $phpCode);
    }
    
    /**
     * Handle select field option selection
     */
    private function handleSelectField(DOMElement $node, string $compiledExpression): void
    {
        // For select fields, we need to mark the correct option as selected
        // This will be handled by JavaScript or by processing option elements
        $phpCode = "<?php\n";
        $phpCode .= "\$__selectValue = {$compiledExpression};\n";
        $phpCode .= "// Store select value for option processing\n";
        $phpCode .= "?>";
        
        $this->insertPhpBefore($node, $phpCode);
        
        // Process option elements
        $options = $node->getElementsByTagName('option');
        foreach ($options as $option) {
            if ($option instanceof DOMElement) {
                $optionValue = $option->getAttribute('value') ?: $option->textContent;
                
                $optionPhp = "<?php\n";
                $optionPhp .= "if (isset(\$__selectValue) && \$__selectValue == '" . addslashes($optionValue) . "') {\n";
                $optionPhp .= "    echo ' selected=\"selected\"';\n";
                $optionPhp .= "}\n";
                $optionPhp .= "?>";
                
                $marker = "<!--TH_PHP_ATTR:" . base64_encode($optionPhp) . "-->";
                $option->setAttribute('__th_selected', $marker);
            }
        }
    }
    
    /**
     * Extract field name from expression
     */
    private function extractFieldName(string $expression): string
    {
        // Extract the last part of dot notation (e.g., "user.profile.name" -> "name")
        $parts = explode('.', $expression);
        return end($parts);
    }
}