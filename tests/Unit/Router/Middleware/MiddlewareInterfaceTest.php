<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;
use Tests\TestCase;

/**
 * Test cases for the MiddlewareInterface
 * 
 * @package Tests\Unit\Router\Middleware
 */
class MiddlewareInterfaceTest extends TestCase
{
    public function testMiddlewareInterfaceCanBeImplemented(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
        $this->assertTrue(method_exists($middleware, 'handle'));
    }

    public function testMiddlewareInterfaceHandleMethod(): void
    {
        $executed = false;

        $middleware = new class($executed) implements MiddlewareInterface {
            public function __construct(private bool &$executed) {}

            public function handle(Request $request, callable $next): Response
            {
                $this->executed = true;
                return $next($request);
            }
        };

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $next = function (Request $request) use (&$executed): Response {
            return new Response('test');
        };

        $response = $middleware->handle($request, $next);

        $this->assertTrue($executed);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('test', $response->getContent());
    }

    public function testMiddlewareCanModifyResponse(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Middleware', 'executed');
                return $response;
            }
        };

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $next = function (Request $request): Response {
            return new Response('original');
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals('original', $response->getContent());
        $this->assertEquals('executed', $response->getHeader('X-Middleware'));
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $nextCalled = false;

        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                // Short-circuit without calling next
                return new Response('short-circuited', 401);
            }
        };

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('should not be called');
        };

        $response = $middleware->handle($request, $next);

        $this->assertFalse($nextCalled);
        $this->assertEquals('short-circuited', $response->getContent());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMiddlewareChaining(): void
    {
        $order = [];

        $middleware1 = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function handle(Request $request, callable $next): Response
            {
                $this->order[] = 'middleware1_before';
                $response = $next($request);
                $this->order[] = 'middleware1_after';
                return $response;
            }
        };

        $middleware2 = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function handle(Request $request, callable $next): Response
            {
                $this->order[] = 'middleware2_before';
                $response = $next($request);
                $this->order[] = 'middleware2_after';
                return $response;
            }
        };

        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);

        // Chain middleware manually to test the interface
        $finalHandler = function (Request $request) use (&$order): Response {
            $order[] = 'final_handler';
            return new Response('final');
        };

        $chain = function (Request $request) use ($middleware2, $finalHandler): Response {
            return $middleware2->handle($request, $finalHandler);
        };

        $response = $middleware1->handle($request, $chain);

        $this->assertEquals([
            'middleware1_before',
            'middleware2_before',
            'final_handler',
            'middleware2_after',
            'middleware1_after'
        ], $order);

        $this->assertEquals('final', $response->getContent());
    }
}