<?php

require_once 'src/TreeHouse/View/Compilers/TreeHouseCompiler.php';
require_once 'src/TreeHouse/View/Compilers/DirectiveProcessorInterface.php';
require_once 'src/TreeHouse/View/Compilers/ExpressionValidator.php';
require_once 'src/TreeHouse/View/Compilers/ExpressionCompiler.php';
require_once 'src/TreeHouse/View/Compilers/Processors/AbstractProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/ExtendProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/SectionProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/YieldProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/IfProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/RepeatProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/TextProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/RawProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/AttrProcessor.php';

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;

// Enable debug mode to see actual errors
define('TH_DEBUG', true);

$compiler = new TreeHouseCompiler();

// Test the actual components.th.html file
$componentsTemplate = file_get_contents('resources/views/components.th.html');

echo "=== Debugging components.th.html Compilation ===\n";

try {
    echo "Attempting to compile components.th.html...\n";
    $compiled = $compiler->compile($componentsTemplate);
    
    echo "✅ Compilation successful!\n";
    echo "First 500 characters of compiled output:\n";
    echo substr($compiled, 0, 500) . "...\n\n";
    
    // Check if it contains the raw th: attributes
    if (strpos($compiled, 'th:extend') !== false || strpos($compiled, 'th:section') !== false) {
        echo "❌ PROBLEM: Raw th: attributes still present in output\n";
        echo "This means the compiler is falling back to raw template\n";
    } else {
        echo "✅ SUCCESS: th: attributes properly processed\n";
    }
    
} catch (Exception $e) {
    echo "❌ COMPILATION FAILED with error:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}