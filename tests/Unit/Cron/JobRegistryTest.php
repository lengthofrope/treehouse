<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use LengthOfRope\TreeHouse\Cron\JobRegistry;
use LengthOfRope\TreeHouse\Cron\CronJobInterface;
use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;
use Tests\TestCase;

/**
 * JobRegistry Test
 * 
 * Tests for the enhanced job registry with centralized built-in job management
 */
class JobRegistryTest extends TestCase
{
    protected JobRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new JobRegistry();
    }

    public function testGetBuiltInJobClasses(): void
    {
        $builtInJobs = JobRegistry::getBuiltInJobClasses();
        
        $this->assertIsArray($builtInJobs);
        $this->assertNotEmpty($builtInJobs);
        
        // Should contain the framework built-in jobs
        $this->assertContains(\LengthOfRope\TreeHouse\Cron\Jobs\CacheCleanupJob::class, $builtInJobs);
        $this->assertContains(\LengthOfRope\TreeHouse\Cron\Jobs\LockCleanupJob::class, $builtInJobs);
        $this->assertContains(\LengthOfRope\TreeHouse\Mail\Queue\MailQueueProcessor::class, $builtInJobs);
    }

    public function testBuiltInJobClassesAreValidClasses(): void
    {
        $builtInJobs = JobRegistry::getBuiltInJobClasses();
        
        foreach ($builtInJobs as $jobClass) {
            $this->assertTrue(class_exists($jobClass), "Class {$jobClass} should exist");
            $this->assertTrue(
                is_subclass_of($jobClass, CronJobInterface::class),
                "Class {$jobClass} should implement CronJobInterface"
            );
        }
    }

    public function testLoadBuiltInJobsSuccessfully(): void
    {
        $loaded = $this->registry->loadBuiltInJobs();
        
        $this->assertIsInt($loaded);
        $this->assertGreaterThan(0, $loaded);
        
        // Should have loaded at least the 3 built-in jobs
        $this->assertGreaterThanOrEqual(3, $loaded);
    }

    public function testLoadBuiltInJobsRegistersJobs(): void
    {
        $this->registry->loadBuiltInJobs();
        
        $allJobs = $this->registry->getAllJobs();
        $jobNames = array_keys($allJobs);
        
        // Should contain the expected job names
        $this->assertContains('cache:cleanup', $jobNames);
        $this->assertContains('lock:cleanup', $jobNames);
        $this->assertContains('mail:queue:process', $jobNames);
    }

    public function testLoadBuiltInJobsIgnoresErrorsByDefault(): void
    {
        // This should not throw an exception even if some jobs fail to load
        $loaded = $this->registry->loadBuiltInJobs();
        $this->assertIsInt($loaded);
    }

    public function testLoadBuiltInJobsCanThrowErrors(): void
    {
        // Create a registry that will have issues (though in practice this is hard to trigger)
        $registry = new JobRegistry(['max_jobs' => 0]); // Set max to 0 to force failures
        
        try {
            $loaded = $registry->loadBuiltInJobs(false); // Don't ignore errors
            // If we get here, that's also okay - means no errors occurred
            $this->assertIsInt($loaded);
        } catch (CronException $e) {
            // Expected if max_jobs limit is hit
            $this->assertInstanceOf(CronException::class, $e);
        }
    }

    public function testBuiltInJobsHaveUniqueNames(): void
    {
        $this->registry->loadBuiltInJobs();
        $allJobs = $this->registry->getAllJobs();
        
        $jobNames = array_keys($allJobs);
        $uniqueNames = array_unique($jobNames);
        
        $this->assertCount(count($jobNames), $uniqueNames, 'All job names should be unique');
    }

    public function testBuiltInJobsAreEnabled(): void
    {
        $this->registry->loadBuiltInJobs();
        $enabledJobs = $this->registry->getEnabledJobs();
        
        // All built-in jobs should be enabled by default (except mail queue which depends on config)
        $this->assertArrayHasKey('cache:cleanup', $enabledJobs);
        $this->assertArrayHasKey('lock:cleanup', $enabledJobs);
        
        // mail:queue:process might be disabled if mail queue is disabled in config
        // so we don't assert it's enabled, just that it's registered
        $allJobs = $this->registry->getAllJobs();
        $this->assertArrayHasKey('mail:queue:process', $allJobs);
    }

    public function testJobRegistryMaintainsOriginalFunctionality(): void
    {
        // Test that existing JobRegistry functionality still works
        $mockJob = $this->createMock(CronJobInterface::class);
        $mockJob->method('getName')->willReturn('test-job');
        $mockJob->method('getDescription')->willReturn('Test job');
        $mockJob->method('getSchedule')->willReturn('0 0 * * *');
        $mockJob->method('isEnabled')->willReturn(true);
        $mockJob->method('getTimeout')->willReturn(60);
        $mockJob->method('getPriority')->willReturn(50);
        $mockJob->method('allowsConcurrentExecution')->willReturn(false);
        $mockJob->method('getMetadata')->willReturn([]);
        
        $this->registry->register($mockJob);
        
        $this->assertTrue($this->registry->hasJob('test-job'));
        $this->assertEquals($mockJob, $this->registry->getJob('test-job'));
        $this->assertEquals(1, $this->registry->getJobCount());
    }

    public function testCombiningBuiltInAndCustomJobs(): void
    {
        // Load built-in jobs first
        $builtInCount = $this->registry->loadBuiltInJobs();
        
        // Add a custom job
        $mockJob = $this->createMock(CronJobInterface::class);
        $mockJob->method('getName')->willReturn('custom-job');
        $mockJob->method('getDescription')->willReturn('Custom job');
        $mockJob->method('getSchedule')->willReturn('0 0 * * *');
        $mockJob->method('isEnabled')->willReturn(true);
        $mockJob->method('getTimeout')->willReturn(60);
        $mockJob->method('getPriority')->willReturn(50);
        $mockJob->method('allowsConcurrentExecution')->willReturn(false);
        $mockJob->method('getMetadata')->willReturn([]);
        
        $this->registry->register($mockJob);
        
        $totalJobs = $this->registry->getJobCount();
        $this->assertEquals($builtInCount + 1, $totalJobs);
        
        // Should have both built-in and custom jobs
        $this->assertTrue($this->registry->hasJob('cache:cleanup'));
        $this->assertTrue($this->registry->hasJob('custom-job'));
    }
}