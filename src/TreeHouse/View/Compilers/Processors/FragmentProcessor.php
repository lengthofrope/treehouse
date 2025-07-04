<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:fragment directive
 * 
 * Handles reusable template fragment definitions
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class FragmentProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse fragment name and parameters (e.g., "userCard(user)" or "userCard(user, showEmail)")
        if (preg_match('/^(\w+)\s*\(([^)]*)\)$/', trim($expression), $matches)) {
            $fragmentName = $matches[1];
            $paramString = trim($matches[2]);
            $parameters = $paramString ? array_map('trim', explode(',', $paramString)) : [];
        } else {
            $fragmentName = trim($expression);
            $parameters = [];
        }
        
        // Store fragment in global registry for later use by include/replace processors
        $fragmentId = "fragment_{$fragmentName}_" . uniqid();
        
        // Generate PHP code to register the fragment
        $phpCode = "<?php \n";
        $phpCode .= "// Fragment: {$fragmentName}\n";
        $phpCode .= "if (!isset(\$__fragments)) \$__fragments = [];\n";
        $phpCode .= "\$__fragments['{$fragmentName}'] = function(";
        
        // Add parameters
        if (!empty($parameters)) {
            $phpParams = array_map(function($param) {
                return '$' . $param;
            }, $parameters);
            $phpCode .= implode(', ', $phpParams);
        }
        
        $phpCode .= ") {\n";
        $phpCode .= "ob_start();\n";
        $phpCode .= "?>";
        
        $this->insertPhpBefore($node, $phpCode);
        
        // Close the fragment function
        $closePhp = "<?php\n";
        $closePhp .= "return ob_get_clean();\n";
        $closePhp .= "};\n";
        $closePhp .= "?>";
        
        $this->insertPhpAfter($node, $closePhp);
        
        // Remove the th:fragment attribute
        $node->removeAttribute('th:fragment');
    }
}