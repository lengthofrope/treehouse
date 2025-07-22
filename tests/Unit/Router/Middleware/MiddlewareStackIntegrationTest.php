<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareStack;
use LengthOfRope\TreeHouse\Router\Middleware\AuthMiddleware;
use LengthOfRope\TreeHouse\Router\Middleware\JwtMiddleware;
use LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware;
use LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use Tests\TestCase;

/**
 * Test cases for JWT middleware integration with MiddlewareStack
 * 
 * @package Tests\Unit\Router\Middleware
 */
class MiddlewareStackIntegrationTest extends TestCase
{
    private MiddlewareStack $stack;
    private AuthManager $authManager;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stack = new MiddlewareStack();
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

    public function testBuiltInAuthMiddlewareAlias(): void
    {
        // Test that 'auth' alias resolves to AuthMiddleware
        $this->stack->add('auth');

        $reflection = new \ReflectionClass($this->stack);
        $method = $reflection->getMethod('resolveBuiltInMiddleware');
        $method->setAccessible(true);

        $resolved = $method->invoke($this->stack, 'auth');
        $this->assertEquals(AuthMiddleware::class, $resolved);
    }

    public function testBuiltInJwtMiddlewareAlias(): void
    {
        // Test that 'jwt' alias resolves to JwtMiddleware
        $this->stack->add('jwt');

        $reflection = new \ReflectionClass($this->stack);
        $method = $reflection->getMethod('resolveBuiltInMiddleware');
        $method->setAccessible(true);

        $resolved = $method->invoke($this->stack, 'jwt');
        $this->assertEquals(JwtMiddleware::class, $resolved);
    }

    public function testBuiltInRoleMiddlewareAlias(): void
    {
        // Test that 'role' alias resolves to RoleMiddleware
        $this->stack->add('role');

        $reflection = new \ReflectionClass($this->stack);
        $method = $reflection->getMethod('resolveBuiltInMiddleware');
        $method->setAccessible(true);

        $resolved = $method->invoke($this->stack, 'role');
        $this->assertEquals(RoleMiddleware::class, $resolved);
    }

    public function testBuiltInPermissionMiddlewareAlias(): void
    {
        // Test that 'permission' alias resolves to PermissionMiddleware
        $this->stack->add('permission');

        $reflection = new \ReflectionClass($this->stack);
        $method = $reflection->getMethod('resolveBuiltInMiddleware');
        $method->setAccessible(true);

        $resolved = $method->invoke($this->stack, 'permission');
        $this->assertEquals(PermissionMiddleware::class, $resolved);
    }

    public function testMiddlewareStackExecutionOrder(): void
    {
        $executionOrder = [];

        // Mock authenticated user
        $user = $this->createMockUser(['admin'], ['manage-users']);

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->method('setRequest');
        $jwtGuard->method('check')->willReturn(true);
        $jwtGuard->method('user')->willReturn($user);
        $jwtGuard->method('getToken')->willReturn('token123');

        $this->authManager->method('guard')->willReturn($jwtGuard);

        // Add middleware that track execution order
        $this->stack->add(function (Request $request, callable $next) use (&$executionOrder): Response {
            $executionOrder[] = 'custom_before';
            $response = $next($request);
            $executionOrder[] = 'custom_after';
            return $response;
        });

        // Add JWT authentication
        $this->stack->add('jwt:api');

        $destination = function (Request $request) use (&$executionOrder): Response {
            $executionOrder[] = 'destination';
            return new Response('Protected content');
        };

        $response = $this->stack->handle($this->request, $destination);

        $this->assertEquals('Protected content', $response->getContent());
        $this->assertEquals([
            'custom_before',
            'destination', // JWT middleware allows access
            'custom_after'
        ], $executionOrder);
    }

