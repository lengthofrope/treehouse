<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\JwtMiddleware;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use Tests\TestCase;

/**
 * Test cases for the JwtMiddleware class
 * 
 * @package Tests\Unit\Router\Middleware
 */
class JwtMiddlewareTest extends TestCase
{
    private JwtMiddleware $middleware;
    private AuthManager $authManager;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock AuthManager
        $this->authManager = $this->createMock(AuthManager::class);

        // Set up global app instance
        $GLOBALS['app'] = $this->createMockApp([
            'auth' => $this->authManager
        ]);

        $this->request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/protected',
            'HTTP_AUTHORIZATION' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['app']);
        parent::tearDown();
    }

    public function testConstructorWithNoGuards(): void
    {
        $middleware = new JwtMiddleware();
        $this->assertEquals(['api', 'mobile'], $middleware->getGuards());
    }

    public function testConstructorWithStringGuard(): void
    {
        $middleware = new JwtMiddleware('api');
        $this->assertEquals(['api'], $middleware->getGuards());
    }

    public function testConstructorWithMultipleGuardsString(): void
    {
        $middleware = new JwtMiddleware('api,mobile');
        $this->assertEquals(['api', 'mobile'], $middleware->getGuards());
    }

    public function testConstructorWithArrayGuards(): void
    {
        $middleware = new JwtMiddleware(['api', 'mobile']);
        $this->assertEquals(['api', 'mobile'], $middleware->getGuards());
    }

    public function testSuccessfulJwtAuthentication(): void
    {
        $this->middleware = new JwtMiddleware('api');

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $jwtGuard->expects($this->once())
                ->method('getToken')
                ->willReturn('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...');

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            $response = new Response('Protected API content');
            return $response;
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected API content', $response->getContent());
        $this->assertEquals(get_class($jwtGuard), $response->getHeader('X-JWT-Guard'));
    }

    public function testMultipleJwtGuardsFirstSucceeds(): void
    {
        $this->middleware = new JwtMiddleware(['api', 'mobile']);

        // Mock API JWT guard (succeeds)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $apiGuard->expects($this->once())
                ->method('getToken')
                ->willReturn('token123');

        // Mobile guard should not be called
        $mobileGuard = $this->createMock(JwtGuard::class);
        $mobileGuard->expects($this->never())
                   ->method('check');

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($apiGuard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Protected content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testNonJwtGuardIsSkipped(): void
    {
        $this->middleware = new JwtMiddleware(['web', 'api']);

        // Mock session guard (not JWT, should be skipped)
        $sessionGuard = $this->createMock(SessionGuard::class);
        $sessionGuard->expects($this->never())
                    ->method('check');

        // Mock JWT guard (succeeds)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $jwtGuard->expects($this->once())
                ->method('getToken')
                ->willReturn('token123');

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['web', $sessionGuard],
                             ['api', $jwtGuard]
                         ]);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Protected content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testAuthenticationFailsAllJwtGuards(): void
    {
        $this->middleware = new JwtMiddleware(['api', 'mobile']);

        // Mock API JWT guard (fails)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        // Mock mobile JWT guard (fails)
        $mobileGuard = $this->createMock(JwtGuard::class);
        $mobileGuard->expects($this->once())
                   ->method('setRequest')
                   ->with($this->request);
        $mobileGuard->expects($this->once())
                   ->method('check')
                   ->willReturn(false);

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['api', $apiGuard],
                             ['mobile', $mobileGuard]
                         ]);

        $next = function (Request $request): Response {
            $this->fail('Next should not be called when authentication fails');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('Bearer realm="API"', $response->getHeader('WWW-Authenticate'));
    }

    public function testUnauthenticatedReturnsJsonWithDebugInfo(): void
    {
        $this->middleware = new JwtMiddleware(['api']);

        // Mock app with debug mode enabled
        $GLOBALS['app'] = $this->createMockApp([
            'auth' => $this->authManager
        ], ['app.debug' => true, 'auth.guards' => ['api' => ['driver' => 'jwt']]]);

        // Mock JWT guard (fails)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals('Bearer realm="API"', $response->getHeader('WWW-Authenticate'));

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('JWT Authentication Required', $content['error']);
        $this->assertEquals('Valid JWT token required to access this resource', $content['message']);
        $this->assertEquals('JWT_AUTH_REQUIRED', $content['code']);
        $this->assertEquals(['api'], $content['guards_tried']);
        $this->assertArrayHasKey('debug', $content);
        $this->assertEquals(['api'], $content['debug']['available_jwt_guards']);
    }

    public function testUnauthenticatedWithoutDebugInfo(): void
    {
        $this->middleware = new JwtMiddleware(['api']);

        // Mock app with debug mode disabled
        $GLOBALS['app'] = $this->createMockApp([
            'auth' => $this->authManager
        ], ['app.debug' => false]);

        // Mock JWT guard (fails)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $content = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('debug', $content);
    }

    public function testAddsCorsHeaders(): void
    {
        $this->middleware = new JwtMiddleware(['api']);

        // Mock JWT guard (fails)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, PUT, DELETE, OPTIONS', $response->getHeader('Access-Control-Allow-Methods'));
        $this->assertEquals('Authorization, Content-Type, X-Requested-With', $response->getHeader('Access-Control-Allow-Headers'));
        $this->assertEquals('X-JWT-Guard, X-JWT-Expires', $response->getHeader('Access-Control-Expose-Headers'));
    }

    public function testOptionsRequestAddsCorsMaxAge(): void
    {
        $this->middleware = new JwtMiddleware(['api']);

        // Create OPTIONS request
        $optionsRequest = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'OPTIONS',
            'REQUEST_URI' => '/api/protected'
        ]);

        // Mock JWT guard (fails)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($optionsRequest);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($optionsRequest, $next);

        $this->assertEquals('86400', $response->getHeader('Access-Control-Max-Age'));
    }

    public function testJwtGuardExceptionHandling(): void
    {
        $this->middleware = new JwtMiddleware(['api', 'mobile']);

        // Mock API JWT guard (throws exception)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willThrowException(new \Exception('JWT validation error'));

        // Mock mobile JWT guard (succeeds)
        $mobileGuard = $this->createMock(JwtGuard::class);
        $mobileGuard->expects($this->once())
                   ->method('setRequest')
                   ->with($this->request);
        $mobileGuard->expects($this->once())
                   ->method('check')
                   ->willReturn(true);
        $mobileGuard->expects($this->once())
                   ->method('getToken')
                   ->willReturn('token123');

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['api', $apiGuard],
                             ['mobile', $mobileGuard]
                         ]);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Protected content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testGetJwtGuardMethod(): void
    {
        $middleware = new JwtMiddleware(['api']);

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $result = $middleware->getJwtGuard('api');
        $this->assertSame($jwtGuard, $result);
    }

    public function testGetJwtGuardWithNonJwtGuard(): void
    {
        $middleware = new JwtMiddleware(['api']);

        // Mock session guard (not JWT)
        $sessionGuard = $this->createMock(SessionGuard::class);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($sessionGuard);

        $result = $middleware->getJwtGuard('api');
        $this->assertNull($result);
    }

    public function testUsesGuardMethod(): void
    {
        $middleware = new JwtMiddleware(['api', 'mobile']);
        
        $this->assertTrue($middleware->usesGuard('api'));
        $this->assertTrue($middleware->usesGuard('mobile'));
        $this->assertFalse($middleware->usesGuard('web'));
    }

    public function testConstructorWithoutApplication(): void
    {
        unset($GLOBALS['app']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application instance not available');

        new JwtMiddleware();
    }

    private function createMockApp(array $services = [], array $config = []): object
    {
        return new class($services, $config) {
            public function __construct(private array $services, private array $config) {}
            
            public function make(string $abstract): mixed
            {
                return $this->services[$abstract] ?? null;
            }

            public function config(string $key, mixed $default = null): mixed
            {
                $keys = explode('.', $key);
                $value = $this->config;

                foreach ($keys as $segment) {
                    if (!is_array($value) || !array_key_exists($segment, $value)) {
                        return $default;
                    }
                    $value = $value[$segment];
                }

                return $value;
            }
        };
    }
}