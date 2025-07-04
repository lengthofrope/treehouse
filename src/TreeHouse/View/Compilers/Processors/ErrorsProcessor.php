<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:errors directive
 * 
 * Handles validation error display for form fields
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class ErrorsProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse the field expression (e.g., "user.name" or "*" for all errors)
        $fieldName = $this->extractFieldName($expression);
        
        // Generate PHP code to display validation errors
        $phpCode = "<?php\n";
        
        if ($expression === '*' || $expression === 'all') {
            // Display all validation errors
            $phpCode .= "// Display all validation errors\n";
            $phpCode .= "if (isset(\$__errors) && !empty(\$__errors)) {\n";
            $phpCode .= "    foreach (\$__errors as \$__errorField => \$__errorMessages) {\n";
            $phpCode .= "        if (is_array(\$__errorMessages)) {\n";
            $phpCode .= "            foreach (\$__errorMessages as \$__errorMessage) {\n";
            $phpCode .= "                echo htmlspecialchars(\$__errorMessage, ENT_QUOTES, 'UTF-8');\n";
            $phpCode .= "            }\n";
            $phpCode .= "        } else {\n";
            $phpCode .= "            echo htmlspecialchars(\$__errorMessages, ENT_QUOTES, 'UTF-8');\n";
            $phpCode .= "        }\n";
            $phpCode .= "    }\n";
            $phpCode .= "}\n";
        } else {
            // Display errors for specific field
            $phpCode .= "// Display validation errors for field '{$fieldName}'\n";
            $phpCode .= "if (isset(\$__errors['{$fieldName}'])) {\n";
            $phpCode .= "    \$__fieldErrors = \$__errors['{$fieldName}'];\n";
            $phpCode .= "    if (is_array(\$__fieldErrors)) {\n";
            $phpCode .= "        foreach (\$__fieldErrors as \$__errorMessage) {\n";
            $phpCode .= "            echo htmlspecialchars(\$__errorMessage, ENT_QUOTES, 'UTF-8');\n";
            $phpCode .= "        }\n";
            $phpCode .= "    } else {\n";
            $phpCode .= "        echo htmlspecialchars(\$__fieldErrors, ENT_QUOTES, 'UTF-8');\n";
            $phpCode .= "    }\n";
            $phpCode .= "}\n";
        }
        
        // Check if errors exist and conditionally show/hide the element
        if ($expression !== '*' && $expression !== 'all') {
            $phpCode .= "elseif (isset(\$__validationErrors) && isset(\$__validationErrors['{$fieldName}'])) {\n";
            $phpCode .= "    \$__fieldErrors = \$__validationErrors['{$fieldName}'];\n";
            $phpCode .= "    if (is_array(\$__fieldErrors)) {\n";
            $phpCode .= "        foreach (\$__fieldErrors as \$__errorMessage) {\n";
            $phpCode .= "            echo htmlspecialchars(\$__errorMessage, ENT_QUOTES, 'UTF-8');\n";
            $phpCode .= "        }\n";
            $phpCode .= "    } else {\n";
            $phpCode .= "        echo htmlspecialchars(\$__fieldErrors, ENT_QUOTES, 'UTF-8');\n";
            $phpCode .= "    }\n";
            $phpCode .= "}\n";
        }
        
        $phpCode .= "?>";
        
        // Wrap the element with conditional display based on error existence
        $conditionPhp = "<?php if (";
        
        if ($expression === '*' || $expression === 'all') {
            $conditionPhp .= "(isset(\$__errors) && !empty(\$__errors)) || (isset(\$__validationErrors) && !empty(\$__validationErrors))";
        } else {
            $conditionPhp .= "isset(\$__errors['{$fieldName}']) || isset(\$__validationErrors['{$fieldName}'])";
        }
        
        $conditionPhp .= "): ?>";
        $endConditionPhp = "<?php endif; ?>";
        
        $this->insertPhpBefore($node, $conditionPhp);
        $this->replaceContent($node, $phpCode);
        $this->insertPhpAfter($node, $endConditionPhp);
        
        // Remove the th:errors attribute
        $node->removeAttribute('th:errors');
    }
    
    /**
     * Extract field name from expression
     */
    private function extractFieldName(string $expression): string
    {
        if ($expression === '*' || $expression === 'all') {
            return $expression;
        }
        
        // Extract the last part of dot notation (e.g., "user.profile.name" -> "name")
        $parts = explode('.', $expression);
        return end($parts);
    }
}