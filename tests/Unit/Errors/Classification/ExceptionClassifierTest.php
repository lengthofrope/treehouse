<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Classification;

use LengthOfRope\TreeHouse\Errors\Classification\ExceptionClassifier;
use LengthOfRope\TreeHouse\Errors\Classification\ClassificationResult;
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use LengthOfRope\TreeHouse\Errors\Exceptions\SystemException;
use PHPUnit\Framework\TestCase;

class ExceptionClassifierTest extends TestCase
{
    private ExceptionClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new ExceptionClassifier();
    }

    public function testClassifyDatabaseException(): void
    {
        $exception = new DatabaseException('Connection failed');
        $result = $this->classifier->classify($exception);

        $this->assertInstanceOf(ClassificationResult::class, $result);
        $this->assertEquals('database', $result->category);
        $this->assertEquals('critical', $result->severity);
        $this->assertTrue($result->shouldReport);
        $this->assertTrue($result->logLevel !== 'none');
    }

    public function testClassifyAuthenticationException(): void
    {
        $exception = new AuthenticationException('Login failed');
        $result = $this->classifier->classify($exception);

        $this->assertEquals('authentication', $result->category);
        $this->assertEquals('medium', $result->severity);
        $this->assertFalse($result->shouldReport); // Auth exceptions are not reportable by default
        $this->assertTrue($result->logLevel !== 'none');
    }

    public function testClassifySystemException(): void
    {
        $exception = new SystemException('System failure');
        $result = $this->classifier->classify($exception);

        $this->assertEquals('system', $result->category);
        $this->assertEquals('critical', $result->severity);
        $this->assertTrue($result->shouldReport);
        $this->assertTrue($result->logLevel !== 'none');
    }

    public function testClassifyGenericException(): void
    {
        $exception = new \Exception('Generic error');
        $result = $this->classifier->classify($exception);

        $this->assertEquals('general', $result->category);
        $this->assertEquals('medium', $result->severity);
        $this->assertTrue($result->shouldReport);
        $this->assertTrue($result->logLevel !== 'none');
    }

    public function testClassifySecurityPatterns(): void
    {
        $securityMessages = [
            'SQL injection attempt detected',
            'XSS attack blocked',
            'CSRF token mismatch',
            'Unauthorized access attempt',
            'Suspicious file upload detected'
        ];

        foreach ($securityMessages as $message) {
            $exception = new \Exception($message);
            $result = $this->classifier->classify($exception);

            $this->assertEquals('security', $result->category, "Failed for message: {$message}");
            $this->assertEquals('critical', $result->severity);
            $this->assertTrue($result->shouldReport);
        }
    }

    public function testClassifyCriticalSystemPatterns(): void
    {
        $criticalMessages = [
            'Out of memory',
            'Disk space full',
            'Database connection pool exhausted',
            'Service unavailable',
            'Fatal system error'
        ];

        foreach ($criticalMessages as $message) {
            $exception = new \Exception($message);
            $result = $this->classifier->classify($exception);

            $this->assertEquals('critical', $result->severity, "Failed for message: {$message}");
            $this->assertTrue($result->shouldReport);
        }
    }

    public function testClassifyValidationErrors(): void
    {
        $validationMessages = [
            'Validation failed for field email',
            'Invalid input provided',
            'Required field missing',
            'Format validation error'
        ];

        foreach ($validationMessages as $message) {
            $exception = new \Exception($message);
            $result = $this->classifier->classify($exception);

            $this->assertEquals('validation', $result->category, "Failed for message: {$message}");
            $this->assertEquals('low', $result->severity);
            $this->assertFalse($result->shouldReport);
        }
    }

    public function testClassifyHttpErrors(): void
    {
        $httpMessages = [
            'HTTP 404: Not Found',
            'HTTP 500: Internal Server Error',
            'Bad Request received',
            'Method not allowed'
        ];

        foreach ($httpMessages as $message) {
            $exception = new \Exception($message);
            $result = $this->classifier->classify($exception);

            $this->assertEquals('http', $result->category, "Failed for message: {$message}");
        }
    }

    public function testClassifyFileSystemErrors(): void
    {
        $fileMessages = [
            'File not found: /path/to/file',
            'Permission denied accessing file',
            'Unable to write to directory',
            'File upload failed'
        ];

        foreach ($fileMessages as $message) {
            $exception = new \Exception($message);
            $result = $this->classifier->classify($exception);

            $this->assertEquals('filesystem', $result->category, "Failed for message: {$message}");
        }
    }

    public function testClassifyNetworkErrors(): void
    {
        $networkMessages = [
            'Connection timeout',
            'Network unreachable',
            'DNS resolution failed',
            'SSL handshake failed'
        ];

        foreach ($networkMessages as $message) {
            $exception = new \Exception($message);
            $result = $this->classifier->classify($exception);

            $this->assertEquals('network', $result->category, "Failed for message: {$message}");
        }
    }

    public function testClassificationResultContainsMetadata(): void
    {
        $exception = new DatabaseException('Query timeout');
        $result = $this->classifier->classify($exception);

        $metadata = $result->metadata;
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('exception_class', $metadata);
        $this->assertEquals(DatabaseException::class, $metadata['exception_class']);
    }

    public function testClassificationWithContext(): void
    {
        $exception = new DatabaseException('Connection failed');
        $exception->addContext('host', 'localhost');
        $exception->addContext('port', 3306);

        $result = $this->classifier->classify($exception);

        $this->assertEquals('database', $result->category);
        $this->assertEquals('critical', $result->severity);
    }

    public function testClassificationConsistency(): void
    {
        $exception = new DatabaseException('Same error message');
        
        $result1 = $this->classifier->classify($exception);
        $result2 = $this->classifier->classify($exception);

        $this->assertEquals($result1->category, $result2->category);
        $this->assertEquals($result1->severity, $result2->severity);
        $this->assertEquals($result1->shouldReport, $result2->shouldReport);
    }

    public function testClassifyExceptionWithPreviousException(): void
    {
        $previous = new \PDOException('Connection refused');
        $exception = new DatabaseException('Database error', null, [], [], $previous);

        $result = $this->classifier->classify($exception);

        $this->assertEquals('database', $result->category);
        $this->assertEquals('critical', $result->severity);
    }
}