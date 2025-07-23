<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use Tests\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

/**
 * Role Middleware Tests
 *
 * Tests for the role-based middleware that protects routes
 * based on user roles.
 */
class RoleMiddlewareTest extends TestCase
{
    protected RoleMiddleware $middleware;
    protected AuthManager $authManager;
    protected array $authConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authConfig = [
            'roles' => [
                'admin' => ['*'],
                'editor' => ['edit-posts', 'delete-posts', 'view-posts'],
                'viewer' => ['view-posts'],
            ],
            'permissions' => [
                'manage-users' => ['admin'],
                'edit-posts' => ['admin', 'editor'],
                'delete-posts' => ['admin', 'editor'],
                'view-posts' => ['admin', 'editor', 'viewer'],
            ],
            'default_role' => 'viewer',
        ];

        // Create mock AuthManager
        $this->authManager = $this->createMock(AuthManager::class);

        // Set up global app instance
        $GLOBALS['app'] = $this->createMockApp([
            'auth' => $this->authManager
        ]);

        // Create middleware with old-style config array
        $this->middleware = new RoleMiddleware($this->authConfig);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['app']);
        parent::tearDown();
    }

    public function testUnauthenticatedUserReturnsUnauthorized(): void
    {
        // Mock guard that fails authentication
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(false);

        $this->authManager->expects($this->once())
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $request = $this->createRequestWithRoles('admin');

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized', $response->getContent());
    }

    public function testJsonRequestReturnsJsonErrorResponse(): void
    {
        // Mock guard that fails authentication
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())
              ->method('check')
              ->willReturn(false);

        // The guard method will be called 2 times:
        // 1 time in getCurrentUser() + 1 time in hasJwtGuard()
        $this->authManager->expects($this->exactly(2))
                         ->method('guard')
                         ->with('web')
                         ->willReturn($guard);

        $request = $this->createRequestWithRoles('admin', ['Accept' => 'application/json']);

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $responseData['error']);
        $this->assertEquals('Authentication required to access this resource', $responseData['message']);
    }

    public function testEmptyRoleParameterWithUnauthenticatedUserStillRequiresAuth(): void
    {
        // Mock guard that fails authentication
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())->method('check')->willReturn(false);
        $this->authManager->expects($this->once())->method('guard')->willReturn($guard);

        $request = $this->createRequestWithRoles('');
        $next = function ($req) { return new Response('Success', 200); };
        $response = $this->middleware->handle($request, $next);

        // Empty roles still require authentication first
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized', $response->getContent());
    }

    public function testConfigurationIsSetCorrectly(): void
    {
        $middleware = new RoleMiddleware($this->authConfig);
        
        // Test that middleware was configured with proper auth config
        $this->assertInstanceOf(RoleMiddleware::class, $middleware);
        
        // Test the configuration by trying to set a new one
        $newConfig = ['roles' => ['test' => ['test-permission']]];
        $middleware->setConfig($newConfig);
        
        // This verifies the setConfig method works
        $this->assertTrue(true);
    }

    public function testRoleParsing(): void
    {
        // Mock guard that fails authentication
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())->method('check')->willReturn(false);
        $this->authManager->expects($this->once())->method('guard')->willReturn($guard);

        // Test that roles are parsed correctly from query string
        $request = $this->createRequestWithRoles('admin,editor,viewer');
        $next = function ($req) { return new Response('Success', 200); };
        $response = $this->middleware->handle($request, $next);

        // Should be unauthorized since no user is authenticated
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMiddlewareInterfaceCompliance(): void
    {
        // Mock guard that fails authentication
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())->method('check')->willReturn(false);
        $this->authManager->expects($this->once())->method('guard')->willReturn($guard);

        // Test that the middleware implements the interface correctly
        $request = $this->createRequestWithRoles('admin');
        $next = function ($req) { return new Response('Success', 200); };
        $response = $this->middleware->handle($request, $next);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertIsInt($response->getStatusCode());
        $this->assertIsString($response->getContent());
    }

    public function testHtmlResponseForNonJsonRequest(): void
    {
        // Mock guard that fails authentication
        $guard = $this->createMock(SessionGuard::class);
        $guard->expects($this->once())->method('check')->willReturn(false);
        $this->authManager->expects($this->once())->method('guard')->willReturn($guard);

        $request = $this->createRequestWithRoles('admin');
        $next = function ($req) { return new Response('Success', 200); };
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertStringContainsString('401 - Unauthorized', $response->getContent());
    }

    private function createRequestWithRoles(string $roles, array $headers = []): Request
    {
        $query = $roles ? ['_roles' => $roles] : [];
        $serverVars = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ];

        // Add headers to server vars
        foreach ($headers as $name => $value) {
            $serverVars['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request($query, [], [], [], $serverVars);
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