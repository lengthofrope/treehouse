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
 * @since   2.0.0
 */
class RepeatProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse repeat expression: "item $items", "item items", "key,item $items", or "key,item items"
        if (preg_match('/^(\w+)(?:,(\w+))?\s+\$?(\w+)$/', trim($expression), $matches)) {
            $first = $matches[1];
            $second = $matches[2] ?? null;
            $array = $matches[3];
            
            if ($second) {
                // key,item format
                $key = $first;
                $item = $second;
                $loop = "foreach (\${$array} as \${$key} => \${$item})";
            } else {
                // item format
                $item = $first;
                $loop = "foreach (\${$array} as \${$item})";
            }
            
            $this->wrapWithLoop($node, $loop);
        }
    }
}