<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Security\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Test HTTP method spoofing functionality
 */
class MethodSpoofingTest extends TestCase
{
    private Router $router;
    private Session $session;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->session = new Session();
        $this->csrf = new Csrf($this->session);
    }

    protected function tearDown(): void
    {
        // Clean up session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testMethodSpoofingWithPutMethod(): void
    {
        // Register a PUT route
        $this->router->put('/users/{id}', function ($request, $id) {
            return "PUT request for user {$id}";
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        // Create a POST request with _method=PUT and CSRF token
        $request = new Request(
            query: [],
            request: ['_method' => 'PUT', 'name' => 'John', '_token' => $token],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/users/123'
            ]
        );

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('PUT request for user 123', $response->getContent());
    }

    public function testMethodSpoofingWithDeleteMethod(): void
    {
        // Register a DELETE route
        $this->router->delete('/posts/{id}', function ($request, $id) {
            return "DELETE request for post {$id}";
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        // Create a POST request with _method=DELETE and CSRF token
        $request = new Request(
            query: [],
            request: ['_method' => 'DELETE', '_token' => $token],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/posts/456'
            ]
        );

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('DELETE request for post 456', $response->getContent());
    }

    public function testMethodSpoofingWithPatchMethod(): void
    {
        // Register a PATCH route
        $this->router->patch('/articles/{id}', function ($request, $id) {
            return "PATCH request for article {$id}";
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        // Create a POST request with _method=PATCH and CSRF token
        $request = new Request(
            query: [],
            request: ['_method' => 'patch', '_token' => $token], // Test case insensitivity
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/articles/789'
            ]
        );

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('PATCH request for article 789', $response->getContent());
    }

    public function testMethodSpoofingIgnoresInvalidMethods(): void
    {
        // Register POST and GET routes
        $this->router->post('/test', function ($request) {
            return 'POST request';
        });
        
        $this->router->get('/test', function ($request) {
            return 'GET request';
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        // Create a POST request with invalid _method and CSRF token
        $request = new Request(
            query: [],
            request: ['_method' => 'GET', '_token' => $token], // GET is not allowed for spoofing
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/test'
            ]
        );

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('POST request', $response->getContent()); // Should use POST, not GET
    }

    public function testMethodSpoofingOnlyWorksWithPostRequests(): void
    {
        // Register PUT route
        $this->router->put('/users/{id}', function ($request, $id) {
            return "PUT request for user {$id}";
        });

        // Create a GET request with _method=PUT (should be ignored)
        $request = new Request(
            query: ['_method' => 'PUT'],
            request: [],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/users/123'
            ]
        );

        $response = $this->router->dispatch($request);
        
        // Should return 404 because GET /users/123 is not registered
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMethodSpoofingWithEmptyValue(): void
    {
        // Register POST route
        $this->router->post('/test', function ($request) {
            return 'POST request';
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        // Create a POST request with empty _method and CSRF token
        $request = new Request(
            query: [],
            request: ['_method' => '', '_token' => $token],
            files: [],
            cookies: [],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/test'
            ]
        );

        $response = $this->router->dispatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('POST request', $response->getContent());
    }
}