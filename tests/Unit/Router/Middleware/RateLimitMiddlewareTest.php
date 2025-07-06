<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimitMiddleware;
use LengthOfRope\TreeHouse\Router\Exceptions\RateLimitExceededException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

/**
 * Rate Limit Middleware Tests
 */
class RateLimitMiddlewareTest extends TestCase
{
    public function testMiddlewareAllowsRequestWithinLimit(): void
    {
        $middleware = new RateLimitMiddleware(100, 1, 'ip');

        $request = $this->createRequest();
        $nextCalled = false;
        
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success');
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Success', $response->getContent());
        
        // Check rate limit headers are added
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
    }

    public function testMiddlewareThrowsExceptionWhenLimitExceeded(): void
    {
        // Use very small limit to trigger rate limit quickly
        $middleware = new RateLimitMiddleware(1, 1, 'ip');

        $request = $this->createRequest();
        
        $next = function ($request) {
            return new Response('Success');
        };

        // First request should succeed
        $response1 = $middleware->handle($request, $next);
        $this->assertEquals('Success', $response1->getContent());

        // Second request should throw exception
        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded for ip');
        
        $middleware->handle($request, $next);
    }

    public function testMiddlewareUsesDefaultConfiguration(): void
    {
        $middleware = new RateLimitMiddleware();

        // Use reflection to check default config
        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('defaultConfig');
        $property->setAccessible(true);
        $config = $property->getValue($middleware);

        $this->assertEquals(60, $config['requests']);
        $this->assertEquals(1, $config['minutes']);
        $this->assertEquals('ip', $config['identifier']);
    }

    public function testMiddlewareWithCustomParameters(): void
    {
        $requests = 50;
        $minutes = 5;
        $identifier = 'user';
        
        $middleware = new RateLimitMiddleware($requests, $minutes, $identifier);

        // Use reflection to check config
        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('defaultConfig');
        $property->setAccessible(true);
        $config = $property->getValue($middleware);

        $this->assertEquals($requests, $config['requests']);
        $this->assertEquals($minutes, $config['minutes']);
        $this->assertEquals($identifier, $config['identifier']);
    }

    public function testGenerateKeyWithIpIdentifier(): void
    {
        $middleware = new RateLimitMiddleware(60, 1, 'ip');

        $request = $this->createRequest([
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('generateKey');
        $method->setAccessible(true);
        
        $key = $method->invoke($middleware, $request, 'ip');
        
        $this->assertEquals('192.168.1.100', $key);
    }

    public function testGenerateKeyWithForwardedFor(): void
    {
        $middleware = new RateLimitMiddleware(60, 1, 'ip');

        $request = $this->createRequest([
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195, 70.41.3.18, 150.172.238.178',
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('generateKey');
        $method->setAccessible(true);
        
        $key = $method->invoke($middleware, $request, 'ip');
        
        // Should use the first public IP from X-Forwarded-For
        $this->assertEquals('203.0.113.195', $key);
    }

    public function testGetClientIpFallsBackToRemoteAddr(): void
    {
        $middleware = new RateLimitMiddleware(60, 1, 'ip');

        $request = $this->createRequest([
            'REMOTE_ADDR' => '127.0.0.1'
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('getClientIp');
        $method->setAccessible(true);
        
        $ip = $method->invoke($middleware, $request);
        
        // Should fallback to REMOTE_ADDR even if it's private
        $this->assertEquals('127.0.0.1', $ip);
    }

    public function testRateLimitHeadersAreAddedToResponse(): void
    {
        $middleware = new RateLimitMiddleware(10, 1, 'ip');

        $request = $this->createRequest();
        
        $next = function ($request) {
            $response = new Response('Success');
            return $response;
        };

        $response = $middleware->handle($request, $next);

        // Check all required headers are present
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
        
        $this->assertEquals('10', $response->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('9', $response->getHeader('X-RateLimit-Remaining')); // After first request
        $this->assertIsNumeric($response->getHeader('X-RateLimit-Reset'));
    }

    public function testUserKeyGenerationFallsBackToIp(): void
    {
        $middleware = new RateLimitMiddleware(60, 1, 'user');

        $request = $this->createRequest([
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('generateKey');
        $method->setAccessible(true);
        
        $key = $method->invoke($middleware, $request, 'user');
        
        // Should fallback to IP-based key
        $this->assertEquals('ip:192.168.1.100', $key);
    }

    /**
     * Create a test request with optional server variables
     */
    private function createRequest(array $server = []): Request
    {
        $defaultServer = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'example.com'
        ];

        return new Request(
            [],      // query
            [],      // request
            [],      // files
            [],      // cookies
            array_merge($defaultServer, $server), // server
            null     // content
        );
    }
}