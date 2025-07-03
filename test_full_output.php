<?php

require_once 'vendor/autoload.php';

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionCompiler;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionValidator;

echo "=== Full Template Compilation Output ===\n";

try {
    $expressionValidator = new ExpressionValidator();
    $expressionCompiler = new ExpressionCompiler($expressionValidator);
    $compiler = new TreeHouseCompiler($expressionValidator, $expressionCompiler);
    
    $templatePath = 'resources/views/components.th.html';
    $template = file_get_contents($templatePath);
    
    echo "Compiling: $templatePath\n";
    echo "Template size: " . strlen($template) . " characters\n\n";
    
    $compiled = $compiler->compile($template, $templatePath);
    
    echo "=== FULL COMPILED OUTPUT ===\n";
    echo $compiled;
    echo "\n=== END OF COMPILED OUTPUT ===\n";
    
    // Look for any remaining th: attributes
    if (preg_match_all('/th:[a-zA-Z-]+/', $compiled, $matches)) {
        echo "\n❌ REMAINING th: ATTRIBUTES FOUND:\n";
        foreach (array_unique($matches[0]) as $attr) {
            echo "- $attr\n";
        }
    } else {
        echo "\n✅ No remaining th: attributes found\n";
    }
    
} catch (Exception $e) {
    echo "❌ COMPILATION FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}