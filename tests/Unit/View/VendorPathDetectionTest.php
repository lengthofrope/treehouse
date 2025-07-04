<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\View\ViewFactory;
use LengthOfRope\TreeHouse\View\ViewEngine;
use ReflectionClass;

/**
 * Test vendor path detection for template engine
 */
class VendorPathDetectionTest extends TestCase
{
    public function testViewFactoryDetectsVendorPaths(): void
    {
        // Create ViewFactory without providing paths (triggers fallback logic)
        $factory = new ViewFactory([]);
        
        // Use reflection to access the protected method
        $reflection = new ReflectionClass($factory);
        $method = $reflection->getMethod('getDefaultViewPaths');
        $method->setAccessible(true);
        
        $paths = $method->invoke($factory);
        
        // Verify we get an array of paths
        $this->assertIsArray($paths);
        $this->assertNotEmpty($paths);
        
        // Verify paths are absolute
        foreach ($paths as $path) {
            $this->assertStringStartsWith('/', $path);
        }
    }

    public function testViewFactoryDetectsCachePath(): void
    {
        // Create ViewFactory without providing cache_path (triggers fallback logic)
        $factory = new ViewFactory([]);
        
        // Use reflection to access the protected method
        $reflection = new ReflectionClass($factory);
        $method = $reflection->getMethod('getDefaultCachePath');
        $method->setAccessible(true);
        
        $cachePath = $method->invoke($factory);
        
        // Verify we get a valid path
        $this->assertIsString($cachePath);
        $this->assertStringStartsWith('/', $cachePath);
        $this->assertStringContainsString('storage/views', $cachePath);
    }

    public function testViewEngineDetectsVendorPaths(): void
    {
        // Create ViewEngine without providing paths (triggers fallback logic)
        $engine = new ViewEngine([]);
        
        // Use reflection to access the protected method
        $reflection = new ReflectionClass($engine);
        $method = $reflection->getMethod('getDefaultViewPaths');
        $method->setAccessible(true);
        
        $paths = $method->invoke($engine);
        
        // Verify we get an array of paths
        $this->assertIsArray($paths);
        $this->assertNotEmpty($paths);
        
        // Verify paths are absolute
        foreach ($paths as $path) {
            $this->assertStringStartsWith('/', $path);
        }
    }

    public function testProjectRootDetectionFromVendorPath(): void
    {
        $factory = new ViewFactory([]);
        
        // Use reflection to access the protected method
        $reflection = new ReflectionClass($factory);
        $method = $reflection->getMethod('findProjectRoot');
        $method->setAccessible(true);
        
        // Test with a typical vendor path
        $vendorPath = '/home/user/project/vendor/lengthofrope/treehouse-framework';
        $projectRoot = $method->invoke($factory, $vendorPath);
        
        $this->assertEquals('/home/user/project', $projectRoot);
    }

    public function testProjectRootDetectionFallback(): void
    {
        $factory = new ViewFactory([]);
        
        // Use reflection to access the protected method
        $reflection = new ReflectionClass($factory);
        $method = $reflection->getMethod('findProjectRoot');
        $method->setAccessible(true);
        
        // Test with a non-vendor path (should fallback to getcwd)
        $nonVendorPath = '/some/random/path';
        $projectRoot = $method->invoke($factory, $nonVendorPath);
        
        $this->assertEquals(getcwd(), $projectRoot);
    }

    public function testViewFactoryWithProvidedPaths(): void
    {
        // When paths are provided, they should be used instead of fallback
        $customPaths = ['/custom/views', '/another/path'];
        $factory = new ViewFactory(['paths' => $customPaths]);
        
        $config = $factory->getConfig();
        $this->assertEquals($customPaths, $config['paths']);
    }

    public function testViewEngineWithProvidedPaths(): void
    {
        // When paths are provided, they should be used instead of fallback
        $customPaths = ['/custom/views', '/another/path'];
        $engine = new ViewEngine($customPaths);
        
        $this->assertEquals($customPaths, $engine->getPaths());
    }
}