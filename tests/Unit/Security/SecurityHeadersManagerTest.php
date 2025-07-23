<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Security\SecurityHeadersManager;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

/**
 * Test SecurityHeadersManager functionality
 */
class SecurityHeadersManagerTest extends TestCase
{
    private SecurityHeadersManager $headerManager;

    protected function setUp(): void
    {
        $this->headerManager = new SecurityHeadersManager();
    }

    public function testCanCreateSecurityHeadersManager(): void
    {
        $this->assertInstanceOf(SecurityHeadersManager::class, $this->headerManager);
    }

    public function testCanApplySecurityHeaders(): void
    {
        $request = $this->createMockRequest();
        $response = new Response('test content');
        
        $modifiedResponse = $this->headerManager->applyHeaders($response, $request);
        
        $this->assertInstanceOf(Response::class, $modifiedResponse);
        $headers = $modifiedResponse->getHeaders();
        
        // Should have security headers applied
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
        
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertEquals('DENY', $headers['X-Frame-Options']);
    }

    public function testCanApplyCorsHeaders(): void
    {
        $request = $this->createMockRequest(['Origin' => 'https://example.com']);
        $response = new Response('test content');
        
        $modifiedResponse = $this->headerManager->applyHeaders($response, $request);
        $headers = $modifiedResponse->getHeaders();
        
        // Should have CORS headers
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
        $this->assertArrayHasKey('Access-Control-Max-Age', $headers);
    }

    public function testCanApplyCspHeaders(): void
    {
        $response = new Response('test content');
        
        $modifiedResponse = $this->headerManager->applyHeaders($response);
        $headers = $modifiedResponse->getHeaders();
        
        // Should have CSP header
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
    }

    public function testCanCreatePreflightResponse(): void
    {
        $request = $this->createMockRequest(['Origin' => 'https://example.com']);
        
        $response = $this->headerManager->createPreflightResponse($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(204, $response->getStatusCode());
        
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
    }

    public function testCanGetConfig(): void
    {
        $config = $this->headerManager->getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('cors', $config);
        $this->assertArrayHasKey('csp', $config);
        $this->assertArrayHasKey('security', $config);
    }

    public function testCanUpdateConfig(): void
    {
        $newConfig = [
            'cors' => [
                'enabled' => false
            ]
        ];
        
        $this->headerManager->updateConfig($newConfig);
        $config = $this->headerManager->getConfig();
        
        $this->assertFalse($config['cors']['enabled']);
    }

    public function testCanGetHeadersSummary(): void
    {
        $summary = $this->headerManager->getHeadersSummary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('cors_enabled', $summary);
        $this->assertArrayHasKey('csp_enabled', $summary);
        $this->assertArrayHasKey('hsts_enabled', $summary);
        $this->assertArrayHasKey('total_policies', $summary);
        $this->assertArrayHasKey('is_production', $summary);
    }

    public function testDisabledManagerDoesNotApplyHeaders(): void
    {
        $disabledManager = new SecurityHeadersManager(['enabled' => false]);
        $response = new Response('test content');
        
        $modifiedResponse = $disabledManager->applyHeaders($response);
        
        // Should be the same response object
        $this->assertSame($response, $modifiedResponse);
    }

    public function testCanApplyRateLimitHeaders(): void
    {
        $response = new Response('test content');
        $context = [
            'rate_limit' => [
                'remaining' => 10,
                'limit' => 100,
                'reset' => time() + 3600
            ]
        ];
        
        $modifiedResponse = $this->headerManager->applyHeaders($response, null, $context);
        $headers = $modifiedResponse->getHeaders();
        
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertEquals('10', $headers['X-RateLimit-Remaining']);
        
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertEquals('100', $headers['X-RateLimit-Limit']);
    }

    public function testCustomConfigurationWorks(): void
    {
        $customConfig = [
            'csp' => [
                'enabled' => true,
                'default_src' => ["'none'"],
                'script_src' => ["'self'", 'https://cdn.example.com']
            ],
            'security' => [
                'frame_options' => 'SAMEORIGIN'
            ]
        ];
        
        $manager = new SecurityHeadersManager($customConfig);
        $response = new Response('test content');
        
        $modifiedResponse = $manager->applyHeaders($response);
        $headers = $modifiedResponse->getHeaders();
        
        $this->assertStringContainsString("default-src 'none'", $headers['Content-Security-Policy']);
        $this->assertStringContainsString('https://cdn.example.com', $headers['Content-Security-Policy']);
        $this->assertEquals('SAMEORIGIN', $headers['X-Frame-Options']);
    }

    public function testOriginValidationWorks(): void
    {
        $config = [
            'cors' => [
                'allowed_origins' => ['https://trusted.com', '*.example.com']
            ]
        ];
        
        $manager = new SecurityHeadersManager($config);
        
        // Test exact match
        $response1 = new Response('test content');
        $request1 = $this->createMockRequest(['Origin' => 'https://trusted.com']);
        $response1 = $manager->applyHeaders($response1, $request1);
        $headers1 = $response1->getHeaders();
        $this->assertEquals('https://trusted.com', $headers1['Access-Control-Allow-Origin']);
        
        // Test wildcard match - use a fresh response object
        $response2 = new Response('test content');
        $request2 = $this->createMockRequest(['Origin' => 'https://subdomain.example.com']);
        $response2 = $manager->applyHeaders($response2, $request2);
        $headers2 = $response2->getHeaders();
        $this->assertEquals('https://subdomain.example.com', $headers2['Access-Control-Allow-Origin']);
    }

    /**
     * Create a mock request with optional headers
     */
    private function createMockRequest(array $headers = []): Request
    {
        $serverVars = [];
        foreach ($headers as $name => $value) {
            $serverVars['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        
        return new Request([], [], [], [], $serverVars);
    }
}