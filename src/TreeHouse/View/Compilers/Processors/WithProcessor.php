<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\View\Compilers\Processors;

use DOMElement;

/**
 * Processor for th:with directive
 * 
 * Handles local variable creation in template scope
 *
 * @package LengthOfRope\TreeHouse\View\Compilers\Processors
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class WithProcessor extends AbstractProcessor
{
    public function process(DOMElement $node, string $expression): void
    {
        // Parse variable assignments (e.g., "fullName=user.firstName + ' ' + user.lastName, count=users.length")
        $assignments = $this->parseAssignments($expression);
        
        $phpCode = "<?php ";
        foreach ($assignments as $variable => $value) {
            // Use calculation context to allow arithmetic operators
            $compiledValue = $this->expressionCompiler->compileCalculation($value);
            $phpCode .= "\${$variable} = {$compiledValue}; ";
        }
        $phpCode .= "?>";
        
        $this->insertPhpBefore($node, $phpCode);
        
        // Remove the th:with attribute
        $node->removeAttribute('th:with');
    }
    
    /**
     * Parse variable assignments from expression
     * 
     * @param string $expression
     * @return array<string, string>
     */
    private function parseAssignments(string $expression): array
    {
        $assignments = [];
        
        // Split by comma, but respect parentheses and quotes
        $parts = $this->splitRespectingDelimiters($expression, ',');
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $part, $matches)) {
                $variable = trim($matches[1]);
                $value = trim($matches[2]);
                $assignments[$variable] = $value;
            }
        }
        
        return $assignments;
    }
    
    /**
     * Split string by delimiter while respecting parentheses and quotes
     * 
     * @param string $string
     * @param string $delimiter
     * @return array<string>
     */
    private function splitRespectingDelimiters(string $string, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = '';
        
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $string[$i - 1] !== '\\')) {
                $inQuotes = false;
                $quoteChar = '';
            } elseif (!$inQuotes && $char === '(') {
                $depth++;
            } elseif (!$inQuotes && $char === ')') {
                $depth--;
            } elseif (!$inQuotes && $depth === 0 && $char === $delimiter) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $parts[] = $current;
        }
        
        return $parts;
    }
}