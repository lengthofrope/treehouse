<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Router\Route;
use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test cases for the Route class
 * 
 * @package Tests\Unit\Router
 */
class RouteTest extends TestCase
{
    public function testConstructorWithStringMethod(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $this->assertEquals(['GET'], $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
        $this->assertIsCallable($route->getAction());
    }

    public function testConstructorWithArrayMethods(): void
    {
        $methods = ['GET', 'POST'];
        $route = new Route($methods, '/test', function () {
            return 'test';
        });

        $this->assertEquals($methods, $route->getMethods());
        $this->assertEquals('/test', $route->getUri());
    }

    public function testConstructorWithStringAction(): void
    {
        $route = new Route('GET', '/test', 'TestController@index');

        $this->assertEquals('TestController@index', $route->getAction());
    }

    public function testConstructorWithArrayAction(): void
    {
        $action = ['TestController', 'index'];
        $route = new Route('GET', '/test', $action);

        $this->assertEquals($action, $route->getAction());
    }

    public function testGetMiddleware(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $middleware = $route->getMiddleware();
        $this->assertInstanceOf(Collection::class, $middleware);
        $this->assertTrue($middleware->isEmpty());
    }

    public function testAddSingleMiddleware(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $route->middleware('auth');

        $this->assertTrue($route->getMiddleware()->contains('auth'));
        $this->assertEquals(1, $route->getMiddleware()->count());
    }

    public function testAddMultipleMiddleware(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $route->middleware(['auth', 'throttle']);

        $this->assertTrue($route->getMiddleware()->contains('auth'));
        $this->assertTrue($route->getMiddleware()->contains('throttle'));
        $this->assertEquals(2, $route->getMiddleware()->count());
    }

    public function testAddCallableMiddleware(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $middleware = function () {
            return 'middleware';
        };
        $route->middleware($middleware);

        $this->assertTrue($route->getMiddleware()->contains($middleware));
    }

    public function testWhereWithSingleConstraint(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $route->where('id', '[0-9]+');

        $wheres = $this->getPrivateProperty($route, 'wheres');
        $this->assertEquals(['id' => '[0-9]+'], $wheres);
    }

    public function testWhereWithMultipleConstraints(): void
    {
        $route = new Route('GET', '/users/{id}/posts/{postId}', function () {
            return 'post';
        });

        $route->where(['id' => '[0-9]+', 'postId' => '[0-9]+']);

        $wheres = $this->getPrivateProperty($route, 'wheres');
        $this->assertEquals(['id' => '[0-9]+', 'postId' => '[0-9]+'], $wheres);
    }

    public function testDefaultsWithSingleDefault(): void
    {
        $route = new Route('GET', '/users/{id?}', function () {
            return 'user';
        });

        $route->defaults('id', 1);

        $defaults = $this->getPrivateProperty($route, 'defaults');
        $this->assertEquals(['id' => 1], $defaults);
    }

    public function testDefaultsWithMultipleDefaults(): void
    {
        $route = new Route('GET', '/users/{id?}/posts/{postId?}', function () {
            return 'post';
        });

        $route->defaults(['id' => 1, 'postId' => 1]);

        $defaults = $this->getPrivateProperty($route, 'defaults');
        $this->assertEquals(['id' => 1, 'postId' => 1], $defaults);
    }

    public function testName(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $route->name('test.route');

        $this->assertEquals('test.route', $route->getName());
    }

    public function testMatchesWithCorrectMethod(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $this->assertTrue($route->matches('GET', '/test'));
        $this->assertTrue($route->matches('get', '/test')); // Case insensitive
    }

    public function testMatchesWithIncorrectMethod(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $this->assertFalse($route->matches('POST', '/test'));
    }

    public function testMatchesWithMultipleMethods(): void
    {
        $route = new Route(['GET', 'POST'], '/test', function () {
            return 'test';
        });

        $this->assertTrue($route->matches('GET', '/test'));
        $this->assertTrue($route->matches('POST', '/test'));
        $this->assertFalse($route->matches('PUT', '/test'));
    }

    public function testMatchesWithParameters(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $this->assertTrue($route->matches('GET', '/users/123'));
        $this->assertTrue($route->matches('GET', '/users/abc'));
        $this->assertFalse($route->matches('GET', '/users'));
        $this->assertFalse($route->matches('GET', '/users/123/extra'));
    }

    public function testMatchesWithOptionalParameters(): void
    {
        $route = new Route('GET', '/users/{id?}', function () {
            return 'user';
        });

        $this->assertTrue($route->matches('GET', '/users'));
        $this->assertTrue($route->matches('GET', '/users/123'));
    }

    public function testMatchesWithConstraints(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });
        $route->where('id', '[0-9]+');

