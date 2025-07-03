<?php

require_once 'vendor/autoload.php';

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionCompiler;
use LengthOfRope\TreeHouse\View\Compilers\ExpressionValidator;

// Enable debug mode
define('TH_DEBUG', true);

echo "=== Debug Template Compilation ===\n";

try {
    $expressionValidator = new ExpressionValidator();
    $expressionCompiler = new ExpressionCompiler($expressionValidator);
    $compiler = new TreeHouseCompiler($expressionValidator, $expressionCompiler);
    
    $templatePath = 'resources/views/components.th.html';
    $template = file_get_contents($templatePath);
    
    echo "Compiling: $templatePath\n";
    echo "Template size: " . strlen($template) . " characters\n\n";
    
    $compiled = $compiler->compile($template);
    
    echo "✅ Compilation successful!\n";
    echo "Compiled size: " . strlen($compiled) . " characters\n";
    
} catch (Exception $e) {
    echo "❌ COMPILATION FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}