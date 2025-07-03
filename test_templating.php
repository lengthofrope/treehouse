<?php

define('TH_DEBUG', true);

require_once 'src/TreeHouse/View/Compilers/TreeHouseCompiler.php';
require_once 'src/TreeHouse/View/Compilers/ExpressionValidator.php';
require_once 'src/TreeHouse/View/Compilers/ExpressionCompiler.php';
require_once 'src/TreeHouse/View/Compilers/DirectiveProcessorInterface.php';
require_once 'src/TreeHouse/View/Compilers/Processors/AbstractProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/ExtendProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/SectionProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/YieldProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/IfProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/RepeatProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/TextProcessor.php';
require_once 'src/TreeHouse/View/Compilers/Processors/RawProcessor.php';

use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;

$compiler = new TreeHouseCompiler();
$template = file_get_contents('resources/views/templating.th.html');

echo "=== Testing Templating Template ===\n";
echo "Template size: " . strlen($template) . " characters\n\n";

try {
    $compiled = $compiler->compile($template);
    echo "✅ Compilation successful!\n";
    echo "Compiled size: " . strlen($compiled) . " characters\n\n";
    
    echo "=== FIRST 1000 CHARACTERS OF COMPILED OUTPUT ===\n";
    echo substr($compiled, 0, 1000) . "\n";
    echo "=== END OF PREVIEW ===\n";
    
} catch (Exception $e) {
    echo "❌ Compilation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}