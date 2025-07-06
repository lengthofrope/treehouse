<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitMiddleware;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthorizationException;

/**
 * Rate Limit Middleware Test
 *
 * Tests the rate limiting middleware functionality including
 * limit enforcement, header management, and configuration parsing.
 *
 * @package Tests\Unit\Router\Middleware\RateLimit
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitMiddlewareTest extends TestCase
{
    /**
     * Test middleware creation with parameters
     */
    public function testMiddlewareCreationWithParameters(): void
    {
        $middleware = new RateLimitMiddleware('60,1');
        
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware);
        $this->assertIsArray($middleware->getConfig());
    }

    /**
     * Test middleware creation with configuration array
     */
    public function testMiddlewareCreationWithConfig(): void
    {
        $config = [
            'enabled' => true,
            'headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining']
        ];
        
        $middleware = new RateLimitMiddleware($config);
        
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware);
        $this->assertEquals($config, $middleware->getConfig());
    }

    /**
     * Test middleware allows request when no configuration
     */
    public function testMiddlewareAllowsRequestWhenNoConfig(): void
    {
        $middleware = new RateLimitMiddleware();
        $request = $this->createMockRequest();
        
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('OK', 200);
        };
        
        $response = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware allows request when disabled
     */
    public function testMiddlewareAllowsRequestWhenDisabled(): void
    {
        $middleware = new RateLimitMiddleware(['enabled' => false]);
        $request = $this->createMockRequest();
        
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('OK', 200);
        };
        
        $response = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test factory methods
     */
    public function testFactoryMethods(): void
    {
        $middleware1 = RateLimitMiddleware::fromParameters('60,1');
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware1);
        
        $middleware2 = RateLimitMiddleware::withConfig(['enabled' => true]);
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware2);
    }

    /**
     * Test configuration management
     */
    public function testConfigurationManagement(): void
    {
        $middleware = new RateLimitMiddleware();
        
        $config = ['test' => 'value'];
        $middleware->setConfig($config);
        
        $this->assertEquals($config, $middleware->getConfig());
    }

    /**
     * Test component access
     */
    public function testComponentAccess(): void
    {
        $middleware = new RateLimitMiddleware();
        
        $this->assertNotNull($middleware->getManager());
        $this->assertNotNull($middleware->getHeaders());
    }

    /**
     * Test statistics method with no configuration
     */
    public function testStatisticsWithNoConfig(): void
    {
        $middleware = new RateLimitMiddleware();
        $request = $this->createMockRequest();
        
        $stats = $middleware->getStatistics($request);
        
        $this->assertNull($stats);
    }

    /**
     * Test error handling in middleware
     */
    public function testErrorHandlingInMiddleware(): void
    {
        // Create middleware with no configuration to test error path
        $middleware = new RateLimitMiddleware();
        $request = $this->createMockRequest();
        
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('OK', 200);
        };
        
        // Should not throw exception, should continue to next middleware
        $response = $middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Create a mock HTTP request for testing
     */
    private function createMockRequest(): Request
    {
        return new Request(
            [], // query
            [], // request
            [], // files
            [], // cookies
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test', 'REMOTE_ADDR' => '127.0.0.1'], // server
            null // content
        );
    }
}