<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Router\Route;
use LengthOfRope\TreeHouse\Router\RouteCollection;
use LengthOfRope\TreeHouse\Support\Collection;
use Tests\TestCase;

/**
 * Test cases for the RouteCollection class
 * 
 * @package Tests\Unit\Router
 */
class RouteCollectionTest extends TestCase
{
    private RouteCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collection = new RouteCollection();
    }

    public function testConstructorInitializesEmptyCollection(): void
    {
        $this->assertTrue($this->collection->isEmpty());
        $this->assertEquals(0, $this->collection->count());
    }

    public function testAddRoute(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $this->collection->add($route);

        $this->assertFalse($this->collection->isEmpty());
        $this->assertEquals(1, $this->collection->count());
        $this->assertTrue($this->collection->getRoutes()->contains($route));
    }

    public function testAddMultipleRoutes(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);

        $this->assertEquals(2, $this->collection->count());
        $this->assertTrue($this->collection->getRoutes()->contains($route1));
        $this->assertTrue($this->collection->getRoutes()->contains($route2));
    }

    public function testAddRouteWithMultipleMethods(): void
    {
        $route = new Route(['GET', 'POST'], '/test', function () {
            return 'test';
        });

        $this->collection->add($route);

        $getRoutes = $this->collection->getRoutesByMethod('GET');
        $postRoutes = $this->collection->getRoutesByMethod('POST');

        $this->assertTrue($getRoutes->contains($route));
        $this->assertTrue($postRoutes->contains($route));
    }

    public function testAddNamedRoute(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });
        $route->name('test.route');

        $this->collection->add($route);

        $this->assertTrue($this->collection->hasNamedRoute('test.route'));
        $this->assertSame($route, $this->collection->getByName('test.route'));
    }

    public function testMatchExistingRoute(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $this->collection->add($route);

        $matched = $this->collection->match('GET', '/users/123');
        $this->assertSame($route, $matched);
    }

    public function testMatchNonExistingRoute(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });

        $this->collection->add($route);

        $matched = $this->collection->match('POST', '/users/123');
        $this->assertNull($matched);
    }

    public function testMatchWithUnsupportedMethod(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $this->collection->add($route);

        $matched = $this->collection->match('PATCH', '/test');
        $this->assertNull($matched);
    }

    public function testMatchCaseInsensitive(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });

        $this->collection->add($route);

        $matched = $this->collection->match('get', '/test');
        $this->assertSame($route, $matched);
    }

    public function testGetByNameExisting(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });
        $route->name('test.route');

        $this->collection->add($route);

        $found = $this->collection->getByName('test.route');
        $this->assertSame($route, $found);
    }

    public function testGetByNameNonExisting(): void
    {
        $found = $this->collection->getByName('nonexistent');
        $this->assertNull($found);
    }

    public function testGetRoutes(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);

        $routes = $this->collection->getRoutes();
        $this->assertInstanceOf(Collection::class, $routes);
        $this->assertEquals(2, $routes->count());
        $this->assertTrue($routes->contains($route1));
        $this->assertTrue($routes->contains($route2));
    }

    public function testGetRoutesByMethod(): void
    {
        $getRoute = new Route('GET', '/test', function () {
            return 'get';
        });
        $postRoute = new Route('POST', '/test', function () {
            return 'post';
        });

        $this->collection->add($getRoute);
        $this->collection->add($postRoute);

        $getRoutes = $this->collection->getRoutesByMethod('GET');
        $postRoutes = $this->collection->getRoutesByMethod('POST');
        $putRoutes = $this->collection->getRoutesByMethod('PUT');

        $this->assertEquals(1, $getRoutes->count());
        $this->assertTrue($getRoutes->contains($getRoute));

        $this->assertEquals(1, $postRoutes->count());
        $this->assertTrue($postRoutes->contains($postRoute));

        $this->assertEquals(0, $putRoutes->count());
    }

    public function testGetNamedRoutes(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route1->name('test1');

        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });
        $route2->name('test2');

        $route3 = new Route('PUT', '/test3', function () {
            return 'test3';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $namedRoutes = $this->collection->getNamedRoutes();

        $this->assertCount(2, $namedRoutes);
        $this->assertArrayHasKey('test1', $namedRoutes);
        $this->assertArrayHasKey('test2', $namedRoutes);
        $this->assertSame($route1, $namedRoutes['test1']);
        $this->assertSame($route2, $namedRoutes['test2']);
    }

    public function testHasNamedRoute(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });
        $route->name('test.route');

        $this->collection->add($route);

        $this->assertTrue($this->collection->hasNamedRoute('test.route'));
        $this->assertFalse($this->collection->hasNamedRoute('nonexistent'));
    }

    public function testUrlGeneration(): void
    {
        $route = new Route('GET', '/users/{id}', function () {
            return 'user';
        });
        $route->name('user.show');

        $this->collection->add($route);

        $url = $this->collection->url('user.show', ['id' => 123]);
        $this->assertEquals('/users/123', $url);
    }

    public function testUrlGenerationForNonExistentRoute(): void
    {
        $url = $this->collection->url('nonexistent');
        $this->assertNull($url);
    }

    public function testClear(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });
        $route->name('test.route');

        $this->collection->add($route);
        $this->assertFalse($this->collection->isEmpty());

        $this->collection->clear();

        $this->assertTrue($this->collection->isEmpty());
        $this->assertEquals(0, $this->collection->count());
        $this->assertFalse($this->collection->hasNamedRoute('test.route'));
    }

    public function testGetRoutesByPattern(): void
    {
        $route1 = new Route('GET', '/users', function () {
            return 'users';
        });
        $route2 = new Route('POST', '/users', function () {
            return 'create user';
        });
        $route3 = new Route('GET', '/posts', function () {
            return 'posts';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $userRoutes = $this->collection->getRoutesByPattern('/users');
        $this->assertEquals(2, $userRoutes->count());
        $this->assertTrue($userRoutes->contains($route1));
        $this->assertTrue($userRoutes->contains($route2));

        $postRoutes = $this->collection->getRoutesByPattern('/posts');
        $this->assertEquals(1, $postRoutes->count());
        $this->assertTrue($postRoutes->contains($route3));
    }

    public function testGetRoutesByMiddleware(): void
    {
        $route1 = new Route('GET', '/public', function () {
            return 'public';
        });

        $route2 = new Route('GET', '/protected', function () {
            return 'protected';
        });
        $route2->middleware('auth');

        $route3 = new Route('GET', '/admin', function () {
            return 'admin';
        });
        $route3->middleware(['auth', 'admin']);

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $authRoutes = $this->collection->getRoutesByMiddleware('auth');
        $this->assertEquals(2, $authRoutes->count());
        $this->assertTrue($authRoutes->contains($route2));
        $this->assertTrue($authRoutes->contains($route3));

        $adminRoutes = $this->collection->getRoutesByMiddleware('admin');
        $this->assertEquals(1, $adminRoutes->count());
        $this->assertTrue($adminRoutes->contains($route3));
    }

    public function testGetAllMethods(): void
    {
        $route1 = new Route(['GET', 'HEAD'], '/test1', function () {
            return 'test1';
        });
        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });
        $route3 = new Route(['PUT', 'PATCH'], '/test3', function () {
            return 'test3';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $methods = $this->collection->getAllMethods();
        $this->assertInstanceOf(Collection::class, $methods);

        $methodsArray = $methods->all();
        $this->assertContains('GET', $methodsArray);
        $this->assertContains('HEAD', $methodsArray);
        $this->assertContains('POST', $methodsArray);
        $this->assertContains('PUT', $methodsArray);
        $this->assertContains('PATCH', $methodsArray);
        $this->assertEquals(5, count($methodsArray));
    }

    public function testGetAllUris(): void
    {
        $route1 = new Route('GET', '/users', function () {
            return 'users';
        });
        $route2 = new Route('POST', '/users', function () {
            return 'create user';
        });
        $route3 = new Route('GET', '/posts', function () {
            return 'posts';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $uris = $this->collection->getAllUris();
        $this->assertInstanceOf(Collection::class, $uris);

        $urisArray = $uris->all();
        $this->assertContains('/users', $urisArray);
        $this->assertContains('/posts', $urisArray);
        $this->assertEquals(2, count($urisArray)); // Unique URIs only
    }

    public function testFilter(): void
    {
        $route1 = new Route('GET', '/users', function () {
            return 'users';
        });
        $route2 = new Route('POST', '/users', function () {
            return 'create user';
        });
        $route3 = new Route('GET', '/posts', function () {
            return 'posts';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $getRoutes = $this->collection->filter(function (Route $route) {
            return in_array('GET', $route->getMethods());
        });

        $this->assertEquals(2, $getRoutes->count());
        $this->assertTrue($getRoutes->contains($route1));
        $this->assertTrue($getRoutes->contains($route3));
    }

    public function testGroupByMethod(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });
        $route3 = new Route(['GET', 'POST'], '/test3', function () {
            return 'test3';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $grouped = $this->collection->groupBy('method');

        $this->assertInstanceOf(Collection::class, $grouped);
        $this->assertTrue($grouped->has('GET'));
        $this->assertTrue($grouped->has('POST'));
        $this->assertTrue($grouped->has('GET|POST'));
    }

    public function testGroupByUri(): void
    {
        $route1 = new Route('GET', '/users', function () {
            return 'get users';
        });
        $route2 = new Route('POST', '/users', function () {
            return 'create user';
        });
        $route3 = new Route('GET', '/posts', function () {
            return 'posts';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $grouped = $this->collection->groupBy('uri');

        $this->assertTrue($grouped->has('/users'));
        $this->assertTrue($grouped->has('/posts'));
        $this->assertEquals(2, $grouped->get('/users')->count());
        $this->assertEquals(1, $grouped->get('/posts')->count());
    }

    public function testGroupByName(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route1->name('test');

        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });
        $route2->name('test');

        $route3 = new Route('GET', '/test3', function () {
            return 'test3';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);
        $this->collection->add($route3);

        $grouped = $this->collection->groupBy('name');

        $this->assertTrue($grouped->has('test'));
        $this->assertTrue($grouped->has('unnamed'));
        $this->assertEquals(2, $grouped->get('test')->count());
        $this->assertEquals(1, $grouped->get('unnamed')->count());
    }

    public function testGroupByInvalidAttribute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid grouping attribute: invalid');

        $this->collection->groupBy('invalid');
    }

    public function testToArray(): void
    {
        $route = new Route('GET', '/test', function () {
            return 'test';
        });
        $route->name('test.route');

        $this->collection->add($route);

        $array = $this->collection->toArray();

        $this->assertIsArray($array);
        $this->assertCount(1, $array);
        $this->assertArrayHasKey('methods', $array[0]);
        $this->assertArrayHasKey('uri', $array[0]);
        $this->assertArrayHasKey('name', $array[0]);
    }

    public function testGetDebugInfo(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route1->name('test1');

        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);

        $debugInfo = $this->collection->getDebugInfo();

        $this->assertArrayHasKey('total_routes', $debugInfo);
        $this->assertArrayHasKey('methods', $debugInfo);
        $this->assertArrayHasKey('named_routes', $debugInfo);
        $this->assertArrayHasKey('routes_by_method', $debugInfo);

        $this->assertEquals(2, $debugInfo['total_routes']);
        $this->assertContains('GET', $debugInfo['methods']);
        $this->assertContains('POST', $debugInfo['methods']);
        $this->assertContains('test1', $debugInfo['named_routes']);
    }

    public function testToString(): void
    {
        $route1 = new Route('GET', '/test1', function () {
            return 'test1';
        });
        $route2 = new Route('POST', '/test2', function () {
            return 'test2';
        });

        $this->collection->add($route1);
        $this->collection->add($route2);

        $string = (string) $this->collection;
        $this->assertEquals('RouteCollection with 2 routes', $string);
    }
}