<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\UploadedFile;
use Tests\TestCase;

/**
 * Request Test
 * 
 * @package Tests\Unit\Http
 */
class RequestTest extends TestCase
{
    public function testCreateFromGlobals(): void
    {
        // Backup original globals
        $originalGet = $_GET;
        $originalPost = $_POST;
        $originalFiles = $_FILES;
        $originalCookie = $_COOKIE;
        $originalServer = $_SERVER;

        try {
            // Set up test globals
            $_GET = ['query' => 'test'];
            $_POST = ['data' => 'value'];
            $_FILES = [];
            $_COOKIE = ['session' => 'abc123'];
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/test?query=test',
                'HTTP_HOST' => 'example.com',
                'HTTP_USER_AGENT' => 'Test Agent',
            ];

            $request = Request::createFromGlobals();

            $this->assertEquals('POST', $request->method());
            $this->assertEquals('/test', $request->path());
            $this->assertEquals('test', $request->query('query'));
            $this->assertEquals('value', $request->request('data'));
            $this->assertEquals('abc123', $request->cookie('session'));
            $this->assertEquals('example.com', $request->getHost());
        } finally {
            // Restore original globals
            $_GET = $originalGet;
            $_POST = $originalPost;
            $_FILES = $originalFiles;
            $_COOKIE = $originalCookie;
            $_SERVER = $originalServer;
        }
    }

    public function testBasicRequestProperties(): void
    {
        $request = new Request(
            ['q' => 'search'],
            ['name' => 'John'],
            [],
            ['token' => 'xyz'],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/search?q=search',
                'HTTP_HOST' => 'test.com',
                'HTTP_USER_AGENT' => 'Browser',
            ]
        );

        $this->assertEquals('GET', $request->method());
        $this->assertEquals('/search', $request->path());
        $this->assertEquals('search', $request->query('q'));
        $this->assertEquals('John', $request->request('name'));
        $this->assertEquals('xyz', $request->cookie('token'));
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('Browser', $request->userAgent());
    }

    public function testInputMethod(): void
    {
        $request = new Request(
            ['query_param' => 'query_value'],
            ['post_param' => 'post_value']
        );

        // Test getting all input
        $allInput = $request->input();
        $this->assertEquals('query_value', $allInput['query_param']);
        $this->assertEquals('post_value', $allInput['post_param']);

        // Test getting specific input
        $this->assertEquals('query_value', $request->input('query_param'));
        $this->assertEquals('post_value', $request->input('post_param'));
        $this->assertEquals('default', $request->input('nonexistent', 'default'));
    }

    public function testHeaders(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer token123',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '100',
            ]
        );

        $this->assertEquals('application/json', $request->header('accept'));
        $this->assertEquals('Bearer token123', $request->header('authorization'));
        $this->assertEquals('application/json', $request->header('content-type'));
        $this->assertEquals('100', $request->header('content-length'));
        $this->assertTrue($request->hasHeader('accept'));
        $this->assertFalse($request->hasHeader('nonexistent'));
    }

    public function testSecureConnection(): void
    {
        // Test HTTPS detection
        $secureRequest = new Request([], [], [], [], ['HTTPS' => 'on']);
        $this->assertTrue($secureRequest->isSecure());

        // Test port 443
        $portRequest = new Request([], [], [], [], ['SERVER_PORT' => '443']);
        $this->assertTrue($portRequest->isSecure());

        // Test X-Forwarded-Proto header
        $forwardedRequest = new Request([], [], [], [], ['HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertTrue($forwardedRequest->isSecure());

        // Test non-secure
        $nonSecureRequest = new Request([], [], [], [], ['SERVER_PORT' => '80']);
        $this->assertFalse($nonSecureRequest->isSecure());
    }

    public function testJsonHandling(): void
    {
        $jsonData = '{"name": "John", "age": 30}';
        $request = new Request([], [], [], [], [], $jsonData);

        $this->assertEquals('John', $request->json('name'));
        $this->assertEquals(30, $request->json('age'));
        $this->assertEquals('default', $request->json('nonexistent', 'default'));

        $allJson = $request->json();
        $this->assertEquals(['name' => 'John', 'age' => 30], $allJson);
    }

    public function testAjaxDetection(): void
    {
        $ajaxRequest = new Request([], [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $this->assertTrue($ajaxRequest->isAjax());

        $normalRequest = new Request();
        $this->assertFalse($normalRequest->isAjax());
    }

    public function testJsonExpectation(): void
    {
        $jsonRequest = new Request([], [], [], [], [
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $this->assertTrue($jsonRequest->expectsJson());

        $ajaxRequest = new Request([], [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $this->assertTrue($ajaxRequest->expectsJson());

        $htmlRequest = new Request([], [], [], [], [
            'HTTP_ACCEPT' => 'text/html'
        ]);
        $this->assertFalse($htmlRequest->expectsJson());
    }

    public function testMethodChecking(): void
    {
        $getRequest = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET']);
        $this->assertTrue($getRequest->isMethod('GET'));
        $this->assertTrue($getRequest->isMethod('get'));
        $this->assertFalse($getRequest->isMethod('POST'));

        $postRequest = new Request([], [], [], [], ['REQUEST_METHOD' => 'POST']);
        $this->assertTrue($postRequest->isMethod('POST'));
        $this->assertFalse($postRequest->isMethod('GET'));
    }

    public function testIpAddress(): void
    {
        // Test direct IP
        $request = new Request([], [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertEquals('192.168.1.1', $request->ip());

        // Test forwarded IP
        $forwardedRequest = new Request([], [], [], [], [
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1, 192.168.1.1',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);
        $this->assertEquals('203.0.113.1', $forwardedRequest->ip());

        // Test real IP header
        $realIpRequest = new Request([], [], [], [], [
            'HTTP_X_REAL_IP' => '203.0.113.2',
            'REMOTE_ADDR' => '192.168.1.1'
        ]);
        $this->assertEquals('203.0.113.2', $realIpRequest->ip());
    }

    public function testUrl(): void
    {
        $request = new Request([], [], [], [], [
            'REQUEST_URI' => '/path/to/resource?param=value',
            'HTTP_HOST' => 'example.com',
            'SERVER_PORT' => '80'
        ]);

        $this->assertEquals('http://example.com/path/to/resource', $request->url());

        // Test HTTPS with custom port
        $secureRequest = new Request([], [], [], [], [
            'REQUEST_URI' => '/secure/path',
            'HTTP_HOST' => 'secure.com',
            'SERVER_PORT' => '8443',
            'HTTPS' => 'on'
        ]);

        $this->assertEquals('https://secure.com:8443/secure/path', $secureRequest->url());
    }

    public function testFileHandling(): void
    {
        $files = [
            'upload' => [
                'tmp_name' => '/tmp/phptest',
                'name' => 'test.txt',
                'type' => 'text/plain',
                'error' => UPLOAD_ERR_OK,
                'size' => 100
            ]
        ];

        $request = new Request([], [], $files);

        // hasFile() checks if file exists AND is valid, but our test file won't be valid
        $this->assertFalse($request->hasFile('upload')); // Will be false because is_uploaded_file() returns false
        $this->assertFalse($request->hasFile('nonexistent'));

        $file = $request->file('upload');
        $this->assertInstanceOf(UploadedFile::class, $file);
        $this->assertEquals('test.txt', $file->getName());
    }
}