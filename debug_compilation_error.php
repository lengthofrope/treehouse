<?php

require_once 'vendor/autoload.php';

use LengthOfRope\TreeHouse\View\ViewFactory;
use LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler;

// Enable debug mode to see compilation errors
define('TH_DEBUG', true);

echo "Debugging template compilation errors...\n\n";

try {
    // Test direct compilation first
    echo "1. Testing direct template compilation...\n";
    $compiler = new TreeHouseCompiler();
    
    // Test with a small portion of the components template that should work
    $testTemplate = '<div th:extend="layouts.app"><div th:section="content">{users.john.name}</div></div>';
    
    try {
        $compiled = $compiler->compile($testTemplate);
        echo "✅ Direct compilation successful\n";
        echo "Output: " . substr($compiled, strpos($compiled, '?>') + 2) . "\n\n";
    } catch (Exception $e) {
        echo "❌ Direct compilation failed: " . $e->getMessage() . "\n\n";
    }
    
    // Test with actual components template content
    echo "2. Testing with actual components template...\n";
    $componentsContent = file_get_contents('resources/views/components.th.html');
    
    try {
        $compiledComponents = $compiler->compile($componentsContent);
        echo "✅ Components compilation successful\n";
        echo "Contains th: attributes: " . (preg_match('/th:\w+/', $compiledComponents) ? 'YES (BAD)' : 'NO (GOOD)') . "\n";
        echo "First 300 chars: " . substr($compiledComponents, strpos($compiledComponents, '?>') + 2, 300) . "...\n\n";
    } catch (Exception $e) {
        echo "❌ Components compilation failed: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    }
    
    // Check what's in the cache files
    echo "3. Checking cache files...\n";
    $cacheFiles = glob('storage/views/*');
    foreach ($cacheFiles as $file) {
        echo "Cache file: " . basename($file) . "\n";
        $content = file_get_contents($file);
        echo "Size: " . strlen($content) . " bytes\n";
        echo "Contains th: attributes: " . (preg_match('/th:\w+/', $content) ? 'YES (BAD)' : 'NO (GOOD)') . "\n";
        echo "First 200 chars: " . substr($content, 0, 200) . "...\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}