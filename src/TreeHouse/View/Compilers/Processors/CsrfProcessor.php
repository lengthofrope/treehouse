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
        $phpCode .= "if (function_exists('csrfField')) {\n";
        $phpCode .= "    echo csrfField();\n";
        $phpCode .= "} elseif (function_exists('csrfToken')) {\n";
        $phpCode .= "    echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '\">';\n";
        $phpCode .= "} elseif (session_status() === PHP_SESSION_ACTIVE && isset(\$_SESSION['_token'])) {\n";
        $phpCode .= "    echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(\$_SESSION['_token'], ENT_QUOTES, 'UTF-8') . '\">';\n";
        $phpCode .= "} else {\n";
        $phpCode .= "    // Generate fallback token\n";
        $phpCode .= "    \$token = bin2hex(random_bytes(32));\n";
        $phpCode .= "    echo '<input type=\"hidden\" name=\"_token\" value=\"' . htmlspecialchars(\$token, ENT_QUOTES, 'UTF-8') . '\">';\n";
        $phpCode .= "}\n";
        $phpCode .= "?>";
        
        // Insert CSRF token as first child of form element
        $commentNode = $node->ownerDocument->createComment("TH_PHP_BEFORE:" . base64_encode($phpCode));
        
        // Insert as the first child of the form
        if ($node->firstChild) {
            $node->insertBefore($commentNode, $node->firstChild);
        } else {
            $node->appendChild($commentNode);
        }
        
        // Remove the th:csrf attribute
        $node->removeAttribute('th:csrf');
    }
}