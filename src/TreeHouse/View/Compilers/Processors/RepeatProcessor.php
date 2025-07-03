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
        // Parse repeat expression: "item $items", "item items", "key,item $items", or "key,item items"
        // Also handle dot notation like "act user.activities"
        if (preg_match('/^(\w+)(?:,(\w+))?\s+(.+)$/', trim($expression), $matches)) {
            $first = $matches[1];
            $second = $matches[2] ?? null;
            $arrayExpression = trim($matches[3]);
            
            // Compile the array expression (handles dot notation)
            $compiledArray = $this->expressionCompiler->compileExpression($arrayExpression);
            
            if ($second) {
                // key,item format
                $key = $first;
                $item = $second;
                $startPhp = "<?php foreach ({$compiledArray} as \${$key} => \${$item}): ?>";
                $endPhp = "<?php endforeach; ?>";
            } else {
                // item format
                $item = $first;
                $startPhp = "<?php foreach ({$compiledArray} as \${$item}): ?>";
                $endPhp = "<?php endforeach; ?>";
            }
            
            // Use PHP marker system for proper code generation
            $this->insertPhpBefore($node, $startPhp);
            $this->insertPhpAfter($node, $endPhp);
            
            // Remove the th:repeat attribute
            $node->removeAttribute('th:repeat');
        }
    }
}