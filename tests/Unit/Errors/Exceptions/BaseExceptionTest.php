<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

class BaseExceptionTest extends TestCase
{
    public function testCanCreateExceptionWithDefaults(): void
    {
        $exception = new DatabaseException('Test message');
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertNotEmpty($exception->getErrorCode());
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('critical', $exception->getSeverity());
        $this->assertIsArray($exception->getContext());
    }

    public function testCanSetAndGetUserMessage(): void
    {
        $exception = new DatabaseException('Main message');
        $exception->setUserMessage('Custom user message');
        
        $this->assertEquals('Custom user message', $exception->getUserMessage());
    }

    public function testCanSetAndGetErrorCode(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->setErrorCode('CUSTOM_001');
        
        $this->assertEquals('CUSTOM_001', $exception->getErrorCode());
    }

    public function testCanSetAndGetStatusCode(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->setStatusCode(422);
        
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function testCanSetAndGetSeverity(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->setSeverity('high');
        
        $this->assertEquals('high', $exception->getSeverity());
    }

    public function testCanSetAndGetContext(): void
    {
        $exception = new DatabaseException('Test message');
        $context = ['key' => 'value', 'number' => 42];
        $exception->setContext($context);
        
        $this->assertEquals($context, $exception->getContext());
    }

    public function testCanAddToContext(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->addContext('new_key', 'new_value');
        
        $context = $exception->getContext();
        $this->assertArrayHasKey('new_key', $context);
        $this->assertEquals('new_value', $context['new_key']);
    }

    public function testToArrayReturnsCompleteData(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->setUserMessage('User message');
        $exception->setErrorCode('TEST_001');
        $exception->setStatusCode(422);
        $exception->setSeverity('high');
        
        $array = $exception->toArray();
        
        $this->assertEquals('Test message', $array['message']);
        $this->assertEquals('User message', $array['user_message']);
        $this->assertEquals('TEST_001', $array['error_code']);
        $this->assertEquals(422, $array['status_code']);
        $this->assertEquals('high', $array['severity']);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('trace', $array);
    }

    public function testValidSeverityLevels(): void
    {
        $validLevels = ['low', 'medium', 'high', 'critical'];
        
        foreach ($validLevels as $level) {
            $exception = new DatabaseException('Test');
            $exception->setSeverity($level);
            $this->assertEquals($level, $exception->getSeverity());
        }
    }

    public function testInvalidSeverityThrowsException(): void
    {
        $this->expectException(\LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException::class);
        
        $exception = new DatabaseException('Test');
        $exception->setSeverity('invalid');
    }

    public function testReportableFlag(): void
    {
        $exception = new DatabaseException('Test');
        
        $this->assertTrue($exception->isReportable());
        $this->assertTrue($exception->shouldReport());
        
        $exception->setReportable(false);
        $this->assertFalse($exception->isReportable());
        $this->assertFalse($exception->shouldReport());
    }

    public function testLoggableFlag(): void
    {
        $exception = new DatabaseException('Test');
        
        $this->assertTrue($exception->isLoggable());
        
        $exception->setLoggable(false);
        $this->assertFalse($exception->isLoggable());
    }

    public function testGetSummary(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->setErrorCode('TEST_001');
        
        $summary = $exception->getSummary();
        
        $this->assertStringContainsString('TEST_001', $summary);
        $this->assertStringContainsString('Test message', $summary);
        $this->assertStringContainsString('critical', $summary);
    }

    public function testToJson(): void
    {
        $exception = new DatabaseException('Test message');
        $exception->setErrorCode('TEST_001');
        
        $json = $exception->toJson();
        $data = json_decode($json, true);
        
        $this->assertEquals('Test message', $data['message']);
        $this->assertEquals('TEST_001', $data['error_code']);
    }

    public function testExceptionInheritance(): void
    {
        $exception = new DatabaseException('Test message');
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionWithPreviousException(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new DatabaseException('Current exception', null, [], [], $previous);
        
        $this->assertSame($previous, $exception->getPrevious());
    }
}