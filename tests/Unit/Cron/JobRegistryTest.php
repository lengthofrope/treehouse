<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Cron\JobRegistry;
use LengthOfRope\TreeHouse\Cron\CronJob;
use LengthOfRope\TreeHouse\Cron\CronJobInterface;
use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;

/**
 * Test suite for JobRegistry
 */
class JobRegistryTest extends TestCase
{
    private JobRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new JobRegistry();
    }

    public function testCreateRegistry(): void
    {
        $this->assertInstanceOf(JobRegistry::class, $this->registry);
        $this->assertEquals(0, $this->registry->getJobCount());
        $this->assertEmpty($this->registry->getAllJobs());
    }

    public function testRegisterJob(): void
    {
        $job = new TestCronJob();
        
        $this->registry->register($job);
        
        $this->assertEquals(1, $this->registry->getJobCount());
        $this->assertTrue($this->registry->hasJob('test-job'));
        $this->assertSame($job, $this->registry->getJob('test-job'));
    }

    public function testRegisterJobClass(): void
    {
        $this->registry->registerClass(TestCronJob::class);
        
        $this->assertEquals(1, $this->registry->getJobCount());
        $this->assertTrue($this->registry->hasJob('test-job'));
        
        $job = $this->registry->getJob('test-job');
        $this->assertInstanceOf(TestCronJob::class, $job);
    }

    public function testRegisterMany(): void
    {
        $jobs = [
            new TestCronJob(),
            new AnotherTestCronJob(),
            TestThirdCronJob::class
        ];
        
        $this->registry->registerMany($jobs);
        
        $this->assertEquals(3, $this->registry->getJobCount());
        $this->assertTrue($this->registry->hasJob('test-job'));
        $this->assertTrue($this->registry->hasJob('another-test-job'));
        $this->assertTrue($this->registry->hasJob('third-test-job'));
    }

    public function testRegisterDuplicateJob(): void
    {
        $job1 = new TestCronJob();
        $job2 = new TestCronJob();
        
        $this->registry->register($job1);
        
        $this->expectException(CronException::class);
        $this->expectExceptionMessage('already registered');
        
        $this->registry->register($job2);
    }

    public function testRegisterDuplicateJobWithOverride(): void
    {
        $job1 = new TestCronJob();
        $job2 = new TestCronJob();
        
        $this->registry->register($job1);
        $this->registry->register($job2, ['allow_override' => true]);
        
        $this->assertEquals(1, $this->registry->getJobCount());
        $this->assertSame($job2, $this->registry->getJob('test-job'));
    }

    public function testRegisterNonExistentClass(): void
    {
        $this->expectException(CronException::class);
        $this->expectExceptionMessage('Class does not exist');
        
        $this->registry->registerClass('NonExistentClass');
    }

    public function testRegisterInvalidClass(): void
    {
        $this->expectException(CronException::class);
        $this->expectExceptionMessage('must implement CronJobInterface');
        
        $this->registry->registerClass(\stdClass::class);
    }

    public function testUnregisterJob(): void
    {
        $job = new TestCronJob();
        $this->registry->register($job);
        
        $this->assertTrue($this->registry->hasJob('test-job'));
        $this->assertTrue($this->registry->unregister('test-job'));
        $this->assertFalse($this->registry->hasJob('test-job'));
        $this->assertEquals(0, $this->registry->getJobCount());
    }

    public function testUnregisterNonExistentJob(): void
    {
        $this->assertFalse($this->registry->unregister('non-existent'));
    }

    public function testGetEnabledJobs(): void
    {
        $enabledJob = new TestCronJob();
        $disabledJob = new DisabledTestCronJob();
        
        $this->registry->register($enabledJob);
        $this->registry->register($disabledJob);
        
        $enabledJobs = $this->registry->getEnabledJobs();
        
        $this->assertCount(1, $enabledJobs);
        $this->assertArrayHasKey('test-job', $enabledJobs);
        $this->assertArrayNotHasKey('disabled-job', $enabledJobs);
    }

    public function testGetJobsByPriority(): void
    {
        $highPriorityJob = new HighPriorityTestCronJob();
        $lowPriorityJob = new LowPriorityTestCronJob();
        $normalJob = new TestCronJob();
        
        $this->registry->register($lowPriorityJob);
        $this->registry->register($normalJob);
        $this->registry->register($highPriorityJob);
        
        $sortedJobs = $this->registry->getJobsByPriority();
        $jobNames = array_keys($sortedJobs);
        
        // Should be sorted by priority (lower number = higher priority)
        $this->assertEquals(['high-priority-job', 'test-job', 'low-priority-job'], $jobNames);
    }

    public function testGetDueJobs(): void
    {
        $alwaysJob = new AlwaysRunningJob();
        $neverJob = new NeverRunningJob();
        
        $this->registry->register($alwaysJob);
        $this->registry->register($neverJob);
        
        $dueJobs = $this->registry->getDueJobs();
        
        $this->assertCount(1, $dueJobs);
        $this->assertArrayHasKey('always-job', $dueJobs);
        $this->assertArrayNotHasKey('never-job', $dueJobs);
    }

    public function testGetJobNames(): void
    {
        $this->registry->register(new TestCronJob());
        $this->registry->register(new AnotherTestCronJob());
        
        $allNames = $this->registry->getJobNames();
        $enabledNames = $this->registry->getJobNames(true);
        
        $this->assertEquals(['test-job', 'another-test-job'], $allNames);
        $this->assertEquals(['test-job', 'another-test-job'], $enabledNames);
    }

    public function testGetJobMetadata(): void
    {
        $job = new TestCronJob();
        $this->registry->register($job);
        
        $metadata = $this->registry->getJobMetadata('test-job');
        
        $this->assertIsArray($metadata);
        $this->assertEquals('test-job', $metadata['name']);
        $this->assertEquals('Test job for testing', $metadata['description']);
        $this->assertEquals('* * * * *', $metadata['schedule']);
        $this->assertTrue($metadata['enabled']);
        $this->assertEquals(50, $metadata['priority']);
        $this->assertEquals(300, $metadata['timeout']);
        $this->assertArrayHasKey('next_run', $metadata);
        $this->assertArrayHasKey('upcoming_runs', $metadata);
    }

    public function testGetSummary(): void
    {
        $enabledJob = new TestCronJob();
        $disabledJob = new DisabledTestCronJob();
        $dueJob = new AlwaysRunningJob();
        
        $this->registry->register($enabledJob);
        $this->registry->register($disabledJob);
        $this->registry->register($dueJob);
        
        $summary = $this->registry->getSummary();
        
        $this->assertEquals(3, $summary['total_jobs']);
        $this->assertEquals(2, $summary['enabled_jobs']);
        $this->assertEquals(1, $summary['disabled_jobs']);
        $this->assertEquals(2, $summary['due_jobs']); // enabled jobs that are due
        $this->assertIsArray($summary['job_names']);
        $this->assertCount(3, $summary['job_names']);
    }

    public function testClear(): void
    {
        $this->registry->register(new TestCronJob());
        $this->registry->register(new AnotherTestCronJob());
        
        $this->assertEquals(2, $this->registry->getJobCount());
        
        $this->registry->clear();
        
        $this->assertEquals(0, $this->registry->getJobCount());
        $this->assertEmpty($this->registry->getAllJobs());
    }

    public function testValidation(): void
    {
        $invalidJob = new InvalidTestCronJob();
        
        $this->expectException(CronException::class);
        
        $this->registry->register($invalidJob);
    }

    public function testJobLimitEnforcement(): void
    {
        $registry = new JobRegistry(['max_jobs' => 2]);
        
        $registry->register(new TestCronJob());
        $registry->register(new AnotherTestCronJob());
        
        $this->expectException(CronException::class);
        $this->expectExceptionMessage('Maximum number of jobs');
        
        $registry->register(new TestThirdCronJob());
    }

    public function testWithConfiguration(): void
    {
        $config = [
            'validate_jobs' => false,
            'cache_metadata' => false,
            'max_jobs' => 10
        ];
        
        $registry = new JobRegistry($config);
        
        // Should allow invalid job when validation is disabled
        $invalidJob = new InvalidTestCronJob();
        $registry->register($invalidJob);
        
        $this->assertTrue($registry->hasJob('invalid-job'));
    }
}

