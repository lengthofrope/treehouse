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
        // Check if this is a literal method or a variable expression
        if (preg_match('/^[A-Z]+$/', trim($expression))) {
            // Literal method (like "POST", "GET", "PUT", etc.)
            $method = strtoupper(trim($expression));
            $this->handleLiteralMethod($node, $method);
        } else {
            // Variable expression (like "form.contact.method")
            $this->handleVariableMethod($node, $expression);
        }
        
        // Remove the th:method attribute
        $node->removeAttribute('th:method');
    }
    
    /**
     * Handle literal method values
     */
    private function handleLiteralMethod(DOMElement $node, string $method): void
    {
        // Set the form method to POST for spoofed methods
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $node->setAttribute('method', 'POST');
            
            // Generate PHP code to inject method spoofing field
            $phpCode = "<?php\n";
            $phpCode .= "// HTTP Method Spoofing\n";
            $phpCode .= "echo '<input type=\"hidden\" name=\"_method\" value=\"{$method}\">';\n";
            $phpCode .= "?>";
            
            // Insert method spoofing field at the beginning of the form element
            $this->insertMethodSpoofingField($node, $phpCode);
        } else {
            // For GET and POST, just set the method directly
            $node->setAttribute('method', $method);
        }
    }
    
    /**
     * Insert method spoofing field at the beginning of a form element
     */
    private function insertMethodSpoofingField(DOMElement $formNode, string $phpCode): void
    {
        // Create the comment node for the PHP code
        $commentNode = $formNode->ownerDocument->createComment("TH_PHP_BEFORE:" . base64_encode($phpCode));
        
        // Insert as the first child of the form
        if ($formNode->firstChild) {
            $formNode->insertBefore($commentNode, $formNode->firstChild);
        } else {
            $formNode->appendChild($commentNode);
        }
    }
    
    /**
     * Handle variable method expressions
     */
    private function handleVariableMethod(DOMElement $node, string $expression): void
    {
        $compiledExpression = $this->expressionCompiler->compileExpression($expression);
        
        // Generate PHP code to dynamically set method and handle spoofing
        $phpCode = "<?php\n";
        $phpCode .= "// Dynamic HTTP Method\n";
        $phpCode .= "\$__method = strtoupper({$compiledExpression});\n";
        $phpCode .= "if (in_array(\$__method, ['PUT', 'PATCH', 'DELETE'])) {\n";
        $phpCode .= "    echo 'POST';\n";
        $phpCode .= "} else {\n";
        $phpCode .= "    echo htmlspecialchars(\$__method, ENT_QUOTES, 'UTF-8');\n";
        $phpCode .= "}\n";
        $phpCode .= "?>";
        
        // Use PHP marker for method attribute
        $marker = "<!--TH_PHP_ATTR:" . base64_encode($phpCode) . "-->";
        $node->setAttribute('method', $marker);
        
        // Generate PHP code for method spoofing field if needed
        $spoofingPhp = "<?php\n";
        $spoofingPhp .= "// Method spoofing field for dynamic methods\n";
        $spoofingPhp .= "\$__method = strtoupper({$compiledExpression});\n";
        $spoofingPhp .= "if (in_array(\$__method, ['PUT', 'PATCH', 'DELETE'])) {\n";
        $spoofingPhp .= "    echo '<input type=\"hidden\" name=\"_method\" value=\"' . htmlspecialchars(\$__method, ENT_QUOTES, 'UTF-8') . '\">';\n";
        $spoofingPhp .= "}\n";
        $spoofingPhp .= "?>";
        
        // Insert method spoofing field at the beginning of the form element
        $this->insertMethodSpoofingField($node, $spoofingPhp);
    }
}