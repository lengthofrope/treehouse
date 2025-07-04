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
        // Check if this is th:unless
        $isUnless = $node->hasAttribute('th:unless');
        
        if ($isUnless) {
            // For th:unless, compile with negation
            $compiledExpression = $this->expressionCompiler->compileNegatedConditional($expression);
            $this->wrapWithCondition($node, "if {$compiledExpression}");
            $node->removeAttribute('th:unless');
        } else {
            // For th:if, use condition as-is
            $compiledExpression = $this->expressionCompiler->compileConditional($expression);
            $this->wrapWithCondition($node, "if {$compiledExpression}");
            $node->removeAttribute('th:if');
        }
    }
}