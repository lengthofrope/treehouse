<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\AuthMiddleware;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use Tests\TestCase;

/**
 * Test cases for the AuthMiddleware class
 * 
 * @package Tests\Unit\Router\Middleware
 */
class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;
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
            'REQUEST_URI' => '/protected'
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['app']);
        parent::tearDown();
    }

    public function testConstructorWithNoGuards(): void
    {
        $middleware = new AuthMiddleware();
        $this->assertEquals([null], $middleware->getGuards());
    }

    public function testConstructorWithStringGuard(): void
    {
        $middleware = new AuthMiddleware('api');
        $this->assertEquals(['api'], $middleware->getGuards());
    }

    public function testConstructorWithMultipleGuardsString(): void
    {
        $middleware = new AuthMiddleware('api,web');
        $this->assertEquals(['api', 'web'], $middleware->getGuards());
    }

    public function testConstructorWithArrayGuards(): void
    {
        $middleware = new AuthMiddleware(['api', 'web']);
        $this->assertEquals(['api', 'web'], $middleware->getGuards());
    }

    public function testSuccessfulAuthenticationWithDefaultGuard(): void
    {
        $this->middleware = new AuthMiddleware();

        // Mock user
        $user = $this->createMockUser();

        // Mock guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with(null)
                         ->willReturn($guard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Protected content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testSuccessfulAuthenticationWithJwtGuard(): void
    {
        $this->middleware = new AuthMiddleware('api');

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Protected content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testMultipleGuardsFirstSucceeds(): void
    {
        $this->middleware = new AuthMiddleware(['web', 'api']);

        // Mock web guard (succeeds)
        $webGuard = $this->createMock(SessionGuard::class);
        $webGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);

        // Mock API guard (should not be called)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->never())
                ->method('check');

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($webGuard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Protected content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Protected content', $response->getContent());
    }

    public function testMultipleGuardsSecondSucceeds(): void
    {
        $this->middleware = new AuthMiddleware(['web', 'api']);

        // Mock web guard (fails)
        $webGuard = $this->createMock(SessionGuard::class);
        $webGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        // Mock API guard (succeeds)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['web', $webGuard],
                             ['api', $apiGuard]
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

    public function testAuthenticationFailsAllGuards(): void
    {
        $this->middleware = new AuthMiddleware(['web', 'api']);

        // Mock web guard (fails)
        $webGuard = $this->createMock(SessionGuard::class);
        $webGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        // Mock API guard (fails)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['web', $webGuard],
                             ['api', $apiGuard]
                         ]);

        $next = function (Request $request): Response {
            $this->fail('Next should not be called when authentication fails');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUnauthenticatedJsonRequest(): void
    {
        $this->middleware = new AuthMiddleware();

        // Create JSON request
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/protected',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        // Mock guard (fails)
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with(null)
                         ->willReturn($guard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthenticated', $content['error']);
        $this->assertEquals('Authentication required to access this resource', $content['message']);
        $this->assertEquals([null], $content['guards']);
    }

    public function testUnauthenticatedHtmlRequest(): void
    {
        $this->middleware = new AuthMiddleware('web');

        // Mock guard (fails)
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('401', $response->getContent());
        $this->assertStringContainsString('Authentication Required', $response->getContent());
    }

    public function testUnauthenticatedWithJwtGuardAddsWwwAuthenticateHeader(): void
    {
        $this->middleware = new AuthMiddleware('api');

        // Create JSON request
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/protected',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        // Mock JWT guard (fails)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($request);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Bearer', $response->getHeader('WWW-Authenticate'));
    }

    public function testGuardExceptionHandling(): void
    {
        $this->middleware = new AuthMiddleware(['web', 'api']);

        // Mock web guard (throws exception)
        $webGuard = $this->createMock(SessionGuard::class);
        $webGuard->expects($this->once())
                ->method('check')
                ->willThrowException(new \Exception('Guard error'));

        // Mock API guard (succeeds)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['web', $webGuard],
                             ['api', $apiGuard]
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

    public function testUsesGuardMethod(): void
    {
        $middleware = new AuthMiddleware(['web', 'api']);
        
        $this->assertTrue($middleware->usesGuard('web'));
        $this->assertTrue($middleware->usesGuard('api'));
        $this->assertFalse($middleware->usesGuard('mobile'));
    }

    public function testConstructorWithoutApplication(): void
    {
        unset($GLOBALS['app']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application instance not available');

        new AuthMiddleware();
    }

    private function createMockUser(): object
    {
        return new class implements Authorizable {
            public function getAuthIdentifier(): mixed { return 1; }
            public function getAuthPassword(): string { return 'password'; }
            public function hasRole(string $role): bool { return true; }
            public function hasPermission(string $permission): bool { return true; }
            public function getRoles(): array { return ['user']; }
            public function getPermissions(): array { return ['read']; }
        };
    }

    private function createMockApp(array $services = []): object
    {
        return new class($services) {
            public function __construct(private array $services) {}
            
            public function make(string $abstract): mixed
            {
                return $this->services[$abstract] ?? null;
            }
        };
    }
}