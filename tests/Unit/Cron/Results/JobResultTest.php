<?php

declare(strict_types=1);

namespace Tests\Unit\Cron\Results;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Cron\Results\JobResult;

/**
 * Test suite for JobResult
 */
class JobResultTest extends TestCase
{
    public function testBasicJobResult(): void
    {
        $result = new JobResult('test-job', true, 'Job completed successfully');
        
        $this->assertEquals('test-job', $result->getJobName());
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Job completed successfully', $result->getMessage());
        $this->assertFalse($result->isSkipped());
        $this->assertFalse($result->hasException());
    }

    public function testFailedJobResult(): void
    {
        $result = new JobResult('test-job', false, 'Job failed');
        
        $this->assertEquals('test-job', $result->getJobName());
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Job failed', $result->getMessage());
    }

    public function testFluentSetters(): void
    {
        $result = new JobResult('test-job');
        
        $result->setSuccess(true)
               ->setMessage('Updated message')
               ->setSkipped(true);
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Updated message', $result->getMessage());
        $this->assertTrue($result->isSkipped());
    }

    public function testTimingMethods(): void
    {
        $result = new JobResult('test-job');
        
        $startTime = microtime(true);
        $endTime = $startTime + 1.5;
        
        $result->setStartTime($startTime)
               ->setEndTime($endTime);
        
        $this->assertEquals($startTime, $result->getStartTime());
        $this->assertEquals($endTime, $result->getEndTime());
        $this->assertEquals(1.5, $result->getDuration());
    }

    public function testMemoryMethods(): void
    {
        $result = new JobResult('test-job');
        
        $startMemory = 1024 * 1024; // 1MB
        $endMemory = 2048 * 1024;   // 2MB
        
        $result->setStartMemory($startMemory)
               ->setEndMemory($endMemory);
        
        $this->assertEquals($startMemory, $result->getStartMemory());
        $this->assertEquals($endMemory, $result->getEndMemory());
        $this->assertEquals(1024 * 1024, $result->getMemoryUsed());
        $this->assertEquals(1.0, $result->getMemoryUsedMB());
    }

    public function testExceptionHandling(): void
    {
        $result = new JobResult('test-job');
        $exception = new \Exception('Test exception');
        
        $result->setException($exception);
        
        $this->assertTrue($result->hasException());
        $this->assertSame($exception, $result->getException());
    }

    public function testMetadata(): void
    {
        $result = new JobResult('test-job');
        
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $result->setMetadata($metadata);
        
        $this->assertEquals($metadata, $result->getMetadata());
        
        $result->addMetadata('key3', 'value3');
        $this->assertEquals('value3', $result->getMetadataValue('key3'));
        $this->assertEquals('default', $result->getMetadataValue('nonexistent', 'default'));
    }

    public function testExitCode(): void
    {
        $result = new JobResult('test-job');
        
        $result->setExitCode(1);
        $this->assertEquals(1, $result->getExitCode());
    }

    public function testOutput(): void
    {
        $result = new JobResult('test-job');
        
        $output = 'Job output content';
        $result->setOutput($output);
        $this->assertEquals($output, $result->getOutput());
    }

    public function testFormattedDuration(): void
    {
        $result = new JobResult('test-job');
        
        // No duration set
        $this->assertEquals('N/A', $result->getFormattedDuration());
        
        // Milliseconds
        $result->setDuration(0.5);
        $this->assertEquals('500ms', $result->getFormattedDuration());
        
        // Seconds
        $result->setDuration(30.5);
        $this->assertEquals('30.5s', $result->getFormattedDuration());
        
        // Minutes and seconds
        $result->setDuration(125.75);
        $this->assertEquals('2m 6s', $result->getFormattedDuration()); // 125.75 seconds = 2m 5.75s, rounds to 6s
    }

    public function testFormattedMemoryUsed(): void
    {
        $result = new JobResult('test-job');
        
        // No memory usage set
        $this->assertEquals('N/A', $result->getFormattedMemoryUsed());
        
        // Kilobytes
        $result->setMemoryUsed(512);
        $this->assertEquals('1KB', $result->getFormattedMemoryUsed()); // Implementation rounds up
        
        // Megabytes
        $result->setMemoryUsed(2 * 1024 * 1024);
        $this->assertEquals('2MB', $result->getFormattedMemoryUsed());
    }

