<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Security\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Test CSRF validation in Router
 */
class CsrfValidationTest extends TestCase
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

    public function testGetRequestDoesNotRequireCsrfValidation(): void
    {
        $this->router->get('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testPostRequestWithoutCsrfTokenSucceeds(): void
    {
        $this->router->post('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], [], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testPostRequestWithValidCsrfTokenSucceeds(): void
    {
        $this->router->post('/test', function () {
            return new Response('Success');
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        $request = new Request([], ['_token' => $token], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testPostRequestWithInvalidCsrfTokenFails(): void
    {
        $this->router->post('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], ['_token' => 'invalid-token'], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(419, $response->getStatusCode());
        $this->assertEquals('CSRF Token Mismatch', $response->getContent());
    }

    public function testPutRequestWithValidCsrfTokenSucceeds(): void
    {
        $this->router->put('/test', function () {
            return new Response('Success');
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        $request = new Request([], ['_token' => $token], [], [], [
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testPatchRequestWithInvalidCsrfTokenFails(): void
    {
        $this->router->patch('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], ['_token' => 'invalid-token'], [], [], [
            'REQUEST_METHOD' => 'PATCH',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(419, $response->getStatusCode());
        $this->assertEquals('CSRF Token Mismatch', $response->getContent());
    }

    public function testDeleteRequestWithValidCsrfTokenSucceeds(): void
    {
        $this->router->delete('/test', function () {
            return new Response('Success');
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        $request = new Request([], ['_token' => $token], [], [], [
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testAjaxRequestWithInvalidCsrfTokenReturnsJson(): void
    {
        $this->router->post('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], ['_token' => 'invalid-token'], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(419, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('CSRF token mismatch', $content['error']);
        $this->assertArrayHasKey('message', $content);
    }

    public function testJsonRequestWithInvalidCsrfTokenReturnsJson(): void
    {
        $this->router->post('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], ['_token' => 'invalid-token'], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test',
            'HTTP_ACCEPT' => 'application/json'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(419, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('CSRF token mismatch', $content['error']);
        $this->assertArrayHasKey('message', $content);
    }

    public function testCsrfValidationWithMethodSpoofing(): void
    {
        $this->router->put('/test', function () {
            return new Response('Success');
        });

        // Generate a valid CSRF token
        $token = $this->csrf->generateToken();

        $request = new Request([], [
            '_method' => 'PUT',
            '_token' => $token
        ], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testCsrfValidationWithMethodSpoofingInvalidToken(): void
    {
        $this->router->delete('/test', function () {
            return new Response('Success');
        });

        $request = new Request([], [
            '_method' => 'DELETE',
            '_token' => 'invalid-token'
        ], [], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test'
        ]);

        $response = $this->router->dispatch($request);
        $this->assertEquals(419, $response->getStatusCode());
        $this->assertEquals('CSRF Token Mismatch', $response->getContent());
    }
}