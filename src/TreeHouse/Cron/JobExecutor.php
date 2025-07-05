<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron;

use LengthOfRope\TreeHouse\Cron\Locking\LockManager;
use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;
use LengthOfRope\TreeHouse\Cron\Results\JobResult;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

/**
 * Job Executor
 * 
 * Executes individual cron jobs with timeout handling, locking, and result tracking.
 * Provides comprehensive job execution management with safety features.
 * 
 * @package LengthOfRope\TreeHouse\Cron
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JobExecutor
{
    /**
     * Lock manager instance
     */
    private LockManager $lockManager;

    /**
     * Logger instance
     */
    private ?ErrorLogger $logger;

    /**
     * Execution configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Currently executing jobs
     *
     * @var array<string, array<string, mixed>>
     */
    private array $executingJobs = [];

    /**
     * Create a new job executor
     *
     * @param LockManager $lockManager Lock manager instance
     * @param ErrorLogger|null $logger Logger instance
     * @param array<string, mixed> $config Executor configuration
     */
    public function __construct(
        LockManager $lockManager,
        ?ErrorLogger $logger = null,
        array $config = []
    ) {
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        
        $this->config = array_merge([
            'default_timeout' => 300,
            'memory_limit' => 512, // MB
            'max_concurrent_jobs' => 3,
            'log_execution' => true,
            'detailed_logging' => false,
        ], $config);
    }

    /**
     * Execute a job
     *
     * @param CronJobInterface $job Job to execute
     * @param bool $force Force execution even if job is disabled or locked
     * @return JobResult Execution result
     */
    public function execute(CronJobInterface $job, bool $force = false): JobResult
    {
        $jobName = $job->getName();
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Create initial result
        $result = new JobResult($jobName, false, 'Job execution started');
        $result->setStartTime($startTime)
               ->setStartMemory($startMemory);

        try {
            // Pre-execution checks
            if (!$this->preExecutionCheck($job, $force)) {
                return $result->setSuccess(false)
                             ->setMessage('Pre-execution checks failed')
                             ->setEndTime(microtime(true))
                             ->setEndMemory(memory_get_usage(true));
            }

            // Acquire job lock
            if (!$this->acquireJobLock($job)) {
                return $result->setSuccess(false)
                             ->setMessage('Failed to acquire job lock')
                             ->setSkipped(true)
                             ->setEndTime(microtime(true))
                             ->setEndMemory(memory_get_usage(true));
            }

            // Execute the job
            $result = $this->executeJob($job, $result);

        } catch (\Throwable $e) {
            $result->setSuccess(false)
                   ->setMessage("Job execution failed: {$e->getMessage()}")
                   ->setException($e);
                   
            $this->logError($job, $e, $result);
        } finally {
            // Always clean up
            $this->cleanup($job, $result);
        }

        return $result;
    }

    /**
     * Execute multiple jobs
     *
     * @param array<CronJobInterface> $jobs Jobs to execute
     * @param bool $respectConcurrencyLimit Whether to respect max concurrent jobs limit
     * @return array<string, JobResult> Execution results
     */
    public function executeMany(array $jobs, bool $respectConcurrencyLimit = true): array
    {
        $results = [];
        $concurrentCount = 0;
        
        foreach ($jobs as $job) {
            $jobName = $job->getName();
            
            // Check concurrency limit
            if ($respectConcurrencyLimit && $concurrentCount >= $this->config['max_concurrent_jobs']) {
                $result = new JobResult($jobName, false, 'Concurrency limit reached');
                $result->setSkipped(true);
                $results[$jobName] = $result;
                continue;
            }
            
            // Check if job allows concurrent execution
            if (!$job->allowsConcurrentExecution() && $this->isJobExecuting($jobName)) {
                $result = new JobResult($jobName, false, 'Job already executing and does not allow concurrency');
                $result->setSkipped(true);
                $results[$jobName] = $result;
                continue;
            }
            
            $results[$jobName] = $this->execute($job);
            
            if ($results[$jobName]->isSuccess()) {
                $concurrentCount++;
            }
        }
        
        return $results;
    }

    /**
     * Check if a job is currently executing
     *
     * @param string $jobName Job name
     * @return bool True if job is executing
     */
    public function isJobExecuting(string $jobName): bool
    {
        return isset($this->executingJobs[$jobName]);
    }

    /**
     * Get currently executing jobs
     *
     * @return array<string, array<string, mixed>> Executing jobs info
     */
    public function getExecutingJobs(): array
    {
        return $this->executingJobs;
    }

    /**
     * Get executor statistics
     *
     * @return array<string, mixed> Executor statistics
     */
    public function getStatistics(): array
    {
        return [
            'config' => $this->config,
            'executing_jobs' => count($this->executingJobs),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024, // MB
            'peak_memory' => memory_get_peak_usage(true) / 1024 / 1024, // MB
        ];
    }

    /**
     * Perform pre-execution checks
     *
     * @param CronJobInterface $job Job to check
     * @param bool $force Force execution
     * @return bool True if checks pass
     */
    private function preExecutionCheck(CronJobInterface $job, bool $force): bool
    {
        $jobName = $job->getName();
        
        // Check if job is enabled (unless forced)
        if (!$force && !$job->isEnabled()) {
            $this->log('info', "Job '$jobName' is disabled, skipping", ['job' => $jobName]);
            return false;
        }
        
        // Check memory limit
        $currentMemory = memory_get_usage(true) / 1024 / 1024; // MB
        if ($currentMemory > $this->config['memory_limit']) {
            $this->log('warning', "Memory limit exceeded ({$currentMemory}MB), skipping job '$jobName'", [
                'job' => $jobName,
                'memory_usage' => $currentMemory,
                'memory_limit' => $this->config['memory_limit']
            ]);
            return false;
        }
        
        // Check if already executing (for non-concurrent jobs)
        if (!$job->allowsConcurrentExecution() && $this->isJobExecuting($jobName)) {
            $this->log('info', "Job '$jobName' is already executing and does not allow concurrency", [
                'job' => $jobName
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Acquire job lock
     *
     * @param CronJobInterface $job Job to lock
     * @return bool True if lock acquired
     */
    private function acquireJobLock(CronJobInterface $job): bool
    {
        $jobName = $job->getName();
        
        // Skip locking for jobs that allow concurrent execution
        if ($job->allowsConcurrentExecution()) {
            return true;
        }
        
        if (!$this->lockManager->acquireJobLock($jobName, $job->getTimeout())) {
            $this->log('info', "Could not acquire lock for job '$jobName'", ['job' => $jobName]);
            return false;
        }
        
        return true;
    }

    /**
     * Execute the job with timeout handling
     *
     * @param CronJobInterface $job Job to execute
     * @param JobResult $result Result object to update
     * @return JobResult Updated result
     */
    private function executeJob(CronJobInterface $job, JobResult $result): JobResult
    {
        $jobName = $job->getName();
        $timeout = $job->getTimeout();
        
        // Track executing job
        $this->executingJobs[$jobName] = [
            'start_time' => microtime(true),
            'timeout' => $timeout,
            'pid' => getmypid(),
        ];
        
        // Set up timeout handling
        $originalTimeLimit = ini_get('max_execution_time');
        if ($timeout > 0) {
            set_time_limit($timeout);
        }
        
        try {
            $this->log('info', "Starting job execution: $jobName", [
                'job' => $jobName,
                'timeout' => $timeout,
                'schedule' => $job->getSchedule()
            ]);
            
            // Execute the job
            $success = $job->execute();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $duration = $endTime - $result->getStartTime();
            
            $result->setSuccess($success)
                   ->setEndTime($endTime)
                   ->setEndMemory($endMemory)
                   ->setDuration($duration);
            
            if ($success) {
                $result->setMessage('Job completed successfully');
                $this->log('info', "Job '$jobName' completed successfully", [
                    'job' => $jobName,
                    'duration' => round($duration, 3),
                    'memory_used' => round(($endMemory - $result->getStartMemory()) / 1024 / 1024, 2)
                ]);
            } else {
                $result->setMessage('Job execution returned false');
                $this->log('warning', "Job '$jobName' execution returned false", [
                    'job' => $jobName,
                    'duration' => round($duration, 3)
                ]);
            }
            
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $duration = $endTime - $result->getStartTime();
            
            $result->setSuccess(false)
                   ->setMessage("Job execution failed: {$e->getMessage()}")
                   ->setException($e)
                   ->setEndTime($endTime)
                   ->setEndMemory(memory_get_usage(true))
                   ->setDuration($duration);
                   
            throw $e;
        } finally {
            // Restore original time limit
            if ($originalTimeLimit !== false) {
                set_time_limit((int)$originalTimeLimit);
            }
            
            // Remove from executing jobs
            unset($this->executingJobs[$jobName]);
        }
        
        return $result;
    }

    /**
     * Clean up after job execution
     *
     * @param CronJobInterface $job Executed job
     * @param JobResult $result Execution result
     */
    private function cleanup(CronJobInterface $job, JobResult $result): void
    {
        $jobName = $job->getName();
        
        // Release job lock
        if (!$job->allowsConcurrentExecution()) {
            $this->lockManager->releaseJobLock($jobName);
        }
        
        // Remove from executing jobs if still there
        unset($this->executingJobs[$jobName]);
        
        // Log final result if detailed logging is enabled
        if ($this->config['detailed_logging']) {
            $this->log('debug', "Job '$jobName' cleanup completed", [
                'job' => $jobName,
                'success' => $result->isSuccess(),
                'duration' => $result->getDuration(),
                'memory_used' => $result->getMemoryUsed()
            ]);
        }
    }

    /**
     * Log error with exception details
     *
     * @param CronJobInterface $job Job that failed
     * @param \Throwable $exception Exception that occurred
     * @param JobResult $result Current result
     */
    private function logError(CronJobInterface $job, \Throwable $exception, JobResult $result): void
    {
        $context = [
            'job' => $job->getName(),
            'job_class' => get_class($job),
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'duration' => $result->getDuration(),
        ];
        
        if ($this->config['detailed_logging']) {
            $context['trace'] = $exception->getTraceAsString();
        }
        
        $this->log('error', "Job '{$job->getName()}' failed with exception", $context);
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Message
     * @param array<string, mixed> $context Context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->config['log_execution']) {
            return;
        }
        
        $context = array_merge([
            'component' => 'cron_executor',
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'pid' => getmypid(),
        ], $context);
        
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        } else {
            // Fallback to error_log
            $logMessage = sprintf(
                '[%s] [CRON_EXECUTOR] %s %s',
                strtoupper($level),
                $message,
                json_encode($context, JSON_UNESCAPED_SLASHES)
            );
            error_log($logMessage);
        }
    }
}