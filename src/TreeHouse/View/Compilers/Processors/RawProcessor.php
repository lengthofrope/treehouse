<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:raw directive
 *
 * Outputs variable content without HTML escaping
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RawProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // For th:raw, we output the variable content without escaping
        $compiledExpression = $this->expressionCompiler->compileRaw($expression);
        
        // Replace the entire element with raw PHP output using marker system
        // This allows the raw content to contain HTML tags without being wrapped
        $phpCode = "<?php echo {$compiledExpression}; ?>";
        
        // Use the marker system to insert PHP code before the element, then remove the element
        $marker = "<!--TH_PHP_REPLACE:" . base64_encode($phpCode) . "-->";
        $commentNode = $node->ownerDocument->createComment("TH_PHP_REPLACE:" . base64_encode($phpCode));
        $node->parentNode->insertBefore($commentNode, $node);
        $this->removeNode($node);
    }
}