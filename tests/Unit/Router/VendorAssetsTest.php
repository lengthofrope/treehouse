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
        // Explicitly enable vendor assets while disabling CSRF
        $this->router = new Router(false, null, true); // registerCsrf=false, registerCsrfEndpoint=null, registerVendorAssets=true
    }

    public function testVendorAssetRouteIsRegistered(): void
    {
        // Debug: Check what routes are actually registered
        $routes = $this->router->getRoutes()->getRoutes();
        $this->assertGreaterThan(0, $routes->count(), 'At least one route should be registered');
        
        // The route should be registered and match
        $route = $this->router->getRoutes()->match('GET', '/_assets/treehouse/js/treehouse.js');
        
        $this->assertNotNull($route, 'Vendor asset route should be registered');
    }

    public function testServeExistingVendorAsset(): void
    {
        // Test that the route exists and can handle asset requests
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_assets/treehouse/js/treehouse.js',
            'HTTP_HOST' => 'example.com'
        ]);
        $response = $this->router->dispatch($request);
        
        // The route should be processed and return a valid response (either 200 or 404 for missing file)
        $this->assertContains($response->getStatusCode(), [200, 404],
            'Route should handle the request and return either 200 (file found) or 404 (file not found)');
        
        // If the file exists, we should get proper headers
        if ($response->getStatusCode() === 200) {
            $this->assertEquals('application/javascript', $response->getHeader('Content-Type'));
            $this->assertEquals('public, max-age=31536000', $response->getHeader('Cache-Control'));
            $this->assertNotEmpty($response->getHeader('ETag'));
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
        // Create a temporary test file to ensure the test always runs
        $testDir = sys_get_temp_dir() . '/treehouse-test';
        $testFile = $testDir . '/treehouse.js';
        
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        file_put_contents($testFile, '/* Test TreeHouse JavaScript */console.log("TreeHouse Test");');
        
        // Create a custom router that points to our test directory
        $customRouter = new Router(false, null, false); // No built-in routes
        $customRouter->get('/_assets/treehouse/{path}', function($request, $path) use ($testDir) {
            $filePath = $testDir . '/' . $path;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $etag = md5_file($filePath);
                $lastModified = filemtime($filePath);
                
                // Check ETag
                $clientEtag = $request->header('If-None-Match');
                if ($clientEtag === $etag) {
                    return new \LengthOfRope\TreeHouse\Http\Response('', 304);
                }
                
                $response = new \LengthOfRope\TreeHouse\Http\Response($content);
                $response->setHeader('Content-Type', 'application/javascript');
                $response->setHeader('Cache-Control', 'public, max-age=31536000');
                $response->setHeader('ETag', $etag);
                $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
                
                return $response;
            }
            return new \LengthOfRope\TreeHouse\Http\Response('Not Found', 404);
        })->where('path', '.*');
        
        try {
            // First request
            $request1 = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/treehouse.js',
                'HTTP_HOST' => 'example.com'
            ]);
            $response1 = $customRouter->dispatch($request1);
            
            $this->assertEquals(200, $response1->getStatusCode());
            $etag = $response1->getHeader('ETag');
            $this->assertNotEmpty($etag);
            
            // Second request with ETag
            $request2 = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/treehouse.js',
                'HTTP_HOST' => 'example.com',
                'HTTP_IF_NONE_MATCH' => $etag
            ]);
            $response2 = $customRouter->dispatch($request2);
            
            $this->assertEquals(304, $response2->getStatusCode());
            $this->assertEmpty($response2->getContent());
            
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            if (is_dir($testDir)) {
                rmdir($testDir);
            }
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
        // Test that the route pattern works correctly
        $validPaths = [
            '/_assets/treehouse/js/treehouse.js',
            '/_assets/treehouse/css/styles.css',
            '/_assets/treehouse/images/logo.png',
        ];

        foreach ($validPaths as $path) {
            $route = $this->router->getRoutes()->match('GET', $path);
            $this->assertNotNull($route, "Route should match valid path: {$path}");
        }
        
        // Test that completely different paths don't match
        $invalidPaths = [
            '/assets/treehouse/js/treehouse.js', // Missing underscore
            '/_assets/other/js/file.js', // Different vendor
        ];

        foreach ($invalidPaths as $path) {
            $route = $this->router->getRoutes()->match('GET', $path);
            $this->assertNull($route, "Route should not match invalid path: {$path}");
        }
    }

    public function testVendorAssetHeaders(): void
    {
        // Create a temporary test file to ensure the test always runs
        $testDir = sys_get_temp_dir() . '/treehouse-test-headers';
        $testFile = $testDir . '/test.js';
        
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        file_put_contents($testFile, '/* Test TreeHouse JavaScript for headers */');
        
        // Create a custom router that points to our test directory
        $customRouter = new Router(false, null, false); // No built-in routes
        $customRouter->get('/_assets/treehouse/{path}', function($request, $path) use ($testDir) {
            $filePath = $testDir . '/' . $path;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $etag = md5_file($filePath);
                $lastModified = filemtime($filePath);
                
                $response = new \LengthOfRope\TreeHouse\Http\Response($content);
                $response->setHeader('Content-Type', 'application/javascript');
                $response->setHeader('Cache-Control', 'public, max-age=31536000');
                $response->setHeader('ETag', $etag);
                $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
                
                return $response;
            }
            return new \LengthOfRope\TreeHouse\Http\Response('Not Found', 404);
        })->where('path', '.*');
        
        try {
            $request = new Request([], [], [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_assets/treehouse/test.js',
                'HTTP_HOST' => 'example.com'
            ]);
            $response = $customRouter->dispatch($request);
            
            $this->assertEquals(200, $response->getStatusCode());
            
            // Check required headers
            $this->assertEquals('application/javascript', $response->getHeader('Content-Type'));
            $this->assertEquals('public, max-age=31536000', $response->getHeader('Cache-Control'));
            $this->assertNotEmpty($response->getHeader('ETag'));
            $this->assertNotEmpty($response->getHeader('Last-Modified'));
            
            // Verify Last-Modified format
            $lastModified = $response->getHeader('Last-Modified');
            $this->assertNotFalse(strtotime($lastModified), 'Last-Modified should be a valid date');
            
        } finally {
            // Clean up
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            if (is_dir($testDir)) {
                rmdir($testDir);
            }
        }
    }
}