<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use LengthOfRope\TreeHouse\Router\RouteNotFoundException;
use Tests\TestCase;

/**
 * RouteNotFoundException Tests
 *
 * @package Tests\Unit\Router
 */
class RouteNotFoundExceptionTest extends TestCase
{
    public function testDefaultConstructor()
    {
        $exception = new RouteNotFoundException();
        
        $this->assertEquals('Route not found', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithCustomMessage()
    {
        $message = 'Custom route not found message';
        $exception = new RouteNotFoundException($message);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function testConstructorWithCustomCode()
    {
        $exception = new RouteNotFoundException('Route not found', 500);
        
        $this->assertEquals('Route not found', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals(404, $exception->getStatusCode()); // Status code is always 404
    }

    public function testConstructorWithPreviousException()
    {
        $previous = new \Exception('Previous exception');
        $exception = new RouteNotFoundException('Route not found', 404, $previous);
        
        $this->assertEquals('Route not found', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithAllParameters()
    {
        $message = 'Custom message';
        $code = 500;
        $previous = new \Exception('Previous');
        
        $exception = new RouteNotFoundException($message, $code, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals(404, $exception->getStatusCode()); // Always 404
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testGetStatusCodeAlwaysReturns404()
    {
        // Test with different constructor codes
        $exception1 = new RouteNotFoundException('Test', 200);
        $exception2 = new RouteNotFoundException('Test', 500);
        $exception3 = new RouteNotFoundException('Test', 0);
        
        $this->assertEquals(404, $exception1->getStatusCode());
        $this->assertEquals(404, $exception2->getStatusCode());
        $this->assertEquals(404, $exception3->getStatusCode());
    }

    public function testInheritanceFromException()
    {
        $exception = new RouteNotFoundException();
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionCanBeThrown()
    {
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route not found');
        $this->expectExceptionCode(404);
        
        throw new RouteNotFoundException();
    }

    public function testExceptionCanBeThrownWithCustomMessage()
    {
        $message = 'No route matches /api/test';
        
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage($message);
        
        throw new RouteNotFoundException($message);
    }

    public function testExceptionInTryCatch()
    {
        try {
            throw new RouteNotFoundException('Test route error');
        } catch (RouteNotFoundException $e) {
            $this->assertEquals('Test route error', $e->getMessage());
            $this->assertEquals(404, $e->getStatusCode());
            return; // Test passed
        }
        
        $this->fail('Exception was not caught');
    }

    public function testStackTrace()
    {
        $exception = new RouteNotFoundException('Test');
        $trace = $exception->getTrace();
        
        $this->assertIsArray($trace);
        $this->assertArrayHasKey(0, $trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
    }

    public function testToString()
    {
        $exception = new RouteNotFoundException('Test message');
        $string = (string) $exception;
        
        $this->assertStringContainsString('RouteNotFoundException', $string);
        $this->assertStringContainsString('Test message', $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMethodChaining()
    {
        // Test that getStatusCode can be called after getMessage
        $exception = new RouteNotFoundException('Chaining test');
        
        $result = $exception->getMessage() . ' - Status: ' . $exception->getStatusCode();
        $this->assertEquals('Chaining test - Status: 404', $result);
    }
}