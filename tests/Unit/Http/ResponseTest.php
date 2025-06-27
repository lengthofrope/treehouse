<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use LengthOfRope\TreeHouse\Http\Response;
use Tests\TestCase;

/**
 * Response Test
 * 
 * @package Tests\Unit\Http
 */
class ResponseTest extends TestCase
{
    public function testBasicResponse(): void
    {
        $response = new Response('Hello World', 200, ['Content-Type' => 'text/plain']);

        $this->assertEquals('Hello World', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeader('Content-Type'));
        $this->assertEquals('OK', $response->getStatusText());
    }

    public function testOkResponse(): void
    {
        $response = Response::ok('Success');

        $this->assertEquals('Success', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreatedResponse(): void
    {
        $response = Response::created('Resource created');

        $this->assertEquals('Resource created', $response->getContent());
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testNoContentResponse(): void
    {
        $response = Response::noContent();

        $this->assertEquals('', $response->getContent());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testRedirectResponse(): void
    {
        $response = Response::redirect('https://example.com');

        $this->assertEquals('', $response->getContent());
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeader('Location'));

        // Test with custom status code
        $permanentRedirect = Response::redirect('https://example.com', 301);
        $this->assertEquals(301, $permanentRedirect->getStatusCode());
    }

    public function testJsonResponse(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $response = Response::json($data);

        $this->assertEquals(json_encode($data), $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));

        // Test with custom status code
        $errorResponse = Response::json(['error' => 'Not found'], 404);
        $this->assertEquals(404, $errorResponse->getStatusCode());
    }

    public function testHtmlResponse(): void
    {
        $html = '<h1>Hello World</h1>';
        $response = Response::html($html);

        $this->assertEquals($html, $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeader('Content-Type'));
    }

    public function testTextResponse(): void
    {
        $text = 'Plain text content';
        $response = Response::text($text);

        $this->assertEquals($text, $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeader('Content-Type'));
    }

    public function testDownloadResponse(): void
    {
        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Test file content');

        try {
            $response = Response::download($tempFile, 'test.txt');

            $this->assertEquals('Test file content', $response->getContent());
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('attachment; filename="test.txt"', $response->getHeader('Content-Disposition'));
            $this->assertEquals('17', $response->getHeader('Content-Length')); // Length of 'Test file content'
        } finally {
            unlink($tempFile);
        }
    }

    public function testDownloadNonExistentFile(): void
    {
        $response = Response::download('/nonexistent/file.txt');

        $this->assertEquals('File not found', $response->getContent());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testFileResponse(): void
    {
        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'Inline file content');

        try {
            $response = Response::file($tempFile, 'inline.txt');

            $this->assertEquals('Inline file content', $response->getContent());
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertStringContainsString('inline; filename="inline.txt"', $response->getHeader('Content-Disposition'));
        } finally {
            unlink($tempFile);
        }
    }

    public function testErrorResponses(): void
    {
        // Bad Request
        $badRequest = Response::badRequest('Invalid input');
        $this->assertEquals(400, $badRequest->getStatusCode());
        $this->assertEquals('Invalid input', $badRequest->getContent());

        // Unauthorized
        $unauthorized = Response::unauthorized('Login required');
        $this->assertEquals(401, $unauthorized->getStatusCode());
        $this->assertEquals('Login required', $unauthorized->getContent());

        // Forbidden
        $forbidden = Response::forbidden('Access denied');
        $this->assertEquals(403, $forbidden->getStatusCode());
        $this->assertEquals('Access denied', $forbidden->getContent());

        // Not Found
        $notFound = Response::notFound('Page not found');
        $this->assertEquals(404, $notFound->getStatusCode());
        $this->assertEquals('Page not found', $notFound->getContent());

        // Method Not Allowed
        $methodNotAllowed = Response::methodNotAllowed('Method not supported');
        $this->assertEquals(405, $methodNotAllowed->getStatusCode());
        $this->assertEquals('Method not supported', $methodNotAllowed->getContent());

        // Unprocessable Entity
        $unprocessable = Response::unprocessableEntity('Validation failed');
        $this->assertEquals(422, $unprocessable->getStatusCode());
        $this->assertEquals('Validation failed', $unprocessable->getContent());

        // Server Error
        $serverError = Response::serverError('Something went wrong');
        $this->assertEquals(500, $serverError->getStatusCode());
        $this->assertEquals('Something went wrong', $serverError->getContent());
    }

    public function testHeaderManipulation(): void
    {
        $response = new Response();

        // Set header
        $response->setHeader('X-Custom-Header', 'custom-value');
        $this->assertEquals('custom-value', $response->getHeader('X-Custom-Header'));
        $this->assertTrue($response->hasHeader('X-Custom-Header'));

        // Add multiple headers
        $response->withHeaders([
            'X-Another-Header' => 'another-value',
            'X-Third-Header' => 'third-value'
        ]);
        $this->assertEquals('another-value', $response->getHeader('X-Another-Header'));
        $this->assertEquals('third-value', $response->getHeader('X-Third-Header'));

        // Remove header
        $response->removeHeader('X-Custom-Header');
        $this->assertFalse($response->hasHeader('X-Custom-Header'));
        $this->assertNull($response->getHeader('X-Custom-Header'));

        // Get all headers
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Another-Header', $headers);
        $this->assertArrayHasKey('X-Third-Header', $headers);
    }

    public function testContentManipulation(): void
    {
        $response = new Response();

        // Set content
        $response->setContent('New content');
        $this->assertEquals('New content', $response->getContent());

        // Test fluent interface
        $fluentResponse = $response->setContent('Fluent content')->setStatusCode(201);
        $this->assertEquals('Fluent content', $fluentResponse->getContent());
        $this->assertEquals(201, $fluentResponse->getStatusCode());
    }

    public function testStatusCodeManipulation(): void
    {
        $response = new Response();

        // Set status code
        $response->setStatusCode(404);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getStatusText());

        // Test unknown status code
        $response->setStatusCode(999);
        $this->assertEquals(999, $response->getStatusCode());
        $this->assertEquals('Unknown Status', $response->getStatusText());
    }

    public function testCookieHandling(): void
    {
        $response = new Response();

        $response->withCookie('test_cookie', 'test_value', time() + 3600, '/', 'example.com', true, true, 'Strict');

        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $cookieHeader = $response->getHeader('Set-Cookie');
        $this->assertStringContainsString('test_cookie=', $cookieHeader);
        $this->assertStringContainsString('Path=/', $cookieHeader);
        $this->assertStringContainsString('Domain=example.com', $cookieHeader);
        $this->assertStringContainsString('Secure', $cookieHeader);
        $this->assertStringContainsString('HttpOnly', $cookieHeader);
        $this->assertStringContainsString('SameSite=Strict', $cookieHeader);
    }

    public function testToString(): void
    {
        $response = new Response('Test content');
        $this->assertEquals('Test content', (string) $response);
    }

    public function testStatusTexts(): void
    {
        $testCases = [
            100 => 'Continue',
            200 => 'OK',
            201 => 'Created',
            301 => 'Moved Permanently',
            302 => 'Found',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
        ];

        foreach ($testCases as $code => $text) {
            $response = new Response('', $code);
            $this->assertEquals($text, $response->getStatusText());
        }
    }
}