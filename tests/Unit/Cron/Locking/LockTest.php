<?php

declare(strict_types=1);

namespace Tests\Unit\Cron\Locking;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Cron\Locking\Lock;
use LengthOfRope\TreeHouse\Cron\Locking\LockMetadata;

/**
 * Test suite for Lock
 */
class LockTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/treehouse_cron_test_' . uniqid();
        if (!mkdir($this->tempDir, 0755, true)) {
            $this->fail('Could not create temp directory');
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDir();
    }

    private function cleanupTempDir(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testCreateLock(): void
    {
        $lockFile = $this->tempDir . '/test.lock';
        $lock = new Lock($lockFile);
        
        $this->assertEquals($lockFile, $lock->getLockFile());
        $this->assertFalse($lock->exists());
        $this->assertNull($lock->getMetadata());
    }

    public function testAcquireLock(): void
    {
        $lockFile = $this->tempDir . '/test.lock';
        $lock = new Lock($lockFile);
        
        $this->assertTrue($lock->acquire('test-job', 300));
        $this->assertTrue($lock->exists());
        $this->assertTrue($lock->isHeldByCurrentProcess());
        
        // Check metadata
        $metadata = $lock->getMetadata();
        $this->assertInstanceOf(LockMetadata::class, $metadata);
        $this->assertEquals('test-job', $metadata->jobName);
        $this->assertEquals(300, $metadata->timeout);
        $this->assertEquals(getmypid(), $metadata->pid);
    }

    public function testAcquireExistingLock(): void
    {
        $lockFile = $this->tempDir . '/test.lock';
        $lock1 = new Lock($lockFile);
        $lock2 = new Lock($lockFile);
        
        $this->assertTrue($lock1->acquire('test-job', 300));
        $this->assertFalse($lock2->acquire('test-job', 300)); // Should fail - file exists
        
        $this->assertTrue($lock1->isHeldByCurrentProcess());
        $this->assertFalse($lock2->isHeldByCurrentProcess());
    }

    public function testReleaseLock(): void
    {
        $lockFile = $this->tempDir . '/test.lock';
        $lock = new Lock($lockFile);
        
        $this->assertTrue($lock->acquire('test-job', 300));
        $this->assertTrue($lock->exists());
        
        $this->assertTrue($lock->release());
        $this->assertFalse($lock->exists());
        $this->assertFalse($lock->isHeldByCurrentProcess());
    }

    public function testReleaseNonExistentLock(): void
    {
        $lockFile = $this->tempDir . '/nonexistent.lock';
        $lock = new Lock($lockFile);
        
        $this->assertTrue($lock->release()); // Should return true for non-existent lock
    }

    public function testIsStale(): void
    {
        $lockFile = $this->tempDir . '/test.lock';
        $lock = new Lock($lockFile);
        
        // Fresh lock should not be stale
        $lock->acquire('test-job', 300);
        $this->assertFalse($lock->isStale());
        
        // Manually create an expired lock
        $expiredMetadata = new LockMetadata(getmypid(), time() - 400, 300, 'test-host', 'test-job');
        file_put_contents($lockFile, $expiredMetadata->toJson());
        
        $staleLock = new Lock($lockFile);
        $this->assertTrue($staleLock->isStale());
    }

    public function testStaleProcessLock(): void
    {
        $lockFile = $this->tempDir . '/stale.lock';
        
        // Create lock with non-existent PID
        $staleMetadata = new LockMetadata(99999, time() - 50, 300, 'test-host', 'test-job');
        file_put_contents($lockFile, $staleMetadata->toJson());
        
        $lock = new Lock($lockFile);
        $this->assertTrue($lock->isStale());
    }

    public function testAcquireStalelock(): void
    {
        $lockFile = $this->tempDir . '/stale.lock';
        
        // Create a stale lock first
        $staleMetadata = new LockMetadata(99999, time() - 400, 300, 'test-host', 'old-job');
        file_put_contents($lockFile, $staleMetadata->toJson());
        
        $lock = new Lock($lockFile);
        $this->assertTrue($lock->isStale());
        
        // Should be able to acquire over stale lock
        $this->assertTrue($lock->acquire('new-job', 600));
        $this->assertTrue($lock->isHeldByCurrentProcess());
        
        $metadata = $lock->getMetadata();
        $this->assertEquals('new-job', $metadata->jobName);
        $this->assertEquals(600, $metadata->timeout);
    }

    public function testForceRelease(): void
    {
        $lockFile = $this->tempDir . '/force.lock';
        $lock = new Lock($lockFile);
        
        $lock->acquire('test-job', 300);
        $this->assertTrue($lock->exists());
        
        $this->assertTrue($lock->forceRelease());
        $this->assertFalse($lock->exists());
    }

    public function testForceReleaseNonExistentFile(): void
    {
        $lockFile = $this->tempDir . '/nonexistent.lock';
        $lock = new Lock($lockFile);
        
        $this->assertTrue($lock->forceRelease()); // Should return true
    }

    public function testGetInfo(): void
    {
        $lockFile = $this->tempDir . '/info.lock';
        $lock = new Lock($lockFile);
        
        // No lock exists
        $info = $lock->getInfo();
        $this->assertStringContainsString('No lock', $info);
        $this->assertStringContainsString($lockFile, $info);
        
        // Lock exists
        $lock->acquire('test-job', 300);
        $infoWithLock = $lock->getInfo();
        $this->assertStringContainsString('test-job', $infoWithLock);
    }

    public function testGetStatus(): void
    {
        $lockFile = $this->tempDir . '/status.lock';
        $lock = new Lock($lockFile);
        
        $this->assertEquals('not locked', $lock->getStatus());
        
        $lock->acquire('test-job', 300);
        $this->assertEquals('active', $lock->getStatus());
    }

    public function testGetAge(): void
    {
        $lockFile = $this->tempDir . '/age.lock';
        $lock = new Lock($lockFile);
        
        $this->assertNull($lock->getAge());
        
        $lock->acquire('test-job', 300);
        $age = $lock->getAge();
        $this->assertIsInt($age);
        $this->assertGreaterThanOrEqual(0, $age);
        $this->assertLessThan(5, $age); // Should be very recent
    }

    public function testGetRemainingTimeout(): void
    {
        $lockFile = $this->tempDir . '/timeout.lock';
        $lock = new Lock($lockFile);
        
        $this->assertNull($lock->getRemainingTimeout());
        
        $lock->acquire('test-job', 300);
        $remaining = $lock->getRemainingTimeout();
        $this->assertIsInt($remaining);
        $this->assertGreaterThan(295, $remaining); // Should be close to 300
        $this->assertLessThanOrEqual(300, $remaining);
    }

    public function testRefresh(): void
    {
        $lockFile = $this->tempDir . '/refresh.lock';
        $lock = new Lock($lockFile);
        
        // Cannot refresh non-held lock
        $this->assertFalse($lock->refresh(600));
        
        $lock->acquire('test-job', 300);
        $originalTimeout = $lock->getMetadata()->timeout;
        
        // Refresh with new timeout
        $this->assertTrue($lock->refresh(600));
        $newTimeout = $lock->getMetadata()->timeout;
        
        $this->assertEquals(300, $originalTimeout);
        $this->assertEquals(600, $newTimeout);
    }

    public function testConcurrentLockAccess(): void
    {
        $lockFile = $this->tempDir . '/concurrent.lock';
        $lock1 = new Lock($lockFile);
        $lock2 = new Lock($lockFile);
        
        // First lock should succeed
        $this->assertTrue($lock1->acquire('job1', 300));
        $this->assertTrue($lock1->isHeldByCurrentProcess());
        
        // Second lock should fail
        $this->assertFalse($lock2->acquire('job2', 300));
        $this->assertFalse($lock2->isHeldByCurrentProcess());
        
        // After releasing first, second should be able to acquire
        $this->assertTrue($lock1->release());
        $this->assertTrue($lock2->acquire('job2', 300));
        $this->assertTrue($lock2->isHeldByCurrentProcess());
    }

    public function testLockFileContents(): void
    {
        $lockFile = $this->tempDir . '/content.lock';
        $lock = new Lock($lockFile);
        
        $customMetadata = ['test' => 'data', 'environment' => 'testing'];
        $lock->acquire('content-job', 600, $customMetadata);
        
        $content = file_get_contents($lockFile);
        $data = json_decode($content, true);
        
        $this->assertEquals(getmypid(), $data['pid']);
        $this->assertEquals('content-job', $data['job_name']);
        $this->assertEquals(600, $data['timeout']);
        $this->assertEquals($customMetadata, $data['metadata']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('expires_at', $data);
    }

    public function testInvalidLockFileHandling(): void
    {
        $lockFile = $this->tempDir . '/invalid.lock';
        
        // Create invalid lock file
        file_put_contents($lockFile, 'invalid json content');
        
        $lock = new Lock($lockFile);
        $this->assertTrue($lock->exists());
        $this->assertNull($lock->getMetadata());
        $this->assertEquals('invalid lock', $lock->getStatus());
        
        // Should be able to acquire over invalid lock
        $this->assertTrue($lock->acquire('new-job', 300));
    }

    public function testDirectoryCreation(): void
    {
        $subDir = $this->tempDir . '/nested/deep/structure';
        $lockFile = $subDir . '/test.lock';
        $lock = new Lock($lockFile);
        
        $this->assertFalse(is_dir($subDir));
        $this->assertTrue($lock->acquire('test-job', 300));
        $this->assertTrue(is_dir($subDir));
        $this->assertTrue(file_exists($lockFile));
    }

    public function testWithCustomMetadata(): void
    {
        $lockFile = $this->tempDir . '/metadata.lock';
        $lock = new Lock($lockFile);
        
        $metadata = [
            'user' => 'testuser',
            'environment' => 'test',
            'version' => '1.0',
            'extra_data' => ['nested' => 'value']
        ];
        
        $this->assertTrue($lock->acquire('metadata-job', 300, $metadata));
        
        $lockMetadata = $lock->getMetadata();
        $this->assertEquals($metadata, $lockMetadata->metadata);
    }

    public function testDestructor(): void
    {
        $lockFile = $this->tempDir . '/destructor.lock';
        
        {
            $lock = new Lock($lockFile);
            $lock->acquire('test-job', 300);
            $this->assertTrue($lock->exists());
        } // Lock goes out of scope and destructor should be called
        
        // Small delay to ensure destructor runs
        usleep(1000);
        
        // Lock should be released by destructor
        $this->assertFalse(file_exists($lockFile));
    }

    public function testAtomicLockCreation(): void
    {
        $lockFile = $this->tempDir . '/atomic.lock';
        $lock = new Lock($lockFile);
        
        $this->assertTrue($lock->acquire('atomic-job', 300));
        
        // Verify no temporary files are left behind
        $tempFiles = glob($this->tempDir . '/*.tmp.*');
        $this->assertEmpty($tempFiles);
        
        // Verify lock file exists and is valid
        $this->assertTrue($lock->exists());
        $this->assertInstanceOf(LockMetadata::class, $lock->getMetadata());
    }
}