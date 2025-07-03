<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:section directive
 * 
 * Defines a section that can be yielded in layout templates
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   2.0.0
 */
class SectionProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        $startPhp = "<?php \$this->startSection('{$expression}'); ?>";
        $endPhp = "<?php \$this->endSection(); ?>";
        
        // Use PHP marker system for proper code generation
        $this->insertPhpBefore($node, $startPhp);
        $this->insertPhpAfter($node, $endPhp);
    }
}