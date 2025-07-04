<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Logging;

use LengthOfRope\TreeHouse\Errors\Logging\LogFormatter;
use LengthOfRope\TreeHouse\Errors\Logging\LogLevel;
use LengthOfRope\TreeHouse\Errors\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

class LogFormatterTest extends TestCase
{
    private LogFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new LogFormatter();
    }

    public function testFormatBasicMessage(): void
    {
        $formatted = $this->formatter->format(LogLevel::INFO, 'Test message', []);
        
        $this->assertStringContainsString('INFO', $formatted);
        $this->assertStringContainsString('Test message', $formatted);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $formatted);
    }

    public function testFormatWithContext(): void
    {
        $context = [
            'user_id' => 123,
            'action' => 'login',
            'ip' => '192.168.1.1'
        ];

        $formatted = $this->formatter->format(LogLevel::WARNING, 'User action', $context);
        
        $this->assertStringContainsString('WARNING', $formatted);
        $this->assertStringContainsString('User action', $formatted);
        $this->assertStringContainsString('user_id', $formatted);
        $this->assertStringContainsString('123', $formatted);
        $this->assertStringContainsString('login', $formatted);
    }

    public function testFormatWithException(): void
    {
        $exception = new DatabaseException('Connection failed');
        $exception->addContext('host', 'localhost');
        
        $context = ['exception' => $exception];

        $formatted = $this->formatter->format(LogLevel::CRITICAL, 'Database error', $context);
        
        $this->assertStringContainsString('CRITICAL', $formatted);
        $this->assertStringContainsString('Database error', $formatted);
        $this->assertStringContainsString('Connection failed', $formatted);
        $this->assertStringContainsString('DatabaseException', $formatted);
    }

    public function testFormatAllLogLevels(): void
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG
        ];

        foreach ($levels as $level) {
            $formatted = $this->formatter->format($level, "Test {$level} message", []);
            
            $this->assertStringContainsString(strtoupper($level), $formatted);
            $this->assertStringContainsString("Test {$level} message", $formatted);
        }
    }

    public function testFormatWithNestedContext(): void
    {
        $context = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe',
                'roles' => ['admin', 'user']
            ],
            'request' => [
                'method' => 'POST',
                'url' => '/api/test'
            ]
        ];

        $formatted = $this->formatter->format(LogLevel::INFO, 'Complex context', $context);
        
        $this->assertStringContainsString('Complex context', $formatted);
        $this->assertStringContainsString('John Doe', $formatted);
        $this->assertStringContainsString('POST', $formatted);
    }

    public function testFormatWithSpecialCharacters(): void
    {
        $context = [
            'special' => 'Special chars: àáâãäåæçèéêë',
            'unicode' => '🚀 Unicode test 中文',
            'json' => '{"key": "value"}'
        ];

        $formatted = $this->formatter->format(LogLevel::INFO, 'Special chars test', $context);
        
        $this->assertStringContainsString('Special chars test', $formatted);
        $this->assertStringContainsString('àáâãäåæçèéêë', $formatted);
        $this->assertStringContainsString('🚀', $formatted);
    }

    public function testFormatWithNullAndEmptyValues(): void
    {
        $context = [
            'null_value' => null,
            'empty_string' => '',
            'zero' => 0,
            'false' => false,
            'empty_array' => []
        ];

        $formatted = $this->formatter->format(LogLevel::DEBUG, 'Null values test', $context);
        
        $this->assertStringContainsString('Null values test', $formatted);
        // Should handle null/empty values gracefully without errors
        $this->assertIsString($formatted);
    }

    public function testFormatWithLargeContext(): void
    {
        $largeContext = [];
        for ($i = 0; $i < 100; $i++) {
            $largeContext["key_{$i}"] = "value_{$i}";
        }

        $formatted = $this->formatter->format(LogLevel::INFO, 'Large context test', $largeContext);
        
        $this->assertStringContainsString('Large context test', $formatted);
        $this->assertStringContainsString('key_0', $formatted);
        $this->assertStringContainsString('key_99', $formatted);
    }

    public function testFormatWithCircularReference(): void
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1; // Circular reference

        $context = ['circular' => $obj1];

        // Should not throw an exception
        $formatted = $this->formatter->format(LogLevel::WARNING, 'Circular reference test', $context);
        
        $this->assertStringContainsString('Circular reference test', $formatted);
        $this->assertIsString($formatted);
    }

    public function testFormatTimestampFormat(): void
    {
        $formatted = $this->formatter->format(LogLevel::INFO, 'Timestamp test', []);
        
        // Should contain ISO 8601 timestamp
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}/', $formatted);
    }

    public function testFormatWithCustomConfiguration(): void
    {
        $config = [
            'format' => 'json',
            'include_context' => true,
            'max_depth' => 3
        ];

        $formatter = new LogFormatter($config);
        $context = ['test' => 'value'];

        $formatted = $formatter->format(LogLevel::INFO, 'Custom config test', $context);
        
        $this->assertStringContainsString('Custom config test', $formatted);
        // Should handle custom configuration
        $this->assertIsString($formatted);
    }

    public function testFormatMessageInterpolation(): void
    {
        $context = [
            'user' => 'john_doe',
            'action' => 'login',
            'ip' => '192.168.1.1'
        ];

        $message = 'User {user} performed {action} from {ip}';
        $formatted = $this->formatter->format(LogLevel::INFO, $message, $context);
        
        $this->assertStringContainsString('User john_doe performed login from 192.168.1.1', $formatted);
    }

    public function testFormatWithExceptionTrace(): void
    {
        try {
            throw new DatabaseException('Test exception with trace');
        } catch (DatabaseException $e) {
            $context = ['exception' => $e];
            $formatted = $this->formatter->format(LogLevel::ERROR, 'Exception with trace', $context);
            
            $this->assertStringContainsString('Exception with trace', $formatted);
            $this->assertStringContainsString('Test exception with trace', $formatted);
            $this->assertStringContainsString(__FILE__, $formatted); // Should include file name in trace
        }
    }

    public function testFormatPerformanceWithLargeData(): void
    {
        $largeContext = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeContext["key_{$i}"] = str_repeat("value_{$i}", 100);
        }

        $startTime = microtime(true);
        $formatted = $this->formatter->format(LogLevel::INFO, 'Performance test', $largeContext);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;
        
        $this->assertStringContainsString('Performance test', $formatted);
        $this->assertLessThan(1.0, $executionTime, 'Formatting should complete within 1 second');
    }
}