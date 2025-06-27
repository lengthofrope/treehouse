<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * Base test case for TreeHouse framework tests
 * 
 * Provides common functionality and utilities for all test cases.
 * 
 * @package Tests
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset any global state if needed
        $this->resetGlobalState();
    }

    /**
     * Clean up the test environment
     * 
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up any test artifacts
        $this->cleanupTestArtifacts();
        
        // Clear any time mocking
        Carbon::clearTestNow();
    }

    /**
     * Reset global state for clean testing
     * 
     * @return void
     */
    protected function resetGlobalState(): void
    {
        // Override in subclasses if needed
    }

    /**
     * Clean up test artifacts
     *
     * @return void
     */
    protected function cleanupTestArtifacts(): void
    {
        $this->cleanupTempFiles();
    }

    /**
     * Assert that a value is a valid UUID
     * 
     * @param string $value
     * @param string $message
     * @return void
     */
    protected function assertIsUuid(string $value, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
            $message ?: "Failed asserting that '{$value}' is a valid UUID."
        );
    }

    /**
     * Assert that a value is a valid JSON string
     * 
     * @param string $value
     * @param string $message
     * @return void
     */
    protected function assertIsJson(string $value, string $message = ''): void
    {
        json_decode($value);
        $this->assertEquals(
            JSON_ERROR_NONE,
            json_last_error(),
            $message ?: "Failed asserting that '{$value}' is valid JSON."
        );
    }

    /**
     * Assert that an array has the expected structure
     * 
     * @param array $expectedStructure
     * @param array $actual
     * @param string $message
     * @return void
     */
    protected function assertArrayStructure(array $expectedStructure, array $actual, string $message = ''): void
    {
        foreach ($expectedStructure as $key => $value) {
            if (is_array($value)) {
                $this->assertArrayHasKey($key, $actual, $message);
                $this->assertIsArray($actual[$key], $message);
                $this->assertArrayStructure($value, $actual[$key], $message);
            } else {
                $this->assertArrayHasKey($value, $actual, $message);
            }
        }
    }

    /**
     * Assert that a string contains all given substrings
     * 
     * @param array $needles
     * @param string $haystack
     * @param string $message
     * @return void
     */
    protected function assertStringContainsAll(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $haystack, $message);
        }
    }

    /**
     * Assert that a string does not contain any of the given substrings
     * 
     * @param array $needles
     * @param string $haystack
     * @param string $message
     * @return void
     */
    protected function assertStringContainsNone(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            $this->assertStringNotContainsString($needle, $haystack, $message);
        }
    }

    /**
     * Create a temporary file for testing
     * 
     * @param string $content
     * @param string $suffix
     * @return string Path to the temporary file
     */
    protected function createTempFile(string $content = '', string $suffix = '.tmp'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'treehouse_test_') . $suffix;
        file_put_contents($tempFile, $content);
        
        // Register for cleanup
        $this->tempFiles[] = $tempFile;
        
        return $tempFile;
    }

    /**
     * Create a temporary directory for testing
     * 
     * @return string Path to the temporary directory
     */
    protected function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/treehouse_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        
        // Register for cleanup
        $this->tempDirs[] = $tempDir;
        
        return $tempDir;
    }

    /**
     * Temporary files created during testing
     * 
     * @var array<string>
     */
    private array $tempFiles = [];

    /**
     * Temporary directories created during testing
     * 
     * @var array<string>
     */
    private array $tempDirs = [];

    /**
     * Clean up temporary files and directories
     * 
     * @return void
     */
    private function cleanupTempFiles(): void
    {
        // Clean up temporary files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        // Clean up temporary directories
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectory($dir);
            }
        }
        $this->tempDirs = [];
    }

    /**
     * Recursively remove a directory
     * 
     * @param string $dir
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }


    /**
     * Get a reflection property value
     * 
     * @param object $object
     * @param string $property
     * @return mixed
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        
        return $property->getValue($object);
    }

    /**
     * Set a reflection property value
     * 
     * @param object $object
     * @param string $property
     * @param mixed $value
     * @return void
     */
    protected function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call a private method
     * 
     * @param object $object
     * @param string $method
     * @param array $args
     * @return mixed
     */
    protected function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $args);
    }

    /**
     * Mock the current time for testing
     * 
     * @param Carbon|string|null $time
     * @return void
     */
    protected function mockTime(Carbon|string|null $time): void
    {
        Carbon::setTestNow($time);
    }

    /**
     * Travel forward in time
     * 
     * @param int $seconds
     * @return void
     */
    protected function travelForward(int $seconds): void
    {
        $current = Carbon::hasTestNow() ? Carbon::now() : Carbon::now();
        Carbon::setTestNow($current->addSeconds($seconds));
    }

    /**
     * Travel backward in time
     * 
     * @param int $seconds
     * @return void
     */
    protected function travelBackward(int $seconds): void
    {
        $current = Carbon::hasTestNow() ? Carbon::now() : Carbon::now();
        Carbon::setTestNow($current->subSeconds($seconds));
    }

    /**
     * Clear time mocking
     * 
     * @return void
     */
    protected function clearTimeMocking(): void
    {
        Carbon::clearTestNow();
    }
}