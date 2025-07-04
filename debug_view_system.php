<?php

require_once 'vendor/autoload.php';

use LengthOfRope\TreeHouse\View\ViewFactory;

echo "Testing complete view system...\n\n";

try {
    // Clear all caches first
    $viewFactory = new ViewFactory();
    $viewFactory->clearCache();
    echo "✅ View cache cleared\n";
    
    // Test if storage/views directory exists and is writable
    $cachePath = 'storage/views';
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0755, true);
        echo "✅ Created cache directory: $cachePath\n";
    } else {
        echo "✅ Cache directory exists: $cachePath\n";
    }
    
    if (!is_writable($cachePath)) {
        echo "❌ Cache directory not writable: $cachePath\n";
    } else {
        echo "✅ Cache directory writable: $cachePath\n";
    }
    
    // Clear any existing cache files
    $cacheFiles = glob($cachePath . '/*');
    foreach ($cacheFiles as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "✅ Cleared " . count($cacheFiles) . " cache files\n";
    
    // Test rendering the components template
    echo "\nTesting components template rendering...\n";
    
    // Add some test data
    $testData = [
        'users' => [
            'john' => [
                'name' => 'John Doe',
                'role' => 'Developer',
                'email' => 'john@example.com'
            ]
        ]
    ];
    
    $rendered = $viewFactory->render('components', $testData);
    
    echo "Render successful: " . (strlen($rendered) > 0 ? 'YES' : 'NO') . "\n";
    echo "Output length: " . strlen($rendered) . " characters\n";
    
    // Check if th: attributes are still present (should not be)
    $hasThAttributes = preg_match('/th:\w+/', $rendered);
    echo "Contains th: attributes: " . ($hasThAttributes ? 'YES (BAD)' : 'NO (GOOD)') . "\n";
    
    // Check if brace expressions are still present (should not be)
    $hasBraceExpressions = preg_match('/\{[^}]+\}/', $rendered);
    echo "Contains brace expressions: " . ($hasBraceExpressions ? 'YES (BAD)' : 'NO (GOOD)') . "\n";
    
    // Show first 500 characters of output
    echo "\nFirst 500 characters of output:\n";
    echo substr($rendered, 0, 500) . "...\n";
    
    // Check cache files created
    $newCacheFiles = glob($cachePath . '/*');
    echo "\nCache files created: " . count($newCacheFiles) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}