<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:csrf directive
 * 
 * Handles CSRF token injection into forms
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CsrfProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Generate PHP code to inject CSRF token
        $phpCode = "<?php\n";
        $phpCode .= "// CSRF Token\n";
        $phpCode .= "if (function_exists('csrf_token')) {\n";
        $phpCode .= "    echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '\">';\n";
        $phpCode .= "} elseif (isset(\$__csrf_token)) {\n";
        $phpCode .= "    echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(\$__csrf_token, ENT_QUOTES, 'UTF-8') . '\">';\n";
        $phpCode .= "} elseif (session_status() === PHP_SESSION_ACTIVE && isset(\$_SESSION['_token'])) {\n";
        $phpCode .= "    echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(\$_SESSION['_token'], ENT_QUOTES, 'UTF-8') . '\">';\n";
        $phpCode .= "}\n";
        $phpCode .= "?>";
        
        // Insert CSRF token as first child of form element
        $this->insertPhpBefore($node->firstChild ?: $node, $phpCode);
        
        // Remove the th:csrf attribute
        $node->removeAttribute('th:csrf');
    }
}