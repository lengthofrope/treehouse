<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Test vendor asset serving functionality
 */
class VendorAssetsTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(true, null, true); // Enable vendor assets
    }

    public function testVendorAssetRouteIsRegistered(): void
    {
        // The route should be registered and match
        $route = $this->router->getRoutes()->match('GET', '/_assets/treehouse/js/treehouse.js');
        
        $this->assertNotNull($route, 'Vendor asset route should be registered');
    }

    public function testServeExistingVendorAsset(): void
    {
        // Create a temporary test file to simulate vendor asset
        $testContent = '/* Test TreeHouse JavaScript */';
        $testFile = __DIR__ . '/../../../assets/js/treehouse.js';
        
        if (file_exists($testFile)) {
            $request = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/js/treehouse.js',
                'HTTP_HOST' => 'example.com'
            ]);
            $response = $this->router->dispatch($request);
            
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('application/javascript', $response->getHeader('Content-Type'));
            $this->assertStringContainsString('TreeHouse', $response->getContent());
            $this->assertEquals('public, max-age=31536000', $response->getHeader('Cache-Control'));
            $this->assertNotEmpty($response->getHeader('ETag'));
        } else {
            $this->markTestSkipped('TreeHouse JavaScript file not found for testing');
        }
    }

    public function testServeNonExistentVendorAsset(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_assets/treehouse/js/nonexistent.js',
            'HTTP_HOST' => 'example.com'
        ]);
        $response = $this->router->dispatch($request);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPreventDirectoryTraversal(): void
    {
        $maliciousPaths = [
            '/_assets/treehouse/../../../etc/passwd',
            '/_assets/treehouse/js/../../../config.php',
            '/_assets/treehouse/js/../../src/Router.php',
            '/_assets/treehouse/js/..%2F..%2F..%2Fetc%2Fpasswd',
        ];

        foreach ($maliciousPaths as $path) {
            $request = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $path,
                'HTTP_HOST' => 'example.com'
            ]);
            $response = $this->router->dispatch($request);
            
            $this->assertEquals(403, $response->getStatusCode(),
                "Directory traversal should be blocked for path: {$path}");
        }
    }

    public function testServeDifferentFileTypes(): void
    {
        $fileTypes = [
            'js/treehouse.js' => 'application/javascript',
            'css/treehouse.css' => 'text/css',
            'fonts/treehouse.woff' => 'font/woff',
            'images/logo.png' => 'image/png',
        ];

        foreach ($fileTypes as $file => $expectedMimeType) {
            // We expect 404 for non-existent files, but we can test the route matching
            $route = $this->router->getRoutes()->match('GET', "/_assets/treehouse/{$file}");
            $this->assertNotNull($route, "Route should match for file type: {$file}");
        }
    }

    public function testETagCaching(): void
    {
        $testFile = __DIR__ . '/../../../assets/js/treehouse.js';
        
        if (file_exists($testFile)) {
            // First request
            $request1 = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/js/treehouse.js',
                'HTTP_HOST' => 'example.com'
            ]);
            $response1 = $this->router->dispatch($request1);
            $etag = $response1->getHeader('ETag');
            
            $this->assertEquals(200, $response1->getStatusCode());
            $this->assertNotEmpty($etag);
            
            // Second request with ETag
            $request2 = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/js/treehouse.js',
                'HTTP_HOST' => 'example.com',
                'HTTP_IF_NONE_MATCH' => $etag
            ]);
            $response2 = $this->router->dispatch($request2);
            
            $this->assertEquals(304, $response2->getStatusCode());
            $this->assertEmpty($response2->getContent());
        } else {
            $this->markTestSkipped('TreeHouse JavaScript file not found for ETag testing');
        }
    }

    public function testDisableVendorAssets(): void
    {
        $routerWithoutAssets = new Router(true, null, false); // Disable vendor assets
        
        $route = $routerWithoutAssets->getRoutes()->match('GET', '/_assets/treehouse/js/treehouse.js');
        
        $this->assertNull($route, 'Vendor asset route should not be registered when disabled');
    }

    public function testPublicOverrideSupport(): void
    {
        // This test would require setting up temporary files
        // For now, we'll just verify the route structure supports overrides
        $route = $this->router->getRoutes()->match('GET', '/_assets/treehouse/js/custom.js');
        
        $this->assertNotNull($route, 'Route should support any file path for override functionality');
    }

    public function testVendorAssetPathValidation(): void
    {
        $invalidPaths = [
            '/_assets/treehouse/', // Empty path
            '/_assets/treehouse', // No trailing path
        ];

        foreach ($invalidPaths as $path) {
            $route = $this->router->getRoutes()->match('GET', $path);
            
            // The route pattern should not match invalid paths
            $this->assertNull($route, "Route should not match invalid path: {$path}");
        }
    }

    public function testVendorAssetHeaders(): void
    {
        $testFile = __DIR__ . '/../../../assets/js/treehouse.js';
        
        if (file_exists($testFile)) {
            $request = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/js/treehouse.js',
                'HTTP_HOST' => 'example.com'
            ]);
            $response = $this->router->dispatch($request);
            
            $this->assertEquals(200, $response->getStatusCode());
            
            // Check required headers
            $this->assertEquals('application/javascript', $response->getHeader('Content-Type'));
            $this->assertEquals('public, max-age=31536000', $response->getHeader('Cache-Control'));
            $this->assertNotEmpty($response->getHeader('ETag'));
            $this->assertNotEmpty($response->getHeader('Last-Modified'));
            
            // Verify Last-Modified format
            $lastModified = $response->getHeader('Last-Modified');
            $this->assertNotFalse(strtotime($lastModified), 'Last-Modified should be a valid date');
        } else {
            $this->markTestSkipped('TreeHouse JavaScript file not found for header testing');
        }
    }
}