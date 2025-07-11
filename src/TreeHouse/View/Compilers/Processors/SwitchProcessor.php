<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:switch directive
 * 
 * Handles switch statement logic with case and default support
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SwitchProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        $compiledExpression = $this->expressionCompiler->compileExpression($expression);
        
        // Find all child elements with th:case and th:default
        $cases = [];
        $defaultCase = null;
        $switchVariable = '$__switch_' . uniqid();
        $firstCaseElement = null;
        $lastCaseElement = null;
        
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($child->hasAttribute('th:case')) {
                    $caseValue = $child->getAttribute('th:case');
                    
                    // Handle string literals vs expressions
                    if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $caseValue)) {
                        // Simple string literal like "admin", "user"
                        $compiledCaseValue = "'{$caseValue}'";
                    } else {
                        // Complex expression
                        $compiledCaseValue = $this->expressionCompiler->compileExpression($caseValue);
                    }
                    
                    $cases[] = [
                        'value' => $compiledCaseValue,
                        'element' => $child
                    ];
                    
                    // Track first and last case elements BEFORE removing attributes
                    if ($firstCaseElement === null) {
                        $firstCaseElement = $child;
                    }
                    $lastCaseElement = $child;
                    
                    $child->removeAttribute('th:case');
                } elseif ($child->hasAttribute('th:default')) {
                    $defaultCase = $child;
                    
                    // Track first and last case elements BEFORE removing attributes
                    if ($firstCaseElement === null) {
                        $firstCaseElement = $child;
                    }
                    $lastCaseElement = $child;
                    
                    $child->removeAttribute('th:default');
                }
            }
        }
        
        // Generate PHP switch statement before first case
        if ($firstCaseElement) {
            $phpCode = "<?php {$switchVariable} = {$compiledExpression}; switch ({$switchVariable}): ?>";
            $this->insertPhpBefore($firstCaseElement, $phpCode);
        }
        
        // Process each case
        foreach ($cases as $case) {
            $casePhp = "<?php case {$case['value']}: ?>";
            $this->insertPhpBefore($case['element'], $casePhp);
            $breakPhp = "<?php break; ?>";
            $this->insertPhpAfter($case['element'], $breakPhp);
        }
        
        // Process default case if exists
        if ($defaultCase) {
            $defaultPhp = "<?php default: ?>";
            $this->insertPhpBefore($defaultCase, $defaultPhp);
        }
        
        // Close switch statement after last case/default
        if ($lastCaseElement) {
            $endPhp = "<?php endswitch; ?>";
            $this->insertPhpAfter($lastCaseElement, $endPhp);
        }
        
        // Remove the th:switch attribute
        $node->removeAttribute('th:switch');
    }
}