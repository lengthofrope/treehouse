<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:include directive
 * 
 * Handles inclusion of template fragments
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class IncludeProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse include expression (e.g., "fragments/user :: userCard(currentUser)")
        if (preg_match('/^([^:]+)\s*::\s*(.+)$/', trim($expression), $matches)) {
            $templatePath = trim($matches[1]);
            $fragmentCall = trim($matches[2]);
        } else {
            // Simple fragment reference in current template
            $templatePath = null;
            $fragmentCall = trim($expression);
        }
        
        // Parse fragment call (e.g., "userCard(currentUser, true)")
        if (preg_match('/^(\w+)\s*\(([^)]*)\)$/', $fragmentCall, $matches)) {
            $fragmentName = $matches[1];
            $paramString = trim($matches[2]);
            $parameters = $paramString ? $this->parseParameters($paramString) : [];
        } else {
            $fragmentName = $fragmentCall;
            $parameters = [];
        }
        
        // Generate PHP code to include the fragment
        $phpCode = "<?php\n";
        
        if ($templatePath) {
            // Include fragment from external template
            $phpCode .= "// Include fragment '{$fragmentName}' from '{$templatePath}'\n";
            $phpCode .= "\$__includePath = '{$templatePath}';\n";
            $phpCode .= "if (!str_ends_with(\$__includePath, '.th.html')) {\n";
            $phpCode .= "    \$__includePath .= '.th.html';\n";
            $phpCode .= "}\n";
            $phpCode .= "\$__includeFullPath = rtrim(\$__viewsPath ?? 'resources/views', '/') . '/' . \$__includePath;\n";
            $phpCode .= "if (file_exists(\$__includeFullPath)) {\n";
            $phpCode .= "    include \$__includeFullPath;\n";
            $phpCode .= "}\n";
            $phpCode .= "if (isset(\$__fragments['{$fragmentName}'])) {\n";
        } else {
            // Include fragment from current template
            $phpCode .= "// Include fragment '{$fragmentName}'\n";
            $phpCode .= "if (isset(\$__fragments['{$fragmentName}'])) {\n";
        }
        
        // Call the fragment with parameters
        if (!empty($parameters)) {
            $compiledParams = array_map(function($param) {
                return $this->expressionCompiler->compileExpression($param);
            }, $parameters);
            $phpCode .= "    echo \$__fragments['{$fragmentName}'](" . implode(', ', $compiledParams) . ");\n";
        } else {
            $phpCode .= "    echo \$__fragments['{$fragmentName}']();\n";
        }
        
        $phpCode .= "}\n";
        $phpCode .= "?>";
        
        // Replace the content of the element with the fragment call
        $this->replaceContent($node, $phpCode);
        
        // Remove the th:include attribute
        $node->removeAttribute('th:include');
    }
    
    /**
     * Parse parameters from parameter string
     * 
     * @param string $paramString
     * @return array<string>
     */
    private function parseParameters(string $paramString): array
    {
        $parameters = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = '';
        
        for ($i = 0; $i < strlen($paramString); $i++) {
            $char = $paramString[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $paramString[$i - 1] !== '\\')) {
                $inQuotes = false;
                $quoteChar = '';
            } elseif (!$inQuotes && $char === '(') {
                $depth++;
            } elseif (!$inQuotes && $char === ')') {
                $depth--;
            } elseif (!$inQuotes && $depth === 0 && $char === ',') {
                $parameters[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $parameters[] = trim($current);
        }
        
        return $parameters;
    }
}