<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:text directive
 * 
 * Sets element text content with escaped output
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   2.0.0
 */
class TextProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        $compiledExpression = $this->expressionCompiler->compileText($expression);
        $phpCode = "<?php echo {$compiledExpression}; ?>";
        $this->replaceContent($node, $phpCode);
    }
}