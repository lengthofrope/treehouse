<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:repeat directive
 * 
 * Handles loops with various syntax formats
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RepeatProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        try {
            // Use the ExpressionCompiler's compileIteration method which properly handles
            // "item : items", "key,item : items", and "item in items" formats
            $compiled = $this->expressionCompiler->compileIteration($expression);
            
            if ($compiled['key']) {
                // key,item format
                $startPhp = "<?php foreach ({$compiled['iterable']} as {$compiled['key']} => {$compiled['value']}): ?>";
            } else {
                // item format
                $startPhp = "<?php foreach ({$compiled['iterable']} as {$compiled['value']}): ?>";
            }
            $endPhp = "<?php endforeach; ?>";
            
            // Use PHP marker system for proper code generation
            $this->insertPhpBefore($node, $startPhp);
            $this->insertPhpAfter($node, $endPhp);
            
            // Remove the th:repeat attribute
            $node->removeAttribute('th:repeat');
            
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException(
                "Invalid th:repeat expression '{$expression}': " . $e->getMessage()
            );
        }
    }
}