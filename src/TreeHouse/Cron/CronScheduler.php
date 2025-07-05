<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron;

use LengthOfRope\TreeHouse\Cron\Locking\LockManager;
use LengthOfRope\TreeHouse\Cron\Exceptions\CronException;
use LengthOfRope\TreeHouse\Cron\Results\JobResult;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;

/**
 * Cron Scheduler
 * 
 * Main orchestrator for the TreeHouse cron system. Manages job discovery,
 * scheduling, execution, and comprehensive locking to prevent concurrent runs.
 * 
 * @package LengthOfRope\TreeHouse\Cron
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CronScheduler
{
    /**
     * Job registry
     */
    private JobRegistry $jobRegistry;

    /**
     * Job executor
     */
    private JobExecutor $jobExecutor;

    /**
     * Lock manager
     */
    private LockManager $lockManager;

    /**
     * Logger instance
     */
    private ?ErrorLogger $logger;

    /**
     * Scheduler configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Execution results from the last run
     *
     * @var array<string, JobResult>
     */
    private array $lastResults = [];

    /**
     * Scheduler statistics
     *
     * @var array<string, mixed>
     */
    private array $statistics = [
        'total_runs' => 0,
        'successful_runs' => 0,
        'failed_runs' => 0,
        'skipped_runs' => 0,
        'last_run_time' => null,
        'last_run_duration' => null,
    ];

    /**
     * Create a new cron scheduler
     *
     * @param JobRegistry $jobRegistry Job registry
     * @param JobExecutor $jobExecutor Job executor
     * @param LockManager $lockManager Lock manager
     * @param ErrorLogger|null $logger Logger instance
     * @param array<string, mixed> $config Scheduler configuration
     */
    public function __construct(
        JobRegistry $jobRegistry,
        JobExecutor $jobExecutor,
        LockManager $lockManager,
        ?ErrorLogger $logger = null,
        array $config = []
    ) {
        $this->jobRegistry = $jobRegistry;
        $this->jobExecutor = $jobExecutor;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
        
        $this->config = array_merge([
            'global_timeout' => 300,
            'max_concurrent_jobs' => 3,
            'cleanup_stale_locks' => true,
            'skip_on_high_load' => true,
            'max_load_average' => 5.0,
            'max_memory_usage' => 512, // MB
            'log_execution' => true,
            'detailed_logging' => false,
            'timezone' => 'UTC',
        ], $config);
    }

    /**
     * Run the cron scheduler
     *
     * @param int|null $timestamp Unix timestamp to run for (null for current time)
     * @param bool $force Force execution even if globally locked
     * @return array<string, JobResult> Execution results
     * @throws CronException If scheduler execution fails
     */
    public function run(?int $timestamp = null, bool $force = false): array
    {
        $startTime = microtime(true);
        $timestamp = $timestamp ?? time();
        
        $this->log('info', 'Cron scheduler starting', [
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'force' => $force
        ]);

        try {
            // Attempt to acquire global lock
            if (!$this->acquireGlobalLock($force)) {
                $this->log('info', 'Cron scheduler already running, exiting gracefully');
                return [];
            }

            // Pre-execution checks
            if (!$this->preExecutionChecks()) {
                $this->log('warning', 'Pre-execution checks failed, skipping cron run');
                return [];
            }

            // Clean up stale locks if enabled
            if ($this->config['cleanup_stale_locks']) {
                $this->cleanupStaleLocks();
            }

            // Execute due jobs
            $results = $this->executeDueJobs($timestamp);

            // Update statistics
            $this->updateStatistics($results, microtime(true) - $startTime);

            $this->log('info', 'Cron scheduler completed', [
                'executed_jobs' => count($results),
                'successful_jobs' => count(array_filter($results, fn($r) => $r->isSuccess())),
                'failed_jobs' => count(array_filter($results, fn($r) => !$r->isSuccess() && !$r->isSkipped())),
                'skipped_jobs' => count(array_filter($results, fn($r) => $r->isSkipped())),
                'duration' => round(microtime(true) - $startTime, 3)
            ]);

            return $results;

        } catch (\Throwable $e) {
            $this->log('error', 'Cron scheduler failed', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            throw CronException::schedulerFailed(
                $e->getMessage(),
                $e,
                ['timestamp' => $timestamp, 'force' => $force]
            );
        } finally {
            // Always release global lock
            $this->releaseGlobalLock();
        }
    }

    /**
     * Get jobs that are due to run
     *
     * @param int|null $timestamp Unix timestamp (null for current time)
     * @return array<string, CronJobInterface> Due jobs
     */
    public function getDueJobs(?int $timestamp = null): array
    {
        return $this->jobRegistry->getDueJobs($timestamp);
    }

    /**
     * Get scheduler statistics
     *
     * @return array<string, mixed> Scheduler statistics
     */
    public function getStatistics(): array
    {
        return array_merge($this->statistics, [
            'job_registry_stats' => $this->jobRegistry->getSummary(),
            'executor_stats' => $this->jobExecutor->getStatistics(),
            'lock_manager_stats' => $this->lockManager->getStatistics(),
            'config' => $this->config,
        ]);
    }

    /**
     * Get last execution results
     *
     * @return array<string, JobResult> Last results
     */
    public function getLastResults(): array
    {
        return $this->lastResults;
    }

    /**
     * Check if scheduler is currently running
     *
     * @return bool True if scheduler is running
     */
    public function isRunning(): bool
    {
        return $this->lockManager->isGlobalLockActive();
    }

    /**
     * Force unlock all locks (emergency use only)
     *
     * @return int Number of locks released
     */
    public function forceUnlockAll(): int
    {
        $this->log('warning', 'Force unlocking all cron locks');
        return $this->lockManager->forceReleaseAllLocks();
    }

    /**
     * Get job registry
     */
    public function getJobRegistry(): JobRegistry
    {
        return $this->jobRegistry;
    }

    /**
     * Get job executor
     */
    public function getJobExecutor(): JobExecutor
    {
        return $this->jobExecutor;
    }

    /**
     * Get lock manager
     */
    public function getLockManager(): LockManager
    {
        return $this->lockManager;
    }

    /**
     * Acquire global scheduler lock
     *
     * @param bool $force Force acquisition even if already locked
     * @return bool True if lock was acquired
     */
    private function acquireGlobalLock(bool $force): bool
    {
        if ($force) {
            // Force release any existing global lock
            $this->lockManager->releaseGlobalLock();
        }

        return $this->lockManager->acquireGlobalLock($this->config['global_timeout']);
    }

    /**
     * Release global scheduler lock
     */
    private function releaseGlobalLock(): void
    {
        $this->lockManager->releaseGlobalLock();
    }

    /**
     * Perform pre-execution checks
     *
     * @return bool True if checks pass
     */
    private function preExecutionChecks(): bool
    {
        // Check system load if enabled
        if ($this->config['skip_on_high_load'] && $this->isSystemLoadTooHigh()) {
            $this->log('warning', 'System load too high, skipping cron execution');
            return false;
        }

        // Check memory usage
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        if ($memoryUsage > $this->config['max_memory_usage']) {
            $this->log('warning', "Memory usage too high ({$memoryUsage}MB), skipping cron execution", [
                'memory_usage' => $memoryUsage,
                'memory_limit' => $this->config['max_memory_usage']
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if system load is too high
     *
     * @return bool True if load is too high
     */
    private function isSystemLoadTooHigh(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows doesn't have load average, skip check
            return false;
        }

        $load = sys_getloadavg();
        if ($load === false) {
            // Cannot determine load, proceed with execution
            return false;
        }

        $currentLoad = $load[0]; // 1-minute load average
        return $currentLoad > $this->config['max_load_average'];
    }

    /**
     * Clean up stale locks
     */
    private function cleanupStaleLocks(): void
    {
        $cleaned = $this->lockManager->cleanupStaleLocks();
        
        if ($cleaned > 0) {
            $this->log('info', "Cleaned up {$cleaned} stale locks");
        }
    }

    /**
     * Execute jobs that are due to run
     *
     * @param int $timestamp Timestamp to check for due jobs
     * @return array<string, JobResult> Execution results
     */
    private function executeDueJobs(int $timestamp): array
    {
        $dueJobs = $this->getDueJobs($timestamp);
        
        if (empty($dueJobs)) {
            $this->log('debug', 'No jobs due for execution');
            return [];
        }

        $this->log('info', 'Found jobs due for execution', [
            'job_count' => count($dueJobs),
            'job_names' => array_keys($dueJobs)
        ]);

        // Sort jobs by priority
        $sortedJobs = $this->sortJobsByPriority($dueJobs);

        // Execute jobs with concurrency control
        $results = $this->jobExecutor->executeMany(
            $sortedJobs,
            true // Respect concurrency limit
        );

        // Store results for later access
        $this->lastResults = $results;

        return $results;
    }

    /**
     * Sort jobs by priority
     *
     * @param array<string, CronJobInterface> $jobs Jobs to sort
     * @return array<CronJobInterface> Sorted jobs (highest priority first)
     */
    private function sortJobsByPriority(array $jobs): array
    {
        $jobsArray = array_values($jobs);
        
        usort($jobsArray, function ($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        return $jobsArray;
    }

    /**
     * Update scheduler statistics
     *
     * @param array<string, JobResult> $results Execution results
     * @param float $duration Total execution duration
     */
    private function updateStatistics(array $results, float $duration): void
    {
        $this->statistics['total_runs']++;
        $this->statistics['last_run_time'] = time();
        $this->statistics['last_run_duration'] = round($duration, 3);

        foreach ($results as $result) {
            if ($result->isSkipped()) {
                $this->statistics['skipped_runs']++;
            } elseif ($result->isSuccess()) {
                $this->statistics['successful_runs']++;
            } else {
                $this->statistics['failed_runs']++;
            }
        }
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
            'component' => 'cron_scheduler',
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'pid' => getmypid(),
        ], $context);

        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        } else {
            // Fallback to error_log
            $logMessage = sprintf(
                '[%s] [CRON_SCHEDULER] %s %s',
                strtoupper($level),
                $message,
                json_encode($context, JSON_UNESCAPED_SLASHES)
            );
            error_log($logMessage);
        }
    }
}