<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Router\Exceptions\RateLimitExceededException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

/**
 * Rate Limiting Integration Tests
 * 
 * Tests the complete integration of rate limiting with the router system.
 */
class RateLimitingIntegrationTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router(false, false, false); // Disable CSRF and assets for testing
    }

    public function testRateLimitingWithRouter(): void
    {
        // Register a route with rate limiting
        $this->router->get('/api/test', function($request) {
            return new Response('API Response');
        })->middleware('throttle:2,1'); // 2 requests per minute

        // First request should work
        $request1 = $this->createRequest('GET', '/api/test', '192.168.1.100');
        $response1 = $this->router->dispatch($request1);
        
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals('API Response', $response1->getContent());
        $this->assertTrue($response1->hasHeader('X-RateLimit-Limit'));
        $this->assertEquals('2', $response1->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('1', $response1->getHeader('X-RateLimit-Remaining'));

        // Second request should work
        $request2 = $this->createRequest('GET', '/api/test', '192.168.1.100');
        $response2 = $this->router->dispatch($request2);
        
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals('0', $response2->getHeader('X-RateLimit-Remaining'));

        // Third request should be rate limited
        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded for ip');
        
        $request3 = $this->createRequest('GET', '/api/test', '192.168.1.100');
        $this->router->dispatch($request3);
    }

    public function testDifferentIPsHaveSeparateRateLimits(): void
    {
        $this->router->get('/api/data', function($request) {
            return new Response('Data');
        })->middleware('throttle:1,1');

        // First IP uses up its limit
        $request1 = $this->createRequest('GET', '/api/data', '192.168.1.100');
        $response1 = $this->router->dispatch($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        // Second request from same IP should fail
        $this->expectException(RateLimitExceededException::class);
        $request2 = $this->createRequest('GET', '/api/data', '192.168.1.100');
        $this->router->dispatch($request2);
    }

    public function testDifferentIPsWorkIndependently(): void
    {
        $this->router->get('/api/independent', function($request) {
            return new Response('Independent');
        })->middleware('throttle:1,1');

        // First IP
        $request1 = $this->createRequest('GET', '/api/independent', '192.168.1.100');
        $response1 = $this->router->dispatch($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        // Different IP should still work
        $request2 = $this->createRequest('GET', '/api/independent', '192.168.1.101');
        $response2 = $this->router->dispatch($request2);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testRouteGroupRateLimiting(): void
    {
        $this->router->group(['middleware' => 'throttle:3,1'], function($router) {
            $router->get('/group/endpoint1', function($request) {
                return new Response('Endpoint 1');
            });
            $router->get('/group/endpoint2', function($request) {
                return new Response('Endpoint 2');
            });
        });

        $ip = '192.168.1.200';
        
        // Should be able to hit different endpoints under same rate limit
        $response1 = $this->router->dispatch($this->createRequest('GET', '/group/endpoint1', $ip));
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals('2', $response1->getHeader('X-RateLimit-Remaining'));

        $response2 = $this->router->dispatch($this->createRequest('GET', '/group/endpoint2', $ip));
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals('1', $response2->getHeader('X-RateLimit-Remaining'));

        $response3 = $this->router->dispatch($this->createRequest('GET', '/group/endpoint1', $ip));
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertEquals('0', $response3->getHeader('X-RateLimit-Remaining'));

        // Fourth request should fail
        $this->expectException(RateLimitExceededException::class);
        $this->router->dispatch($this->createRequest('GET', '/group/endpoint2', $ip));
    }

    public function testRouteSpecificRateLimitOverride(): void
    {
        $this->router->group(['middleware' => 'throttle:10,1'], function($router) {
            $router->get('/group/normal', function($request) {
                return new Response('Normal');
            });
            
            $router->get('/group/restricted', function($request) {
                return new Response('Restricted');
            })->middleware('throttle:1,1'); // Override group middleware
        });

        $ip = '192.168.1.300';

        // Normal endpoint should have group limit (10)
        $response1 = $this->router->dispatch($this->createRequest('GET', '/group/normal', $ip));
        $this->assertEquals('10', $response1->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('9', $response1->getHeader('X-RateLimit-Remaining'));

        // Restricted endpoint should have its own limit (1), separate from group
        $response2 = $this->router->dispatch($this->createRequest('GET', '/group/restricted', $ip));
        $this->assertEquals('1', $response2->getHeader('X-RateLimit-Limit'));
        $this->assertEquals('0', $response2->getHeader('X-RateLimit-Remaining'));

        // Second request to restricted should fail
        $this->expectException(RateLimitExceededException::class);
        $this->router->dispatch($this->createRequest('GET', '/group/restricted', $ip));
    }

    public function testRateLimitExceptionContainsCorrectHeaders(): void
    {
        $this->router->get('/api/headers', function($request) {
            return new Response('Headers Test');
        })->middleware('throttle:1,5'); // 1 request per 5 minutes

        $ip = '192.168.1.400';

        // First request works
        $response1 = $this->router->dispatch($this->createRequest('GET', '/api/headers', $ip));
        $this->assertEquals(200, $response1->getStatusCode());

        // Second request throws exception with correct headers
        try {
            $this->router->dispatch($this->createRequest('GET', '/api/headers', $ip));
            $this->fail('Expected RateLimitExceededException to be thrown');
        } catch (RateLimitExceededException $e) {
            $headers = $e->getHeaders();
            
            $this->assertEquals('1', $headers['X-RateLimit-Limit']);
            $this->assertEquals('0', $headers['X-RateLimit-Remaining']);
            $this->assertEquals('300', $headers['Retry-After']); // 5 minutes = 300 seconds
            $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
            $this->assertIsNumeric($headers['X-RateLimit-Reset']);
        }
    }

    /**
     * Create a test request
     */
    private function createRequest(string $method, string $uri, string $ip): Request
    {
        return new Request(
            [], // query
            [], // request
            [], // files
            [], // cookies
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => $ip,
                'HTTP_HOST' => 'example.com'
            ], // server
            null // content
        );
    }
}