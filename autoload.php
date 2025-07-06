<?php

declare(strict_types=1);

// Simple autoloader for TreeHouse framework testing
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'LengthOfRope\\TreeHouse\\';
    $baseDir = __DIR__ . '/src/TreeHouse/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Also register autoloader for test classes
spl_autoload_register(function ($class) {
    $prefix = 'Tests\\';
    $baseDir = __DIR__ . '/tests/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Include helper files
$helperFiles = [
    __DIR__ . '/src/TreeHouse/Support/helpers.php',
    __DIR__ . '/src/TreeHouse/View/helpers.php',
    __DIR__ . '/src/TreeHouse/Auth/helpers.php',
    __DIR__ . '/src/TreeHouse/Cache/helpers.php',
    __DIR__ . '/src/TreeHouse/Http/helpers.php'
];

foreach ($helperFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}