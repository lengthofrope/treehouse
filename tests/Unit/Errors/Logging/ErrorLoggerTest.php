<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Logging;

use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;
use LengthOfRope\TreeHouse\Errors\Logging\LogFormatter;
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

class ErrorLoggerTest extends TestCase
{
    private ErrorLogger $logger;
    private string $testLogFile;

    protected function setUp(): void
    {
        $this->testLogFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        $channels = [
            'test' => [
                'driver' => 'file',
                'path' => dirname($this->testLogFile),
                'filename' => basename($this->testLogFile),
                'level' => 'debug'
            ]
        ];
        
        $this->logger = new ErrorLogger('test', $channels);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }

    public function testCanLogDatabaseException(): void
    {
        $exception = new DatabaseException('Connection failed');
        $exception->addContext('host', 'localhost');
        $exception->addContext('port', 3306);

        $this->logger->critical('Database error occurred', [
            'exception' => $exception,
            'additional_info' => 'Connection timeout'
        ]);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Database error occurred', $logContent);
        $this->assertStringContainsString('Connection failed', $logContent);
    }

    public function testCanLogAuthenticationException(): void
    {
        $exception = new AuthenticationException('Login failed', 'password', 'testuser');

        $this->logger->warning('Authentication failure', [
            'exception' => $exception,
            'ip_address' => '192.168.1.1'
        ]);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Authentication failure', $logContent);
        $this->assertStringContainsString('Login failed', $logContent);
    }

    public function testLogLevels(): void
    {
        $exception = new DatabaseException('Test error');

        // Test all log levels
        $this->logger->emergency('Emergency message', ['exception' => $exception]);
        $this->logger->alert('Alert message', ['exception' => $exception]);
        $this->logger->critical('Critical message', ['exception' => $exception]);
        $this->logger->error('Error message', ['exception' => $exception]);
        $this->logger->warning('Warning message', ['exception' => $exception]);
        $this->logger->notice('Notice message', ['exception' => $exception]);
        $this->logger->info('Info message', ['exception' => $exception]);
        $this->logger->debug('Debug message', ['exception' => $exception]);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        
        $this->assertStringContainsString('EMERGENCY', $logContent);
        $this->assertStringContainsString('ALERT', $logContent);
        $this->assertStringContainsString('CRITICAL', $logContent);
        $this->assertStringContainsString('ERROR', $logContent);
        $this->assertStringContainsString('WARNING', $logContent);
        $this->assertStringContainsString('NOTICE', $logContent);
        $this->assertStringContainsString('INFO', $logContent);
        $this->assertStringContainsString('DEBUG', $logContent);
    }

    public function testLogWithoutException(): void
    {
        $this->logger->info('Simple log message', [
            'user_id' => 123,
            'action' => 'login'
        ]);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Simple log message', $logContent);
        $this->assertStringContainsString('user_id', $logContent);
        $this->assertStringContainsString('action', $logContent);
    }

    public function testLogWithComplexContext(): void
    {
        $context = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe',
                'roles' => ['admin', 'user']
            ],
            'request' => [
                'method' => 'POST',
                'url' => '/api/test',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer token123'
                ]
            ],
            'metadata' => [
                'timestamp' => time(),
                'server' => 'web-01'
            ]
        ];

        $this->logger->info('Complex context test', $context);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Complex context test', $logContent);
        $this->assertStringContainsString('John Doe', $logContent);
        $this->assertStringContainsString('POST', $logContent);
    }

    public function testLogFormatsTimestamp(): void
    {
        $this->logger->info('Timestamp test');

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        
        // Should contain a timestamp in ISO format
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $logContent);
    }

    public function testLogHandlesLargeContext(): void
    {
        $largeContext = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeContext["key_{$i}"] = "value_{$i}";
        }

        $this->logger->info('Large context test', $largeContext);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Large context test', $logContent);
    }

    public function testLogHandlesSpecialCharacters(): void
    {
        $context = [
            'special_chars' => 'Special: àáâãäåæçèéêë ñòóôõö ùúûüý',
            'unicode' => '🚀 Unicode test 中文 العربية',
            'json_chars' => '{"key": "value", "array": [1, 2, 3]}'
        ];

        $this->logger->info('Special characters test', $context);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Special characters test', $logContent);
        $this->assertStringContainsString('🚀', $logContent);
    }

    public function testLogRotation(): void
    {
        // Create a large log entry to test rotation behavior
        $largeMessage = str_repeat('This is a test message. ', 1000);
        
        for ($i = 0; $i < 10; $i++) {
            $this->logger->info("Log entry {$i}: {$largeMessage}");
        }

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Log entry 0', $logContent);
        $this->assertStringContainsString('Log entry 9', $logContent);
    }

    public function testLogWithNullValues(): void
    {
        $context = [
            'null_value' => null,
            'empty_string' => '',
            'zero' => 0,
            'false' => false,
            'empty_array' => []
        ];

        $this->logger->info('Null values test', $context);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Null values test', $logContent);
    }

    public function testLogWithCircularReference(): void
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1; // Circular reference

        $context = [
            'circular' => $obj1,
            'message' => 'Testing circular reference handling'
        ];

        // Should not throw an exception
        $this->logger->info('Circular reference test', $context);

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('Circular reference test', $logContent);
    }

    public function testLogFileCreation(): void
    {
        $newLogFile = sys_get_temp_dir() . '/new_test_log_' . uniqid() . '.log';
        
        $channels = [
            'new_test' => [
                'driver' => 'file',
                'path' => dirname($newLogFile),
                'filename' => basename($newLogFile),
                'level' => 'debug'
            ]
        ];
        
        $newLogger = new ErrorLogger('new_test', $channels);

        $this->assertFileDoesNotExist($newLogFile);

        $newLogger->info('Test message');

        $this->assertFileExists($newLogFile);

        // Cleanup
        if (file_exists($newLogFile)) {
            unlink($newLogFile);
        }
    }
}