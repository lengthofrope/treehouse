<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\PermissionChecker;
use Tests\TestCase;

/**
 * Enhanced test cases for the RoleMiddleware class with JWT support
 * 
 * @package Tests\Unit\Router\Middleware
 */
class EnhancedRoleMiddlewareTest extends TestCase
{
    private RoleMiddleware $middleware;
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
            'REQUEST_URI' => '/admin'
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['app']);
        parent::tearDown();
    }

    public function testConstructorWithRoleParameters(): void
    {
        $middleware = new RoleMiddleware('admin', 'editor');
        $this->assertEquals(['web'], $middleware->getGuards());
    }

    public function testConstructorWithGuardSpecification(): void
    {
        $middleware = new RoleMiddleware('admin', 'auth:api');
        $this->assertEquals(['api'], $middleware->getGuards());
    }

    public function testConstructorWithRoleAndGuard(): void
    {
        $middleware = new RoleMiddleware('admin:api');
        $this->assertEquals(['api'], $middleware->getGuards());
    }

    public function testSuccessfulRoleCheckWithSessionGuard(): void
    {
        $this->middleware = new RoleMiddleware('admin');

        // Mock user with role
        $user = $this->createMockUser(['admin']);

        // Mock session guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);
        $guard->expects($this->once())
              ->method('user')
              ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Admin content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Admin content', $response->getContent());
    }

    public function testSuccessfulRoleCheckWithJwtGuard(): void
    {
        $this->middleware = new RoleMiddleware('admin', 'auth:api');

        // Mock user with role
        $user = $this->createMockUser(['admin']);

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $jwtGuard->expects($this->once())
                ->method('user')
                ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Admin API content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Admin API content', $response->getContent());
    }

    public function testMultipleRolesOrLogic(): void
    {
        $this->middleware = new RoleMiddleware('admin', 'editor');

        // Mock user with editor role (should pass)
        $user = $this->createMockUser(['editor']);

        // Mock guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);
        $guard->expects($this->once())
              ->method('user')
              ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Editor content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Editor content', $response->getContent());
    }

    public function testFailedRoleCheckReturns403(): void
    {
        $this->middleware = new RoleMiddleware('admin');

        // Mock user without admin role
        $user = $this->createMockUser(['editor']);

        // Mock guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);
        $guard->expects($this->once())
              ->method('user')
              ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $next = function (Request $request): Response {
            $this->fail('Next should not be called when role check fails');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUnauthenticatedUserReturns401(): void
    {
        $this->middleware = new RoleMiddleware('admin');

        // Mock guard that fails authentication
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
    }

    public function testMultipleGuardsAuthentication(): void
    {
        $this->middleware = new RoleMiddleware('admin', 'auth:web,api');

        // Mock user with role
        $user = $this->createMockUser(['admin']);

        // Mock web guard (fails)
        $webGuard = $this->createMock(SessionGuard::class);
        $webGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        // Mock API guard (succeeds)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $apiGuard->expects($this->once())
                ->method('user')
                ->willReturn($user);

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['web', $webGuard],
                             ['api', $apiGuard]
                         ]);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Admin content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Admin content', $response->getContent());
    }

    public function testForbiddenJsonResponse(): void
    {
        $this->middleware = new RoleMiddleware('admin');

        // Create JSON request
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/admin',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        // Mock user without admin role
        $user = $this->createMockUser(['editor']);

        // Mock guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);
        $guard->expects($this->once())
              ->method('user')
              ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $content['error']);
        $this->assertEquals('Insufficient role privileges to access this resource', $content['message']);
        $this->assertEquals(['admin'], $content['required_roles']);
        $this->assertEquals(['web'], $content['guards_used']);
        $this->assertEquals('INSUFFICIENT_ROLE', $content['code']);
    }

    public function testUnauthenticatedWithJwtGuardAddsWwwAuthenticateHeader(): void
    {
        $this->middleware = new RoleMiddleware('admin', 'auth:api');

        // Create JSON request
        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/admin',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        // Mock JWT guard (fails)
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->expects($this->once())
                ->method('setRequest')
                ->with($request);
        $jwtGuard->expects($this->once())
                ->method('check')
                ->willReturn(false);

        // The guard method will be called 2 times:
        // 1 time in getCurrentUser() + 1 time in hasJwtGuard()
        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->with('api')
                         ->willReturn($jwtGuard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Bearer realm="API"', $response->getHeader('WWW-Authenticate'));
    }

    public function testNoRolesSpecifiedAllowsAccess(): void
    {
        $this->middleware = new RoleMiddleware(); // No roles specified

        // Mock authenticated user
        $user = $this->createMockUser(['editor']);

        // Mock guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);
        $guard->expects($this->once())
              ->method('user')
              ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Content', $response->getContent());
    }

    public function testSetGuardsMethod(): void
    {
        $middleware = new RoleMiddleware('admin');
        $this->assertEquals(['web'], $middleware->getGuards());

        $middleware->setGuards(['api', 'mobile']);
        $this->assertEquals(['api', 'mobile'], $middleware->getGuards());
    }

    public function testGuardExceptionHandling(): void
    {
        $this->middleware = new RoleMiddleware('admin', 'auth:web,api');

        // Mock user with role
        $user = $this->createMockUser(['admin']);

        // Mock web guard (throws exception)
        $webGuard = $this->createMock(SessionGuard::class);
        $webGuard->expects($this->once())
                ->method('check')
                ->willThrowException(new \Exception('Guard error'));

        // Mock API guard (succeeds)
        $apiGuard = $this->createMock(JwtGuard::class);
        $apiGuard->expects($this->once())
                ->method('setRequest')
                ->with($this->request);
        $apiGuard->expects($this->once())
                ->method('check')
                ->willReturn(true);
        $apiGuard->expects($this->once())
                ->method('user')
                ->willReturn($user);

        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->willReturnMap([
                             ['web', $webGuard],
                             ['api', $apiGuard]
                         ]);

        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;
            return new Response('Admin content');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals('Admin content', $response->getContent());
    }

    public function testForbiddenHtmlResponse(): void
    {
        $this->middleware = new RoleMiddleware('admin');

        // Mock user without admin role
        $user = $this->createMockUser(['editor']);

        // Mock guard
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(true);
        $guard->expects($this->once())
              ->method('user')
              ->willReturn($user);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $next = function (Request $request): Response {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('403 - Forbidden', $response->getContent());
        $this->assertStringContainsString('Required role(s):', $response->getContent());
        $this->assertStringContainsString('admin', $response->getContent());
        $this->assertStringContainsString('Authentication guards: web', $response->getContent());
    }

    private function createMockUser(array $roles = []): object
    {
        return new class($roles) implements Authorizable {
            public function __construct(private array $roles) {}
            
            public function getAuthIdentifier(): mixed { return 1; }
            public function hasRole(string $role): bool {
                return in_array($role, $this->roles);
            }
            public function hasAnyRole(array $roles): bool {
                return !empty(array_intersect($roles, $this->roles));
            }
            public function can(string $permission): bool { return true; }
            public function cannot(string $permission): bool { return false; }
            public function assignRole(string $role): void { }
            public function removeRole(string $role): void { }
            public function getRole(): string|array { return $this->roles; }
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