# Vendor Path Resolution Fix

## Problem

When TreeHouse Framework is installed as a vendor dependency (via Composer), the templating engine's `th:extend` and `th:content` directives were not working properly. This was due to incorrect path resolution in the ViewFactory and ViewEngine classes.

## Root Cause

The issue was in the fallback path logic in both `ViewFactory.php` and `ViewEngine.php`. When no explicit paths were provided in configuration, these classes used `getcwd()` to determine the default template paths:

```php
// Problematic code
$defaultConfig['paths'] = [
    getcwd() . '/resources/views',
    getcwd() . '/templates',
];
```

When TreeHouse is installed as a vendor dependency, `getcwd()` returns the consuming project's directory, but the framework's own templates and the path resolution logic expected to find templates relative to the framework's installation directory.

## Solution

Implemented intelligent vendor detection in both `ViewFactory` and `ViewEngine` classes:

### 1. ViewFactory Changes

- Added `getDefaultViewPaths()` method that detects if running as vendor dependency
- Added `getDefaultCachePath()` method with same detection logic
- Added `findProjectRoot()` helper method to extract project root from vendor paths

### 2. ViewEngine Changes

- Added identical `getDefaultViewPaths()` and `findProjectRoot()` methods
- Updated constructor to use intelligent path detection

### 3. Detection Logic

The fix uses reflection to determine the framework's installation location:

```php
$reflector = new \ReflectionClass(static::class);
$frameworkDir = dirname($reflector->getFileName(), 4);

// Check if we're in vendor/
if (str_contains($frameworkDir, '/vendor/')) {
    $projectRoot = $this->findProjectRoot($frameworkDir);
    return [
        $projectRoot . '/resources/views',
        $projectRoot . '/templates',
        $projectRoot . '/views',
    ];
}
```

## Behavior

### When Running Standalone
- Uses `getcwd()` for paths (existing behavior)
- Paths: `[getcwd()/resources/views, getcwd()/templates]`

### When Running as Vendor Dependency
- Detects project root from vendor path
- Paths: `[projectRoot/resources/views, projectRoot/templates, projectRoot/views]`

### When Explicit Configuration Provided
- Uses provided paths (no fallback logic triggered)
- Behavior unchanged from before

## Testing

Added comprehensive test coverage:

1. **VendorPathDetectionTest.php** - Tests the path detection logic
2. **VendorIntegrationTest.php** - Integration tests for template inheritance

All existing tests continue to pass, ensuring backward compatibility.

## Impact

- ✅ `th:extend` now works correctly when TreeHouse is a vendor dependency
- ✅ `th:content` and other template directives work correctly
- ✅ Backward compatibility maintained for standalone installations
- ✅ No breaking changes to existing APIs
- ✅ Proper configuration still takes precedence over fallback logic

## Usage

No changes required for end users. The fix is automatic and transparent:

```php
// This now works correctly in both scenarios
$factory = new ViewFactory(); // Uses intelligent path detection
$output = $factory->render('child-template-that-extends-layout');
```

For best practices, explicit configuration is still recommended:

```php
// Recommended approach
$factory = new ViewFactory([
    'paths' => [__DIR__ . '/resources/views'],
    'cache_path' => __DIR__ . '/storage/views'
]);