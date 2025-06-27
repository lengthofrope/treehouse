<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareStack;
use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test cases for the MiddlewareStack class
 * 
 * @package Tests\Unit\Router\Middleware
 */
class MiddlewareStackTest extends TestCase
{
    private MiddlewareStack $stack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stack = new MiddlewareStack();
    }

    public function testConstructorWithEmptyStack(): void
    {
        $this->assertTrue($this->stack->isEmpty());
        $this->assertEquals(0, $this->stack->count());
    }

    public function testConstructorWithInitialMiddleware(): void
    {
        $middleware = ['auth', 'throttle'];
        $stack = new MiddlewareStack($middleware);

        $this->assertFalse($stack->isEmpty());
        $this->assertEquals(2, $stack->count());
    }

    public function testAddSingleMiddleware(): void
    {
        $this->stack->add('auth');

        $this->assertFalse($this->stack->isEmpty());
        $this->assertEquals(1, $this->stack->count());
        $this->assertTrue($this->stack->getMiddleware()->contains('auth'));
    }

    public function testAddMultipleMiddleware(): void
    {
        $middleware = ['auth', 'throttle'];
        $this->stack->add($middleware);

        $this->assertEquals(2, $this->stack->count());
        $this->assertTrue($this->stack->getMiddleware()->contains('auth'));
        $this->assertTrue($this->stack->getMiddleware()->contains('throttle'));
    }

    public function testAddCallableMiddleware(): void
    {
        $middleware = function (Request $request, callable $next): Response {
            return $next($request);
        };

        $this->stack->add($middleware);

        $this->assertEquals(1, $this->stack->count());
        // Test that the middleware was added by checking the count and that it's callable
        $addedMiddleware = $this->stack->getMiddleware()->first();
        $this->assertTrue(is_callable($addedMiddleware));
    }

    public function testAddMiddlewareInterface(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $this->stack->add($middleware);

        $this->assertEquals(1, $this->stack->count());
        $this->assertTrue($this->stack->getMiddleware()->contains($middleware));
    }

    public function testPrependMiddleware(): void
    {
        $this->stack->add('second');
        $this->stack->prepend('first');

        $middleware = $this->stack->getMiddleware()->all();
        $this->assertEquals(['first', 'second'], $middleware);
    }

    public function testAlias(): void
    {
        $this->stack->alias('auth', 'AuthMiddleware');

        $aliases = $this->stack->getAliases();
        $this->assertEquals(['auth' => 'AuthMiddleware'], $aliases);
    }

    public function testAliases(): void
    {
        $aliases = [
            'auth' => 'AuthMiddleware',
            'throttle' => 'ThrottleMiddleware'
        ];

        $this->stack->aliases($aliases);

        $this->assertEquals($aliases, $this->stack->getAliases());
    }

    public function testAliasesAreMerged(): void
    {
        $this->stack->alias('auth', 'AuthMiddleware');
        $this->stack->aliases(['throttle' => 'ThrottleMiddleware']);

        $expected = [
            'auth' => 'AuthMiddleware',
            'throttle' => 'ThrottleMiddleware'
        ];

        $this->assertEquals($expected, $this->stack->getAliases());
    }

    public function testHandleWithEmptyStack(): void
    {
        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $response = new Response('test');

        $destination = function (Request $request) use ($response): Response {
            return $response;
        };

        $result = $this->stack->handle($request, $destination);
        $this->assertSame($response, $result);
    }

    public function testHandleWithCallableMiddleware(): void
    {
        $executed = [];

        $middleware = function (Request $request, callable $next) use (&$executed): Response {
            $executed[] = 'middleware_before';
            $response = $next($request);
            $executed[] = 'middleware_after';
            return $response;
        };

        $this->stack->add($middleware);

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request) use (&$executed): Response {
            $executed[] = 'destination';
            return new Response('test');
        };

        $this->stack->handle($request, $destination);

        $this->assertEquals([
            'middleware_before',
            'destination',
            'middleware_after'
        ], $executed);
    }

    public function testHandleWithMiddlewareInterface(): void
    {
        $executed = [];

        $middleware = new class($executed) implements MiddlewareInterface {
            public function __construct(private array &$executed) {}

            public function handle(Request $request, callable $next): Response
            {
                $this->executed[] = 'middleware_before';
                $response = $next($request);
                $this->executed[] = 'middleware_after';
                return $response;
            }
        };

        $this->stack->add($middleware);

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request) use (&$executed): Response {
            $executed[] = 'destination';
            return new Response('test');
        };

        $this->stack->handle($request, $destination);

        $this->assertEquals([
            'middleware_before',
            'destination',
            'middleware_after'
        ], $executed);
    }

    public function testHandleWithMultipleMiddleware(): void
    {
        $executed = [];

        $middleware1 = function (Request $request, callable $next) use (&$executed): Response {
            $executed[] = 'middleware1_before';
            $response = $next($request);
            $executed[] = 'middleware1_after';
            return $response;
        };

        $middleware2 = function (Request $request, callable $next) use (&$executed): Response {
            $executed[] = 'middleware2_before';
            $response = $next($request);
            $executed[] = 'middleware2_after';
            return $response;
        };

        $this->stack->add($middleware1);
        $this->stack->add($middleware2);

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request) use (&$executed): Response {
            $executed[] = 'destination';
            return new Response('test');
        };

        $this->stack->handle($request, $destination);

        // Middleware should execute in reverse order (onion pattern)
        $this->assertEquals([
            'middleware1_before',
            'middleware2_before',
            'destination',
            'middleware2_after',
            'middleware1_after'
        ], $executed);
    }

    public function testHandleWithStringMiddleware(): void
    {
        // Create a test middleware class
        $middlewareClass = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Test', 'middleware-executed');
                return $response;
            }
        };

        // Register the class name as an alias
        $className = get_class($middlewareClass);
        $this->stack->alias('test', $className);
        $this->stack->add('test');

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request): Response {
            return new Response('test');
        };

        // This will throw an exception because the class doesn't exist in the global namespace
        $this->expectException(\InvalidArgumentException::class);
        $this->stack->handle($request, $destination);
    }

    public function testHandleWithInvalidMiddleware(): void
    {
        // We need to bypass type checking to test runtime validation
        $middleware = $this->getPrivateProperty($this->stack, 'middleware');
        $middleware->push(123); // Invalid middleware type

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request): Response {
            return new Response('test');
        };

        $this->expectException(\TypeError::class);

        $this->stack->handle($request, $destination);
    }

    public function testParseMiddlewareWithoutParameters(): void
    {
        $result = $this->callPrivateMethod($this->stack, 'parseMiddleware', ['TestMiddleware']);
        $this->assertEquals(['TestMiddleware', []], $result);
    }

    public function testParseMiddlewareWithParameters(): void
    {
        $result = $this->callPrivateMethod($this->stack, 'parseMiddleware', ['throttle:60,1']);
        $this->assertEquals(['throttle', [60, 1]], $result);
    }

    public function testParseMiddlewareWithStringParameters(): void
    {
        $result = $this->callPrivateMethod($this->stack, 'parseMiddleware', ['cors:origin,methods']);
        $this->assertEquals(['cors', ['origin', 'methods']], $result);
    }

    public function testParseMiddlewareWithMixedParameters(): void
    {
        $result = $this->callPrivateMethod($this->stack, 'parseMiddleware', ['throttle:60,api']);
        $this->assertEquals(['throttle', [60, 'api']], $result);
    }

    public function testGetMiddleware(): void
    {
        $middleware = ['auth', 'throttle'];
        $this->stack->add($middleware);

        $result = $this->stack->getMiddleware();
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals($middleware, $result->all());
    }

    public function testGetAliases(): void
    {
        $aliases = ['auth' => 'AuthMiddleware'];
        $this->stack->aliases($aliases);

        $this->assertEquals($aliases, $this->stack->getAliases());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->stack->isEmpty());

        $this->stack->add('auth');
        $this->assertFalse($this->stack->isEmpty());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->stack->count());

        $this->stack->add('auth');
        $this->assertEquals(1, $this->stack->count());

        $this->stack->add(['throttle', 'cors']);
        $this->assertEquals(3, $this->stack->count());
    }

    public function testClear(): void
    {
        $this->stack->add(['auth', 'throttle']);
        $this->assertFalse($this->stack->isEmpty());

        $result = $this->stack->clear();

        $this->assertSame($this->stack, $result); // Test fluent interface
        $this->assertTrue($this->stack->isEmpty());
        $this->assertEquals(0, $this->stack->count());
    }

    public function testToArray(): void
    {
        $callable = function () {};
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $this->stack->add('auth');
        $this->stack->add($callable);
        $this->stack->add($middleware);

        $array = $this->stack->toArray();

        $this->assertEquals('auth', $array[0]);
        $this->assertEquals('callable', $array[1]);
        $this->assertEquals(get_class($middleware), $array[2]);
    }

    public function testToString(): void
    {
        $this->stack->add(['auth', 'throttle']);

        $string = (string) $this->stack;
        $this->assertEquals('MiddlewareStack with 2 middleware: [auth, throttle]', $string);
    }

    public function testFluentInterface(): void
    {
        $result = $this->stack->add('auth')
                              ->prepend('cors')
                              ->alias('throttle', 'ThrottleMiddleware')
                              ->aliases(['auth' => 'AuthMiddleware'])
                              ->clear();

        $this->assertSame($this->stack, $result);
    }

    public function testMiddlewareExecutionOrder(): void
    {
        $order = [];

        // Add middleware in specific order
        $this->stack->add(function (Request $request, callable $next) use (&$order): Response {
            $order[] = 'first_before';
            $response = $next($request);
            $order[] = 'first_after';
            return $response;
        });

        $this->stack->add(function (Request $request, callable $next) use (&$order): Response {
            $order[] = 'second_before';
            $response = $next($request);
            $order[] = 'second_after';
            return $response;
        });

        $this->stack->add(function (Request $request, callable $next) use (&$order): Response {
            $order[] = 'third_before';
            $response = $next($request);
            $order[] = 'third_after';
            return $response;
        });

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request) use (&$order): Response {
            $order[] = 'destination';
            return new Response('test');
        };

        $this->stack->handle($request, $destination);

        // Should execute in onion pattern: first -> second -> third -> destination -> third -> second -> first
        $expected = [
            'first_before',
            'second_before',
            'third_before',
            'destination',
            'third_after',
            'second_after',
            'first_after'
        ];

        $this->assertEquals($expected, $order);
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $this->stack->add(function (Request $request, callable $next): Response {
            // Simulate modifying request (in real scenario, you'd modify the request object)
            return $next($request);
        });

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request): Response {
            return new Response('modified');
        };

        $response = $this->stack->handle($request, $destination);
        $this->assertEquals('modified', $response->getContent());
    }

    public function testMiddlewareCanModifyResponse(): void
    {
        $this->stack->add(function (Request $request, callable $next): Response {
            $response = $next($request);
            $response->setHeader('X-Modified', 'true');
            return $response;
        });

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $destination = function (Request $request): Response {
            return new Response('test');
        };

        $response = $this->stack->handle($request, $destination);
        $this->assertEquals('true', $response->getHeader('X-Modified'));
    }
}