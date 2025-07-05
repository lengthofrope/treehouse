<?php

declare(strict_types=1);

namespace Tests\Unit\Cron\Locking;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Cron\Locking\LockMetadata;

/**
 * Test suite for LockMetadata
 */
class LockMetadataTest extends TestCase
{
    public function testBasicMetadata(): void
    {
        $startTime = time();
        $metadata = new LockMetadata(1234, $startTime, 300, 'test-host', 'test-job');
        
        $this->assertEquals(1234, $metadata->pid);
        $this->assertEquals('test-job', $metadata->jobName);
        $this->assertEquals(300, $metadata->timeout);
        $this->assertEquals($startTime, $metadata->startedAt);
        $this->assertEquals('test-host', $metadata->hostname);
    }

    public function testForCurrentProcess(): void
    {
        $metadata = LockMetadata::forCurrentProcess('test-job', 300);
        
        $this->assertEquals(getmypid(), $metadata->pid);
        $this->assertEquals('test-job', $metadata->jobName);
        $this->assertEquals(300, $metadata->timeout);
        $this->assertIsInt($metadata->startedAt);
        $this->assertIsString($metadata->hostname);
    }

    public function testWithMetadata(): void
    {
        $customMetadata = ['key1' => 'value1', 'key2' => 'value2'];
        $metadata = new LockMetadata(1234, time(), 300, 'test-host', 'test-job', $customMetadata);
        
        $this->assertEquals($customMetadata, $metadata->metadata);
    }

    public function testIsStaleExpired(): void
    {
        // Create metadata that's expired (started 400 seconds ago, timeout 300)
        $startTime = time() - 400;
        $metadata = new LockMetadata(getmypid(), $startTime, 300, 'test-host', 'test-job');
        
        $this->assertTrue($metadata->isStale());
        $this->assertTrue($metadata->hasExpired());
    }

    public function testIsStaleNotExpired(): void
    {
        // Create metadata that's not expired (started 100 seconds ago, timeout 300)
        $startTime = time() - 100;
        $metadata = new LockMetadata(getmypid(), $startTime, 300, 'test-host', 'test-job');
        
        $this->assertFalse($metadata->isStale());
        $this->assertFalse($metadata->hasExpired());
    }

    public function testIsProcessRunning(): void
    {
        // Test with current process (should be running)
        $metadata = new LockMetadata(getmypid(), time(), 300, 'test-host', 'test-job');
        $this->assertTrue($metadata->isProcessRunning());
        
        // Test with non-existent process
        $metadata = new LockMetadata(99999, time(), 300, 'test-host', 'test-job');
        $this->assertFalse($metadata->isProcessRunning());
    }

    public function testGetAge(): void
    {
        $startTime = time() - 150;
        $metadata = new LockMetadata(1234, $startTime, 300, 'test-host', 'test-job');
        
        $age = $metadata->getAge();
        $this->assertGreaterThanOrEqual(150, $age);
        $this->assertLessThan(155, $age); // Allow for small time differences
    }

    public function testGetRemainingTimeout(): void
    {
        $startTime = time() - 100;
        $metadata = new LockMetadata(1234, $startTime, 300, 'test-host', 'test-job');
        
        $remaining = $metadata->getRemainingTimeout();
        $this->assertGreaterThanOrEqual(195, $remaining);
        $this->assertLessThan(205, $remaining); // Allow for small time differences
    }

    public function testExpiredRemainingTimeout(): void
    {
        $startTime = time() - 400;
        $metadata = new LockMetadata(1234, $startTime, 300, 'test-host', 'test-job');
        
        $remaining = $metadata->getRemainingTimeout();
        $this->assertLessThanOrEqual(-95, $remaining);
    }

    public function testToArray(): void
    {
        $startTime = time() - 100;
        $customMetadata = ['custom' => 'data'];
        $metadata = new LockMetadata(1234, $startTime, 300, 'test-host', 'test-job', $customMetadata);
        
        $array = $metadata->toArray();
        
        $this->assertEquals(1234, $array['pid']);
        $this->assertEquals('test-job', $array['job_name']);
        $this->assertEquals(300, $array['timeout']);
        $this->assertEquals($startTime, $array['started_at']);
        $this->assertEquals('test-host', $array['hostname']);
        $this->assertEquals($customMetadata, $array['metadata']);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('expires_at', $array);
    }

    public function testToJson(): void
    {
        $metadata = new LockMetadata(1234, time(), 300, 'test-host', 'test-job');
        
        $json = $metadata->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertEquals(1234, $decoded['pid']);
        $this->assertEquals('test-job', $decoded['job_name']);
        $this->assertEquals(300, $decoded['timeout']);
        $this->assertEquals('test-host', $decoded['hostname']);
    }

