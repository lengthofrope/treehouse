<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:yield directive
 * 
 * Yields content from a section in layout templates
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   2.0.0
 */
class YieldProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse expression to get section name and optional default
        $sectionName = trim($expression);
        $defaultValue = '';
        
        // Check if expression contains a default value: "content, 'default text'"
        if (preg_match('/^([^,]+),\s*(.+)$/', $expression, $matches)) {
            $sectionName = trim($matches[1], '\'"');
            $defaultValue = trim($matches[2]);
        } else {
            $sectionName = trim($sectionName, '\'"');
        }
        
        // Generate PHP code to yield the section
        if ($defaultValue) {
            $php = "<?php echo \$this->yieldSection('{$sectionName}', {$defaultValue}); ?>";
        } else {
            $php = "<?php echo \$this->yieldSection('{$sectionName}'); ?>";
        }
        
        // Replace the element content with the yield PHP code
        $this->replaceContent($node, $php);
    }
}