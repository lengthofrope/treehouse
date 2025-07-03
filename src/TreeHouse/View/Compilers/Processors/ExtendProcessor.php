<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:extend directive
 * 
 * Handles template inheritance by extending a layout template
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   2.0.0
 */
class ExtendProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Add the extend call at the beginning of the document using PHP marker system
        $php = "<?php \$this->extend('{$expression}'); ?>";
        $this->insertPhpBefore($node, $php);
        
        // Move child nodes up to parent instead of removing them
        $this->unwrapElement($node);
    }
}