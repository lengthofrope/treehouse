<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

class DatabaseExceptionTest extends TestCase
{
    public function testCanCreateWithDefaults(): void
    {
        $exception = new DatabaseException('Database error');
        
        $this->assertEquals('Database error', $exception->getMessage());
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('critical', $exception->getSeverity());
        $this->assertStringStartsWith('DB_', $exception->getErrorCode());
    }

    public function testCanCreateWithCustomParameters(): void
    {
        $context = ['table' => 'users', 'operation' => 'insert'];
        $exception = new DatabaseException(
            'Custom database error',
            'DB_CUSTOM_001',
            $context,
            ['user_message' => 'Database operation failed']
        );
        
        $this->assertEquals('Custom database error', $exception->getMessage());
        $this->assertEquals('DB_CUSTOM_001', $exception->getErrorCode());
        $this->assertEquals($context, $exception->getContext());
        $this->assertEquals('Database operation failed', $exception->getUserMessage());
    }

    public function testCanCreateWithPreviousException(): void
    {
        $previous = new \PDOException('Connection failed');
        $exception = new DatabaseException(
            'Database connection error',
            null,
            [],
            [],
            $previous
        );
        
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals('Database connection error', $exception->getMessage());
    }

    public function testDefaultErrorCodeGeneration(): void
    {
        $exception1 = new DatabaseException('Error 1');
        $exception2 = new DatabaseException('Error 2');
        
        $this->assertStringStartsWith('DB_', $exception1->getErrorCode());
        $this->assertStringStartsWith('DB_', $exception2->getErrorCode());
        $this->assertNotEquals($exception1->getErrorCode(), $exception2->getErrorCode());
    }

    public function testContextContainsDatabaseInfo(): void
    {
        $context = [
            'table' => 'users',
            'operation' => 'select',
            'query' => 'SELECT * FROM users WHERE id = ?',
            'bindings' => [123]
        ];
        
        $exception = new DatabaseException('Query failed', null, $context);
        
        $exceptionContext = $exception->getContext();
        $this->assertEquals('users', $exceptionContext['table']);
        $this->assertEquals('select', $exceptionContext['operation']);
        $this->assertEquals('SELECT * FROM users WHERE id = ?', $exceptionContext['query']);
        $this->assertEquals([123], $exceptionContext['bindings']);
    }

    public function testToArrayIncludesAllData(): void
    {
        $exception = new DatabaseException(
            'Database error',
            'DB_TEST_001',
            ['table' => 'users'],
            ['user_message' => 'Operation failed']
        );
        
        $array = $exception->toArray();
        
        $this->assertEquals('Database error', $array['message']);
        $this->assertEquals('DB_TEST_001', $array['error_code']);
        $this->assertEquals(500, $array['status_code']);
        $this->assertEquals('critical', $array['severity']);
        $this->assertEquals('Operation failed', $array['user_message']);
        $this->assertArrayHasKey('table', $array['context']);
    }

    public function testSummaryContainsRelevantInfo(): void
    {
        $exception = new DatabaseException(
            'Connection timeout',
            'DB_TIMEOUT_001',
            ['host' => 'localhost', 'timeout' => 30]
        );
        
        $summary = $exception->getSummary();
        
        $this->assertStringContainsString('DB_TIMEOUT_001', $summary);
        $this->assertStringContainsString('Connection timeout', $summary);
        $this->assertStringContainsString('critical', $summary);
    }

    public function testIsReportableByDefault(): void
    {
        $exception = new DatabaseException('Database error');
        
        $this->assertTrue($exception->isReportable());
        $this->assertTrue($exception->shouldReport());
    }

    public function testIsLoggableByDefault(): void
    {
        $exception = new DatabaseException('Database error');
        
        $this->assertTrue($exception->isLoggable());
    }

    public function testJsonSerialization(): void
    {
        $exception = new DatabaseException(
            'JSON test',
            'DB_JSON_001',
            ['data' => 'test']
        );
        
        $json = $exception->toJson();
        $data = json_decode($json, true);
        
        $this->assertIsArray($data);
        $this->assertEquals('JSON test', $data['message']);
        $this->assertEquals('DB_JSON_001', $data['error_code']);
    }
}