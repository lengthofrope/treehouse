<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Test CSRF token endpoint functionality
 */
class CsrfEndpointTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        // Clean up session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testCsrfTokenEndpointExists(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on'
        ]);

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testCsrfTokenEndpointReturnsValidJson(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on'
        ]);

        $response = $this->router->dispatch($request);
        
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('field', $data);
        $this->assertEquals('_token', $data['field']);
        $this->assertIsString($data['token']);
        $this->assertNotEmpty($data['token']);
    }

    public function testCsrfTokenEndpointHasNoCacheHeaders(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on'
        ]);

        $response = $this->router->dispatch($request);
        
        $this->assertEquals('no-cache, no-store, must-revalidate', $response->getHeader('Cache-Control'));
        $this->assertEquals('no-cache', $response->getHeader('Pragma'));
        $this->assertEquals('0', $response->getHeader('Expires'));
    }

    public function testCsrfTokenEndpointWithAjaxHeaders(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testCsrfTokenEndpointReturnsConsistentTokenInSameSession(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on'
        ]);

        // First request
        $response1 = $this->router->dispatch($request);
        $data1 = json_decode($response1->getContent(), true);
        
        // Second request (same session)
        $response2 = $this->router->dispatch($request);
        $data2 = json_decode($response2->getContent(), true);
        
        $this->assertEquals($data1['token'], $data2['token']);
    }

    public function testCsrfTokenEndpointOnlyAcceptsGetRequests(): void
    {
        $postRequest = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/_csrf/token'
        ]);

        // Should throw RouteNotFoundException since POST is not registered for this endpoint
        $this->expectException(\LengthOfRope\TreeHouse\Router\Exceptions\RouteNotFoundException::class);
        $this->expectExceptionMessage('Route not found: POST /_csrf/token');
        
        $this->router->dispatch($postRequest);
    }

    public function testCsrfTokenIsValidLength(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on'
        ]);

        $response = $this->router->dispatch($request);
        $data = json_decode($response->getContent(), true);
        
        $token = $data['token'];
        $this->assertGreaterThanOrEqual(16, strlen($token));
        $this->assertLessThanOrEqual(256, strlen($token));
    }

    public function testCsrfTokenIsHexadecimal(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/_csrf/token',
            'HTTP_HOST' => 'example.com',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTPS' => 'on'
        ]);

        $response = $this->router->dispatch($request);
        $data = json_decode($response->getContent(), true);
        
        $token = $data['token'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }
}