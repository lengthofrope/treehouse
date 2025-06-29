<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Test CSRF endpoint origin validation
 */
class CsrfOriginValidationTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(true); // Enable CSRF endpoint
    }

    public function testCsrfEndpointAllowsSameOriginRequests(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_ORIGIN' => 'https://example.com',
                'HTTPS' => 'on'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('field', $data);
        $this->assertEquals('_token', $data['field']);
    }

    public function testCsrfEndpointAllowsSameOriginWithReferer(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_REFERER' => 'https://example.com/some/page',
                'HTTPS' => 'on'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testCsrfEndpointBlocksCrossOriginRequests(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_ORIGIN' => 'https://malicious.com',
                'HTTPS' => 'on'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type'));
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertStringContainsString('Cross-origin requests are not allowed', $data['message']);
    }

    public function testCsrfEndpointBlocksCrossOriginReferer(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_REFERER' => 'https://malicious.com/attack',
                'HTTPS' => 'on'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
    }

    public function testCsrfEndpointBlocksRequestsWithoutOriginOrReferer(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTPS' => 'on'
                // No Origin or Referer headers
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
    }

    public function testCsrfEndpointHandlesDifferentPorts(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com:8080',
                'HTTP_ORIGIN' => 'http://example.com:8080',
                'SERVER_PORT' => '8080'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testCsrfEndpointBlocksDifferentPorts(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com:8080',
                'HTTP_ORIGIN' => 'http://example.com:9000', // Different port
                'SERVER_PORT' => '8080'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
    }

    public function testCsrfEndpointHandlesHttpVsHttps(): void
    {
        // HTTP request with HTTP origin should work
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_ORIGIN' => 'http://example.com',
                'SERVER_PORT' => '80'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testCsrfEndpointBlocksHttpsOriginOnHttpRequest(): void
    {
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_ORIGIN' => 'https://example.com', // HTTPS origin
                'SERVER_PORT' => '80' // HTTP request
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $data['error']);
    }

    public function testCsrfEndpointOriginTakesPrecedenceOverReferer(): void
    {
        // Valid Origin but invalid Referer - should succeed because Origin takes precedence
        $request = new Request(
            query: [],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/_csrf/token',
                'HTTP_HOST' => 'example.com',
                'HTTP_ORIGIN' => 'https://example.com', // Valid
                'HTTP_REFERER' => 'https://malicious.com/attack', // Invalid
                'HTTPS' => 'on'
            ]
        );

        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }
}