    public function testSummary(): void
    {
        $result = new JobResult('test-job', true, 'Completed');
        $result->setDuration(1.5)
               ->setMemoryUsed(1024 * 1024);
        
        $summary = $result->getSummary();
        $this->assertStringContainsString('[SUCCESS]', $summary);
        $this->assertStringContainsString('test-job', $summary);
        $this->assertStringContainsString('Completed', $summary);
        $this->assertStringContainsString('1.5s', $summary);
        $this->assertStringContainsString('1MB', $summary);
    }

    public function testSkippedSummary(): void
    {
        $result = new JobResult('test-job', false, 'Skipped');
        $result->setSkipped(true);
        
        $summary = $result->getSummary();
        $this->assertStringContainsString('[SKIPPED]', $summary);
    }

    public function testFailedSummary(): void
    {
        $result = new JobResult('test-job', false, 'Failed');
        
        $summary = $result->getSummary();
        $this->assertStringContainsString('[FAILED]', $summary);
    }

    public function testToArray(): void
    {
        $result = new JobResult('test-job', true, 'Completed');
        $result->setDuration(1.5)
               ->setMemoryUsed(1024 * 1024)
               ->setExitCode(0)
               ->setOutput('Test output')
               ->addMetadata('test', 'value');
        
        $array = $result->toArray();
        
        $this->assertEquals('test-job', $array['job_name']);
        $this->assertTrue($array['success']);
        $this->assertEquals('Completed', $array['message']);
        $this->assertEquals(1.5, $array['duration']);
        $this->assertEquals('1.5s', $array['formatted_duration']);
        $this->assertEquals(1.0, $array['memory_used_mb']);
        $this->assertEquals('1MB', $array['formatted_memory']);
        $this->assertEquals(0, $array['exit_code']);
        $this->assertEquals('Test output', $array['output']);
        $this->assertEquals(['test' => 'value'], $array['metadata']);
    }

    public function testToArrayWithException(): void
    {
        $result = new JobResult('test-job', false, 'Failed');
        $exception = new \Exception('Test exception', 123);
        $result->setException($exception);
        
        $array = $result->toArray();
        
        $this->assertArrayHasKey('exception', $array);
        $this->assertEquals(\Exception::class, $array['exception']['class']);
        $this->assertEquals('Test exception', $array['exception']['message']);
        $this->assertEquals(123, $array['exception']['code']);
    }

    public function testToJson(): void
    {
        $result = new JobResult('test-job', true, 'Completed');
        $result->setDuration(1.5);
        
        $json = $result->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertEquals('test-job', $decoded['job_name']);
        $this->assertTrue($decoded['success']);
        $this->assertEquals(1.5, $decoded['duration']);
    }

    public function testStaticFactoryMethods(): void
    {
        // Success
        $result = JobResult::success('test-job', 'Custom success message');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('test-job', $result->getJobName());
        $this->assertEquals('Custom success message', $result->getMessage());
        
        // Failure
        $exception = new \Exception('Test error');
        $result = JobResult::failure('test-job', 'Custom failure message', $exception);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Custom failure message', $result->getMessage());
        $this->assertSame($exception, $result->getException());
        
        // Skipped
        $result = JobResult::skipped('test-job', 'Custom skip reason');
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isSkipped());
        $this->assertEquals('Custom skip reason', $result->getMessage());
    }

    public function testManualDurationSetting(): void
    {
        $result = new JobResult('test-job');
        
        // Set duration manually (should not be overridden by setEndTime)
        $result->setDuration(5.0);
        $this->assertEquals(5.0, $result->getDuration());
        
        // Setting end time should still work but not override manual duration
        $result->setStartTime(microtime(true))
               ->setEndTime(microtime(true) + 1.0);
        
        $this->assertEquals(5.0, $result->getDuration()); // Manual setting preserved
    }

    public function testManualMemoryUsedSetting(): void
    {
        $result = new JobResult('test-job');
        
        // Set memory used manually
        $result->setMemoryUsed(512 * 1024);
        $this->assertEquals(512 * 1024, $result->getMemoryUsed());
        
        // Setting end memory should still work but not override manual setting
        $result->setStartMemory(1024)
               ->setEndMemory(2048);
        
        $this->assertEquals(512 * 1024, $result->getMemoryUsed()); // Manual setting preserved
    }
}