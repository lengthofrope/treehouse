<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:if directive
 * 
 * Handles conditional rendering with boolean logic support
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class IfProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        $compiledExpression = $this->expressionCompiler->compileConditional($expression);
        $this->wrapWithCondition($node, "if {$compiledExpression}");
        
        // Remove the th:if attribute
        $node->removeAttribute('th:if');
    }
}