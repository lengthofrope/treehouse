<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:attr directive
 * 
 * Handles dynamic attribute setting
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   2.0.0
 */
class AttrProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse attr expression: "id='user-' + $user->id, class=$user->status"
        $attributes = explode(',', $expression);
        
        foreach ($attributes as $attr) {
            if (preg_match('/^\s*(\w+)\s*=\s*(.+)$/', trim($attr), $matches)) {
                $name = $matches[1];
                $value = trim($matches[2]);
                
                $compiledValue = $this->expressionCompiler->compileExpression($value);
                $php = "<?php echo {$compiledValue}; ?>";
                $node->setAttribute($name, $php);
            }
        }
    }
}