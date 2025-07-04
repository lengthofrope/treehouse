<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Foundation\Container;
use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Router\RouteNotFoundException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\View\ViewFactory;
use Tests\TestCase;

/**
 * Foundation Application Tests
 *
 * @package Tests\Unit\Foundation
 */
class ApplicationTest extends TestCase
{
    private Application $app;
    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testBasePath = sys_get_temp_dir() . '/treehouse_test_' . uniqid();
        if (!is_dir($this->testBasePath)) {
            mkdir($this->testBasePath, 0755, true);
        }
        
        $this->app = new Application($this->testBasePath);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testBasePath)) {
            $this->removeDirectory($this->testBasePath);
        }
        
        parent::tearDown();
    }

    public function testApplicationConstructor()
    {
        $app = new Application('/test/path');
        $this->assertEquals('/test/path', $app->getBasePath());
        
        $this->assertInstanceOf(Router::class, $app->getRouter());
        $this->assertInstanceOf(Container::class, $app->getContainer());
    }

    public function testApplicationConstructorWithEmptyPath()
    {
        $app = new Application();
        $this->assertNotEmpty($app->getBasePath());
    }

    public function testCoreServicesRegistration()
    {
        $container = $this->app->getContainer();
        
        // Test router service
        $this->assertTrue($container->bound('router'));
        $this->assertInstanceOf(Router::class, $container->make('router'));
        
        // Test cache service
        $this->assertTrue($container->bound('cache'));
        $this->assertInstanceOf(CacheManager::class, $container->make('cache'));
        
        // Test view service
        $this->assertTrue($container->bound('view'));
        $this->assertInstanceOf(ViewFactory::class, $container->make('view'));
    }

    public function testSingletonServices()
    {
        $container = $this->app->getContainer();
        
        // Test that services are singletons
        $router1 = $container->make('router');
        $router2 = $container->make('router');
        $this->assertSame($router1, $router2);
        
        $cache1 = $container->make('cache');
        $cache2 = $container->make('cache');
        $this->assertSame($cache1, $cache2);
        
        $view1 = $container->make('view');
        $view2 = $container->make('view');
        $this->assertSame($view1, $view2);
    }

    public function testLoadConfiguration()
    {
        // Create test config directory and files
        $configDir = $this->testBasePath . '/config';
        mkdir($configDir, 0755, true);
        
        // Create app config
        file_put_contents($configDir . '/app.php', '<?php return ["debug" => true, "name" => "Test App"];');
        
        // Create database config
        file_put_contents($configDir . '/database.php', '<?php return ["default" => "mysql"];');
        
        $this->app->loadConfiguration($configDir);
        
        $this->assertTrue($this->app->config('app.debug'));
        $this->assertEquals('Test App', $this->app->config('app.name'));
        $this->assertEquals('mysql', $this->app->config('database.default'));
    }

    public function testLoadConfigurationWithNonExistentDirectory()
    {
        // Should not throw exception
        $this->app->loadConfiguration('/non/existent/path');
        $this->assertEmpty($this->app->getAllConfig());
    }

    public function testLoadConfigurationWithInvalidFile()
    {
        $configDir = $this->testBasePath . '/config';
        mkdir($configDir, 0755, true);
        
        // Create invalid config file (returns string instead of array)
        file_put_contents($configDir . '/invalid.php', '<?php return "not an array";');
        
        $this->app->loadConfiguration($configDir);
        
        // Should not include invalid config
        $this->assertNull($this->app->config('invalid'));
    }

    public function testLoadRoutes()
    {
        $routesFile = $this->testBasePath . '/routes.php';
        file_put_contents($routesFile, '<?php $router->get("/test", function() { return "test"; });');
        
        $this->app->loadRoutes($routesFile);
        
        // Routes should be loaded into the router
        $router = $this->app->getRouter();
        $this->assertInstanceOf(Router::class, $router);
    }

    public function testLoadRoutesWithNonExistentFile()
    {
        // Should not throw exception
        $this->app->loadRoutes('/non/existent/routes.php');
        $this->assertInstanceOf(Router::class, $this->app->getRouter());
    }

    public function testHandleRequest()
    {
        // Create a mock router that returns a response
        $mockRouter = $this->createMock(Router::class);
        $expectedResponse = new Response('Test Response');
        $mockRouter->expects($this->once())
                   ->method('dispatch')
                   ->willReturn($expectedResponse);
        
        // Replace the router instance directly since the app uses its own router, not the container's
        $reflection = new \ReflectionClass($this->app);
        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $routerProperty->setValue($this->app, $mockRouter);
        
        $request = new Request([], [], [], [], [], null);
        $response = $this->app->handle($request);
        
        $this->assertEquals($expectedResponse->getContent(), $response->getContent());
        $this->assertEquals($expectedResponse->getStatusCode(), $response->getStatusCode());
    }

    public function testHandleRouteNotFoundException()
    {
        // Disable debug mode for this test
        $this->app->setConfig('app.debug', false);
        
        // Create a mock router that throws RouteNotFoundException
        $mockRouter = $this->createMock(Router::class);
        $mockRouter->expects($this->once())
                   ->method('dispatch')
                   ->willThrowException(new RouteNotFoundException('Route not found'));
        
        // Replace the router instance directly
        $reflection = new \ReflectionClass($this->app);
        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $routerProperty->setValue($this->app, $mockRouter);
        
        $request = new Request([], [], [], [], [], null);
        $response = $this->app->handle($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Page Not Found', $response->getContent());
    }

    public function testHandleRouteNotFoundExceptionWithDebugMode()
    {
        // Enable debug mode
        $this->app->setConfig('app.debug', true);
        
        $mockRouter = $this->createMock(Router::class);
        $exception = new RouteNotFoundException('Route not found');
        $mockRouter->expects($this->once())
                   ->method('dispatch')
                   ->willThrowException($exception);
        
        // Replace the router instance directly
        $reflection = new \ReflectionClass($this->app);
        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $routerProperty->setValue($this->app, $mockRouter);
        
        $request = new Request([], [], [], [], [], null);
        $response = $this->app->handle($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Route not found', $response->getContent());
    }

    public function testHandleGenericException()
    {
        // Disable debug mode for this test
        $this->app->setConfig('app.debug', false);
        
        $mockRouter = $this->createMock(Router::class);
        $mockRouter->expects($this->once())
                   ->method('dispatch')
                   ->willThrowException(new \Exception('Generic error'));
        
        // Replace the router instance directly
        $reflection = new \ReflectionClass($this->app);
        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $routerProperty->setValue($this->app, $mockRouter);
        
        $request = new Request([], [], [], [], [], null);
        $response = $this->app->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Something went wrong', $response->getContent());
    }

    public function testHandleExceptionWithStatusCode()
    {
        // Disable debug mode for this test
        $this->app->setConfig('app.debug', false);
        
        $mockRouter = $this->createMock(Router::class);
        $exception = new class extends \Exception {
            public function getStatusCode(): int
            {
                return 403;
            }
        };
        $mockRouter->expects($this->once())
                   ->method('dispatch')
                   ->willThrowException($exception);
        
        // Replace the router instance directly
        $reflection = new \ReflectionClass($this->app);
        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $routerProperty->setValue($this->app, $mockRouter);
        
        $request = new Request([], [], [], [], [], null);
        $response = $this->app->handle($request);
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Something went wrong', $response->getContent());
    }

    public function testConfigWithDotNotation()
    {
        $this->app->setConfig('database.connections.mysql.host', 'localhost');
        $this->app->setConfig('database.connections.mysql.port', 3306);
        
        $this->assertEquals('localhost', $this->app->config('database.connections.mysql.host'));
        $this->assertEquals(3306, $this->app->config('database.connections.mysql.port'));
        $this->assertEquals('default', $this->app->config('database.connections.mysql.username', 'default'));
    }

    public function testConfigWithNonExistentKey()
    {
        $this->assertNull($this->app->config('non.existent.key'));
        $this->assertEquals('default', $this->app->config('non.existent.key', 'default'));
    }

    public function testSetConfigCreatesNestedStructure()
    {
        $this->app->setConfig('level1.level2.level3', 'value');
        
        $config = $this->app->getAllConfig();
        $this->assertEquals('value', $config['level1']['level2']['level3']);
    }

    public function testSetConfigOverwritesExistingValues()
    {
        $this->app->setConfig('test.key', 'original');
        $this->app->setConfig('test.key', 'updated');
        
        $this->assertEquals('updated', $this->app->config('test.key'));
    }

    public function testMakeService()
    {
        $this->app->bind('test-service', function() {
            return new \stdClass();
        });
        
        $service = $this->app->make('test-service');
        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testBindService()
    {
        $this->app->bind('test-binding', \stdClass::class);
        $service = $this->app->make('test-binding');
        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testSingletonService()
    {
        $this->app->singleton('test-singleton', function() {
            return new \stdClass();
        });
        
        $service1 = $this->app->make('test-singleton');
        $service2 = $this->app->make('test-singleton');
        
        $this->assertSame($service1, $service2);
    }

    public function testGetAllConfig()
    {
        $this->app->setConfig('app.name', 'Test');
        $this->app->setConfig('database.host', 'localhost');
        
        $config = $this->app->getAllConfig();
        
        $this->assertIsArray($config);
        $this->assertEquals('Test', $config['app']['name']);
        $this->assertEquals('localhost', $config['database']['host']);
    }

    public function testDebugModeFromEnvironment()
    {
        // Temporarily set environment variable
        $_ENV['APP_DEBUG'] = 'true';
        
        $app = new Application();
        
        // Create a mock router that throws an exception
        $mockRouter = $this->createMock(Router::class);
        $exception = new \Exception('Test exception');
        $mockRouter->expects($this->once())
                   ->method('dispatch')
                   ->willThrowException($exception);
        
        // Replace the router instance directly
        $reflection = new \ReflectionClass($app);
        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $routerProperty->setValue($app, $mockRouter);
        
        $request = new Request([], [], [], [], [], null);
        $response = $app->handle($request);
        
        $this->assertStringContainsString('Debug', $response->getContent());
        
        // Clean up
        unset($_ENV['APP_DEBUG']);
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}