    public function testComplexMiddlewareChain(): void
    {
        // Mock authenticated admin user
        $user = $this->createMockUser(['admin'], ['manage-users']);

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->method('setRequest');
        $jwtGuard->method('check')->willReturn(true);
        $jwtGuard->method('user')->willReturn($user);
        $jwtGuard->method('getToken')->willReturn('token123');

        $this->authManager->method('guard')->willReturn($jwtGuard);

        // Build complex middleware chain: JWT auth -> admin role -> manage-users permission
        $this->stack->add('jwt:api');
        $this->stack->add('role:admin');
        $this->stack->add('permission:manage-users');

        $destination = function (Request $request): Response {
            return new Response('Admin protected content');
        };

        $response = $this->stack->handle($this->request, $destination);

        $this->assertEquals('Admin protected content', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMiddlewareChainFailsAtAuthentication(): void
    {
        // Mock unauthenticated request
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->method('setRequest');
        $jwtGuard->method('check')->willReturn(false);

        $this->authManager->method('guard')->willReturn($jwtGuard);

        // Build middleware chain that should fail at first step
        $this->stack->add('jwt:api');
        $this->stack->add('role:admin');

        $destination = function (Request $request): Response {
            $this->fail('Destination should not be reached');
        };

        $response = $this->stack->handle($this->request, $destination);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMiddlewareChainFailsAtRoleCheck(): void
    {
        // Mock authenticated user without admin role
        $user = $this->createMockUser(['editor'], ['edit-posts']);

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->method('setRequest');
        $jwtGuard->method('check')->willReturn(true);
        $jwtGuard->method('user')->willReturn($user);
        $jwtGuard->method('getToken')->willReturn('token123');

        $this->authManager->method('guard')->willReturn($jwtGuard);

        // Build middleware chain that should fail at role check
        $this->stack->add('jwt:api');
        $this->stack->add('role:admin');

        $destination = function (Request $request): Response {
            $this->fail('Destination should not be reached');
        };

        $response = $this->stack->handle($this->request, $destination);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testMiddlewareParameterParsing(): void
    {
        // Mock authenticated admin user
        $user = $this->createMockUser(['admin'], ['manage-users']);

        // Mock JWT guard
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->method('setRequest');
        $jwtGuard->method('check')->willReturn(true);
        $jwtGuard->method('user')->willReturn($user);
        $jwtGuard->method('getToken')->willReturn('token123');

        $this->authManager->method('guard')->willReturn($jwtGuard);

        // Test middleware with parameters
        $this->stack->add('jwt:api,mobile');
        $this->stack->add('role:admin,editor');
        $this->stack->add('permission:manage-users,edit-posts');

        $destination = function (Request $request): Response {
            return new Response('Multi-parameter content');
        };

        $response = $this->stack->handle($this->request, $destination);

        $this->assertEquals('Multi-parameter content', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCustomMiddlewareAlias(): void
    {
        // Register custom alias
        $this->stack->alias('custom-auth', AuthMiddleware::class);

        // Test custom alias resolution
        $this->stack->add('custom-auth:api');

        $aliases = $this->stack->getAliases();
        $this->assertEquals(AuthMiddleware::class, $aliases['custom-auth']);
    }

    public function testToArrayWithJwtMiddleware(): void
    {
        // Mock authenticated user for successful execution
        $user = $this->createMockUser(['admin'], ['manage-users']);
        $jwtGuard = $this->createMock(JwtGuard::class);
        $jwtGuard->method('setRequest');
        $jwtGuard->method('check')->willReturn(true);
        $jwtGuard->method('user')->willReturn($user);
        $jwtGuard->method('getToken')->willReturn('token123');
        $this->authManager->method('guard')->willReturn($jwtGuard);

        $this->stack->add(['jwt:api', 'role:admin', 'permission:manage-users']);

        $array = $this->stack->toArray();
        $this->assertEquals(['jwt:api', 'role:admin', 'permission:manage-users'], $array);
    }

    public function testDebugString(): void
    {
        $this->stack->add(['jwt:api', 'role:admin', 'throttle:60,1']);

        $string = (string) $this->stack;
        $this->assertStringContainsString('MiddlewareStack with 3 middleware', $string);
        $this->assertStringContainsString('jwt:api', $string);
        $this->assertStringContainsString('role:admin', $string);
        $this->assertStringContainsString('throttle:60,1', $string);
    }

    private function createMockUser(array $roles = [], array $permissions = []): object
    {
        return new class($roles, $permissions) implements Authorizable {
            public function __construct(private array $roles, private array $permissions) {}
            
            public function getAuthIdentifier(): mixed { return 1; }
            public function getAuthPassword(): string { return 'password'; }
            public function hasRole(string $role): bool { 
                return in_array($role, $this->roles); 
            }
            public function hasPermission(string $permission): bool { 
                return in_array($permission, $this->permissions); 
            }
            public function getRoles(): array { return $this->roles; }
            public function getPermissions(): array { return $this->permissions; }
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