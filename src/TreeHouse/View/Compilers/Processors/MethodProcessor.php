<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:method directive
 * 
 * Handles HTTP method spoofing for forms (PUT, PATCH, DELETE)
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class MethodProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        $method = strtoupper(trim($expression));
        
        // Set the form method to POST for spoofed methods
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $node->setAttribute('method', 'POST');
            
            // Generate PHP code to inject method spoofing field
            $phpCode = "<?php\n";
            $phpCode .= "// HTTP Method Spoofing\n";
            $phpCode .= "echo '<input type=\"hidden\" name=\"_method\" value=\"{$method}\">';\n";
            $phpCode .= "?>";
            
            // Insert method spoofing field as first child of form element
            $this->insertPhpBefore($node->firstChild ?: $node, $phpCode);
        } else {
            // For GET and POST, just set the method directly
            $node->setAttribute('method', $method);
        }
        
        // Remove the th:method attribute
        $node->removeAttribute('th:method');
    }
}