    public function testFromArray(): void
    {
        $data = [
            'pid' => 5678,
            'job_name' => 'loaded-job',
            'timeout' => 600,
            'started_at' => time() - 50,
            'hostname' => 'test-host',
            'metadata' => ['key' => 'value']
        ];
        
        $metadata = LockMetadata::fromArray($data);
        
        $this->assertEquals(5678, $metadata->pid);
        $this->assertEquals('loaded-job', $metadata->jobName);
        $this->assertEquals(600, $metadata->timeout);
        $this->assertEquals($data['started_at'], $metadata->startedAt);
        $this->assertEquals('test-host', $metadata->hostname);
        $this->assertEquals(['key' => 'value'], $metadata->metadata);
    }

    public function testFromArrayMissingFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: timeout');
        
        LockMetadata::fromArray([
            'pid' => 1234,
            'job_name' => 'test',
            'started_at' => time(),
            'hostname' => 'test-host'
            // Missing 'timeout'
        ]);
    }

    public function testGetStatus(): void
    {
        // Active lock
        $metadata = new LockMetadata(getmypid(), time() - 50, 300, 'test-host', 'test-job');
        $this->assertEquals('active', $metadata->getStatus());
        
        // Expired but process still running
        $metadata = new LockMetadata(getmypid(), time() - 400, 300, 'test-host', 'test-job');
        $this->assertEquals('stale (timeout exceeded)', $metadata->getStatus());
        
        // Process not running
        $metadata = new LockMetadata(99999, time() - 50, 300, 'test-host', 'test-job');
        $this->assertEquals('stale (process not running)', $metadata->getStatus());
    }

    public function testGetInfo(): void
    {
        $startTime = time() - 100;
        $metadata = new LockMetadata(1234, $startTime, 300, 'test-host', 'test-job');
        
        $info = $metadata->getInfo();
        $this->assertStringContainsString('test-job', $info);
        $this->assertStringContainsString('1234', $info);
        $this->assertStringContainsString('test-host', $info);
        $this->assertStringContainsString(date('Y-m-d H:i:s', $startTime), $info);
    }

    public function testHasExpiredZeroTimeout(): void
    {
        $metadata = new LockMetadata(1234, time(), 0, 'test-host', 'test-job');
        
        $this->assertTrue($metadata->hasExpired());
        $this->assertLessThanOrEqual(0, $metadata->getRemainingTimeout());
    }

    public function testHasExpiredNegativeTimeout(): void
    {
        $metadata = new LockMetadata(1234, time(), -100, 'test-host', 'test-job');
        
        $this->assertTrue($metadata->hasExpired());
        $this->assertLessThan(0, $metadata->getRemainingTimeout());
    }

    public function testForCurrentProcessWithMetadata(): void
    {
        $customMetadata = ['environment' => 'test', 'version' => '1.0'];
        $metadata = LockMetadata::forCurrentProcess('test-job', 600, $customMetadata);
        
        $this->assertEquals('test-job', $metadata->jobName);
        $this->assertEquals(600, $metadata->timeout);
        $this->assertEquals($customMetadata, $metadata->metadata);
        $this->assertEquals(getmypid(), $metadata->pid);
    }

    public function testReadonlyProperties(): void
    {
        $metadata = new LockMetadata(1234, time(), 300, 'test-host', 'test-job');
        
        // These should be accessible but readonly
        $this->assertIsInt($metadata->pid);
        $this->assertIsInt($metadata->startedAt);
        $this->assertIsInt($metadata->timeout);
        $this->assertIsString($metadata->hostname);
        $this->assertIsString($metadata->jobName);
        $this->assertIsArray($metadata->metadata);
    }

    public function testStaleDetection(): void
    {
        // Test stale due to process not running
        $metadata = new LockMetadata(99999, time() - 50, 300, 'test-host', 'test-job');
        $this->assertTrue($metadata->isStale());
        
        // Test stale due to timeout
        $metadata = new LockMetadata(getmypid(), time() - 400, 300, 'test-host', 'test-job');
        $this->assertTrue($metadata->isStale());
        
        // Test not stale
        $metadata = new LockMetadata(getmypid(), time() - 50, 300, 'test-host', 'test-job');
        $this->assertFalse($metadata->isStale());
    }

    public function testLargeTimeout(): void
    {
        $largeTimeout = 86400 * 7; // 7 days
        $metadata = new LockMetadata(1234, time() - 100, $largeTimeout, 'test-host', 'test-job');
        
        $this->assertEquals($largeTimeout, $metadata->timeout);
        $this->assertFalse($metadata->hasExpired());
        $this->assertGreaterThan(86400 * 6, $metadata->getRemainingTimeout());
    }
}