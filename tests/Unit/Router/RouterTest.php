<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Route;
use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Router\RouteCollection;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareStack;
use Tests\TestCase;

/**
 * Test cases for the Router class
 * 
 * @package Tests\Unit\Router
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router(false); // Disable CSRF endpoint for tests
    }

    public function testConstructorInitializesProperties(): void
    {
        $routes = $this->getPrivateProperty($this->router, 'routes');
        $middleware = $this->getPrivateProperty($this->router, 'middleware');
        $groupStack = $this->getPrivateProperty($this->router, 'groupStack');

        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertInstanceOf(MiddlewareStack::class, $middleware);
        $this->assertTrue($groupStack->isEmpty());
    }

    public function testGetRouteRegistration(): void
    {
        $route = $this->router->get('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET', 'HEAD'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testPostRouteRegistration(): void
    {
        $route = $this->router->post('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['POST'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testPutRouteRegistration(): void
    {
        $route = $this->router->put('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['PUT'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testPatchRouteRegistration(): void
    {
        $route = $this->router->patch('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['PATCH'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testDeleteRouteRegistration(): void
    {
        $route = $this->router->delete('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['DELETE'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testOptionsRouteRegistration(): void
    {
        $route = $this->router->options('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['OPTIONS'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testAnyRouteRegistration(): void
    {
        $route = $this->router->any('/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testMatchRouteRegistration(): void
    {
        $methods = ['GET', 'POST'];
        $route = $this->router->match($methods, '/test', function () {
            return 'test';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($methods, $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testRouteGroupWithPrefix(): void
    {
        $this->router->group(['prefix' => 'api'], function (Router $router) {
            $router->get('/users', function () {
                return 'users';
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/api/users', $routes->first()->getUri());
    }

    public function testRouteGroupWithMiddleware(): void
    {
        $this->router->group(['middleware' => 'auth'], function (Router $router) {
            $router->get('/protected', function () {
                return 'protected';
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes->first();
        $this->assertTrue($route->getMiddleware()->contains('auth'));
    }

    public function testNestedRouteGroups(): void
    {
        $this->router->group(['prefix' => 'api'], function (Router $router) {
            $router->group(['prefix' => 'v1'], function (Router $router) {
                $router->get('/users', function () {
                    return 'users';
                });
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $this->assertEquals('/api/v1/users', $routes->first()->getUri());
    }

    public function testGlobalMiddleware(): void
    {
        $this->router->middleware('global');
        
        $middleware = $this->router->getMiddleware();
        $this->assertTrue($middleware->getMiddleware()->contains('global'));
    }

    public function testMiddlewareAliases(): void
    {
        $aliases = ['auth' => 'AuthMiddleware'];
        $this->router->middlewareAliases($aliases);
        
        $middleware = $this->router->getMiddleware();
        $this->assertEquals($aliases, $middleware->getAliases());
    }

    public function testDispatchWithCallableAction(): void
    {
        $this->router->get('/test', function (Request $request) {
            return new Response('Hello World');
        });

        $request = $this->createMockRequest('GET', '/test');
        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testDispatchWithStringResponse(): void
    {
        $this->router->get('/test', function () {
            return 'Hello World';
        });

        $request = $this->createMockRequest('GET', '/test');
        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Hello World', $response->getContent());
    }

    public function testDispatchWithArrayResponse(): void
    {
        $this->router->get('/test', function () {
            return ['message' => 'Hello World'];
        });

        $request = $this->createMockRequest('GET', '/test');
        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('{"message":"Hello World"}', $response->getContent());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
    }

    public function testDispatchNotFound(): void
    {
        $request = $this->createMockRequest('GET', '/nonexistent');
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getContent());
    }

    public function testDispatchWithControllerString(): void
    {
        $this->router->get('/test', 'TestController@index');

        $request = $this->createMockRequest('GET', '/test');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller 'TestController' not found");
        
        $this->router->dispatch($request);
    }

    public function testDispatchWithControllerArray(): void
    {
        $this->router->get('/test', ['TestController', 'index']);

        $request = $this->createMockRequest('GET', '/test');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller 'TestController' not found");
        
        $this->router->dispatch($request);
    }

    public function testDispatchWithInvalidAction(): void
    {
        $this->router->get('/test', 123); // Invalid action type

        $request = $this->createMockRequest('GET', '/test');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid route action');
        
        $this->router->dispatch($request);
    }

    public function testUrlGeneration(): void
    {
        $this->router->get('/users/{id}', function () {
            return 'user';
        })->name('user.show');

        $url = $this->router->url('user.show', ['id' => 123]);
        $this->assertEquals('/users/123', $url);
    }

    public function testUrlGenerationForNonexistentRoute(): void
    {
        $url = $this->router->url('nonexistent');
        $this->assertNull($url);
    }

    public function testGetCurrentRoute(): void
    {
        $this->router->get('/test', function () {
            return 'test';
        });

        $request = $this->createMockRequest('GET', '/test');
        $this->router->dispatch($request);

        $currentRoute = $this->router->getCurrentRoute();
        $this->assertInstanceOf(Route::class, $currentRoute);
        $this->assertEquals('/test', $currentRoute->getUri());
    }

    public function testGetCurrentParameters(): void
    {
        $this->router->get('/users/{id}', function () {
            return 'user';
        });

        $request = $this->createMockRequest('GET', '/users/123');
        $this->router->dispatch($request);

        $parameters = $this->router->getCurrentParameters();
        $this->assertEquals(['id' => '123'], $parameters);
    }

    public function testGetParameter(): void
    {
        $this->router->get('/users/{id}', function () {
            return 'user';
        });

        $request = $this->createMockRequest('GET', '/users/123');
        $this->router->dispatch($request);

        $this->assertEquals('123', $this->router->getParameter('id'));
        $this->assertEquals('default', $this->router->getParameter('nonexistent', 'default'));
    }

    public function testGetRoutes(): void
    {
        $routes = $this->router->getRoutes();
        $this->assertInstanceOf(RouteCollection::class, $routes);
    }

    public function testGetMiddleware(): void
    {
        $middleware = $this->router->getMiddleware();
        $this->assertInstanceOf(MiddlewareStack::class, $middleware);
    }

    public function testGetDebugInfo(): void
    {
        $this->router->get('/test', function () {
            return 'test';
        });
        $this->router->middleware('global');

        $debugInfo = $this->router->getDebugInfo();

        $this->assertArrayHasKey('routes', $debugInfo);
        $this->assertArrayHasKey('middleware', $debugInfo);
        $this->assertArrayHasKey('current_route', $debugInfo);
        $this->assertArrayHasKey('current_parameters', $debugInfo);
        $this->assertArrayHasKey('group_stack', $debugInfo);
    }

    public function testRouteWithParameters(): void
    {
        $this->router->get('/users/{id}/posts/{postId}', function (Request $request, $id, $postId) {
            return "User: $id, Post: $postId";
        });

        $request = $this->createMockRequest('GET', '/users/123/posts/456');
        $response = $this->router->dispatch($request);

        $this->assertEquals('User: 123, Post: 456', $response->getContent());
    }

    public function testMiddlewareExecution(): void
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

        $this->router->middleware($middleware1);
        $this->router->get('/test', function () use (&$executed) {
            $executed[] = 'action';
            return 'test';
        })->middleware($middleware2);

        $request = $this->createMockRequest('GET', '/test');
        $this->router->dispatch($request);

        $this->assertEquals([
            'middleware1_before',
            'middleware2_before',
            'action',
            'middleware2_after',
            'middleware1_after'
        ], $executed);
    }

    private function createMockRequest(string $method, string $path): Request
    {
        return new Request([], [], [], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path
        ]);
    }
}