        $this->assertTrue($route->matches('GET', '/users/123'));
        $this->assertFalse($route->matches('GET', '/users/abc'));
    }

    public function testExtractParametersFromSimpleRoute(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $parameters = $route->extractParameters('/users/123');
        $this->assertEquals(['id' => '123'], $parameters);
    }

    public function testExtractParametersFromMultipleParameters(): void
    {
        $route = new Route('GET', '/users/{id}/posts/{postId}', function () {
            return 'post';
        });

        $parameters = $route->extractParameters('/users/123/posts/456');
        $this->assertEquals(['id' => '123', 'postId' => '456'], $parameters);
    }

    public function testExtractParametersWithOptionalParameter(): void
    {
        $route = new Route('GET', '/users/{id?}', function () {
            return 'user';
        });

        $parameters = $route->extractParameters('/users');
        $this->assertEquals(['id' => null], $parameters);

        $parameters = $route->extractParameters('/users/123');
        $this->assertEquals(['id' => '123'], $parameters);
    }

    public function testExtractParametersWithDefaults(): void
    {
        $route = new Route('GET', '/users/{id?}', function () {
            return 'user';
        });
        $route->defaults('id', 1);

        $parameters = $route->extractParameters('/users');
        $this->assertEquals(['id' => 1], $parameters);
    }

    public function testExtractParametersFromNonMatchingRoute(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $parameters = $route->extractParameters('/posts/123');
        $this->assertEquals([], $parameters);
    }

    public function testUrlGenerationWithoutParameters(): void
    {
        $route = new Route('GET', '/users', function () {
            return 'users';
        });

        $url = $route->url();
        $this->assertEquals('/users', $url);
    }

    public function testUrlGenerationWithParameters(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $url = $route->url(['id' => 123]);
        $this->assertEquals('/users/123', $url);
    }

    public function testUrlGenerationWithMultipleParameters(): void
    {
        $route = new Route('GET', '/users/{id}/posts/{postId}', function () {
            return 'post';
        });

        $url = $route->url(['id' => 123, 'postId' => 456]);
        $this->assertEquals('/users/123/posts/456', $url);
    }

    public function testUrlGenerationWithOptionalParameters(): void
    {
        $route = new Route('GET', '/users/{id?}', function () {
            return 'user';
        });

        $url = $route->url();
        $this->assertEquals('/users', $url);

        $url = $route->url(['id' => 123]);
        $this->assertEquals('/users/123', $url);
    }

    public function testToArray(): void
    {
        $route = new Route(['GET', 'POST'], '/users/{id}', function () {
            return 'user';
        });
        $route->middleware(['auth', 'throttle'])
              ->where('id', '[0-9]+')
              ->defaults('id', 1)
              ->name('user.show');

        $array = $route->toArray();

        $this->assertArrayHasKey('methods', $array);
        $this->assertArrayHasKey('uri', $array);
        $this->assertArrayHasKey('action', $array);
        $this->assertArrayHasKey('middleware', $array);
        $this->assertArrayHasKey('wheres', $array);
        $this->assertArrayHasKey('defaults', $array);
        $this->assertArrayHasKey('name', $array);

        $this->assertEquals(['GET', 'POST'], $array['methods']);
        $this->assertEquals('/users/{id}', $array['uri']);
        $this->assertEquals(['auth', 'throttle'], $array['middleware']);
        $this->assertEquals(['id' => '[0-9]+'], $array['wheres']);
        $this->assertEquals(['id' => 1], $array['defaults']);
        $this->assertEquals('user.show', $array['name']);
    }

    public function testToString(): void
    {
        $route = new Route(['GET', 'POST'], '/users/{id}', function () {
            return 'user';
        });

        $string = (string) $route;
        $this->assertEquals('GET|POST /users/{id}', $string);
    }

    public function testMethodChaining(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $result = $route->middleware('auth')
                       ->where('id', '[0-9]+')
                       ->defaults('id', 1)
                       ->name('test.route');

        $this->assertSame($route, $result);
    }

    public function testComplexRoutePattern(): void
    {
        $route = new Route('GET', '/api/v1/users/{userId}/posts/{postId}/comments/{commentId?}', function () {
            return 'comment';
        });
        $route->where(['userId' => '[0-9]+', 'postId' => '[0-9]+', 'commentId' => '[0-9]+'])
              ->defaults('commentId', null);

        $this->assertTrue($route->matches('GET', '/api/v1/users/123/posts/456/comments/789'));
        $this->assertTrue($route->matches('GET', '/api/v1/users/123/posts/456/comments'));
        $this->assertFalse($route->matches('GET', '/api/v1/users/abc/posts/456/comments/789'));

        $parameters = $route->extractParameters('/api/v1/users/123/posts/456/comments/789');
        $this->assertEquals(['userId' => '123', 'postId' => '456', 'commentId' => '789'], $parameters);

        $parameters = $route->extractParameters('/api/v1/users/123/posts/456/comments');
        $this->assertEquals(['userId' => '123', 'postId' => '456', 'commentId' => null], $parameters);
    }
}