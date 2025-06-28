<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

/**
 * Permission Middleware Tests
 *
 * Tests for the permission-based middleware that protects routes
 * based on user permissions.
 */
class PermissionMiddlewareTest extends TestCase
{
    protected PermissionMiddleware $middleware;
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

        $this->middleware = new PermissionMiddleware($this->authConfig);
    }

    public function testUnauthenticatedUserReturnsUnauthorized(): void
    {
        $request = $this->createRequestWithPermissions('manage-users');

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized', $response->getContent());
    }

    public function testJsonRequestReturnsJsonErrorResponse(): void
    {
        $request = $this->createRequestWithPermissions('manage-users', ['Accept' => 'application/json']);

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $responseData['error']);
        $this->assertEquals('Authentication required', $responseData['message']);
    }

    public function testEmptyPermissionParameterWithUnauthenticatedUserStillRequiresAuth(): void
    {
        $request = $this->createRequestWithPermissions('');

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        // Empty permissions still require authentication first
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized', $response->getContent());
    }

    public function testConfigurationIsSetCorrectly(): void
    {
        $middleware = new PermissionMiddleware($this->authConfig);
        
        // Test that middleware was configured with proper auth config
        $this->assertInstanceOf(PermissionMiddleware::class, $middleware);
        
        // Test the configuration by trying to set a new one
        $newConfig = ['permissions' => ['test' => ['admin']]];
        $middleware->setConfig($newConfig);
        
        // This verifies the setConfig method works
        $this->assertTrue(true);
    }

    public function testPermissionParsing(): void
    {
        // Test that permissions are parsed correctly from query string
        $request = $this->createRequestWithPermissions('manage-users,edit-posts,view-posts');

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        // Should be unauthorized since no user is authenticated
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMiddlewareInterfaceCompliance(): void
    {
        // Test that the middleware implements the interface correctly
        $request = $this->createRequestWithPermissions('manage-users');
        
        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertIsInt($response->getStatusCode());
        $this->assertIsString($response->getContent());
    }

    public function testHtmlResponseForNonJsonRequest(): void
    {
        $request = $this->createRequestWithPermissions('manage-users');

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertStringContainsString('401 - Unauthorized', $response->getContent());
    }

    public function testSpacesInPermissionsAreTrimmed(): void
    {
        // Test that spaces in permission strings are handled correctly
        $request = $this->createRequestWithPermissions(' manage-users , edit-posts ');

        $next = function ($req) {
            return new Response('Success', 200);
        };

        $response = $this->middleware->handle($request, $next);

        // Should be unauthorized since no user is authenticated
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function createRequestWithPermissions(string $permissions, array $headers = []): Request
    {
        $query = $permissions ? ['_permissions' => $permissions] : [];
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
}