// Test job classes

class TestCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('test-job')
            ->setDescription('Test job for testing')
            ->setSchedule('* * * * *')
            ->setPriority(50)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class AnotherTestCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('another-test-job')
            ->setDescription('Another test job')
            ->setSchedule('0 * * * *')
            ->setPriority(50)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class TestThirdCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('third-test-job')
            ->setDescription('Third test job')
            ->setSchedule('0 0 * * *')
            ->setPriority(50)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class DisabledTestCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('disabled-job')
            ->setDescription('Disabled test job')
            ->setSchedule('* * * * *')
            ->setPriority(50)
            ->setTimeout(300)
            ->setEnabled(false);
    }

    public function handle(): bool
    {
        return true;
    }
}

class HighPriorityTestCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('high-priority-job')
            ->setDescription('High priority test job')
            ->setSchedule('* * * * *')
            ->setPriority(10)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class LowPriorityTestCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('low-priority-job')
            ->setDescription('Low priority test job')
            ->setSchedule('* * * * *')
            ->setPriority(90)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class AlwaysRunningJob extends CronJob
{
    public function __construct()
    {
        $this->setName('always-job')
            ->setDescription('Always running job')
            ->setSchedule('* * * * *')
            ->setPriority(50)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class NeverRunningJob extends CronJob
{
    public function __construct()
    {
        $this->setName('never-job')
            ->setDescription('Never running job')
            ->setSchedule('0 0 31 2 *') // February 31st (never)
            ->setPriority(50)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}

class InvalidTestCronJob extends CronJob
{
    public function __construct()
    {
        $this->setName('')  // Invalid: empty name
            ->setDescription('Invalid job')
            ->setSchedule('invalid cron expression')
            ->setPriority(50)
            ->setTimeout(300);
    }

    public function handle(): bool
    {
        return true;
    }
}