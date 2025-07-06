<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareStack;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimitMiddleware;

/**
 * Middleware Stack Integration Tests for Rate Limiting
 */
class MiddlewareStackRateLimitTest extends TestCase
{
    public function testMiddlewareStackResolvesThrottleAlias(): void
    {
        $stack = new MiddlewareStack();
        
        // Use reflection to test the built-in middleware resolution
        $reflection = new \ReflectionClass($stack);
        $method = $reflection->getMethod('resolveBuiltInMiddleware');
        $method->setAccessible(true);
        
        $resolved = $method->invoke($stack, 'throttle');
        
        $this->assertEquals('LengthOfRope\TreeHouse\Router\Middleware\RateLimitMiddleware', $resolved);
    }

    public function testMiddlewareStackParsesThrottleParameters(): void
    {
        $stack = new MiddlewareStack();
        
        // Use reflection to test parameter parsing
        $reflection = new \ReflectionClass($stack);
        $method = $reflection->getMethod('parseMiddleware');
        $method->setAccessible(true);
        
        $result = $method->invoke($stack, 'throttle:60,1');
        
        $this->assertEquals(['throttle', [60, 1]], $result);
    }

    public function testMiddlewareStackParsesThrottleParametersWithIdentifier(): void
    {
        $stack = new MiddlewareStack();
        
        // Use reflection to test parameter parsing with identifier
        $reflection = new \ReflectionClass($stack);
        $method = $reflection->getMethod('parseMiddleware');
        $method->setAccessible(true);
        
        $result = $method->invoke($stack, 'throttle:100,5,user');
        
        $this->assertEquals(['throttle', [100, 5, 'user']], $result);
    }

    public function testMiddlewareStackCreatesRateLimitMiddlewareInstance(): void
    {
        $stack = new MiddlewareStack();
        
        // Test that the stack can create a throttle middleware instance
        $reflection = new \ReflectionClass($stack);
        $method = $reflection->getMethod('resolveMiddleware');
        $method->setAccessible(true);
        
        $middleware = $method->invoke($stack, 'throttle:30,2');
        
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware);
    }

    public function testMiddlewareStackHandlesThrottleWithoutParameters(): void
    {
        $stack = new MiddlewareStack();
        
        $reflection = new \ReflectionClass($stack);
        $method = $reflection->getMethod('parseMiddleware');
        $method->setAccessible(true);
        
        $result = $method->invoke($stack, 'throttle');
        
        $this->assertEquals(['throttle', []], $result);
    }

    public function testThrottleAliasIsRecognized(): void
    {
        $stack = new MiddlewareStack();
        
        $builtInMiddleware = [
            'role' => 'LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware',
            'permission' => 'LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware',
            'throttle' => 'LengthOfRope\TreeHouse\Router\Middleware\RateLimitMiddleware',
        ];
        
        // Test that throttle is in the built-in middleware list
        $reflection = new \ReflectionClass($stack);
        $method = $reflection->getMethod('resolveBuiltInMiddleware');
        $method->setAccessible(true);
        
        foreach ($builtInMiddleware as $alias => $className) {
            $resolved = $method->invoke($stack, $alias);
            $this->assertEquals($className, $resolved);
        }
    }
}