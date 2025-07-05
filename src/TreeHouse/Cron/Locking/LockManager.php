<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Locking;

use LengthOfRope\TreeHouse\Cron\Exceptions\LockException;

/**
 * Lock Manager
 * 
 * Manages both global scheduler locks and individual job locks.
 * Provides comprehensive locking functionality to prevent concurrent executions.
 * 
 * @package LengthOfRope\TreeHouse\Cron\Locking
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LockManager
{
    /**
     * Base directory for lock files
     */
    private string $lockDirectory;

    /**
     * Global lock instance
     */
    private ?Lock $globalLock = null;

    /**
     * Job lock instances
     *
     * @var array<string, Lock>
     */
    private array $jobLocks = [];

    /**
     * Default timeout for locks
     */
    private int $defaultTimeout;

    /**
     * Create new lock manager
     *
     * @param string $lockDirectory Base directory for lock files
     * @param int $defaultTimeout Default timeout in seconds
     */
    public function __construct(string $lockDirectory, int $defaultTimeout = 300)
    {
        $this->lockDirectory = rtrim($lockDirectory, '/');
        $this->defaultTimeout = $defaultTimeout;
        
        // Ensure lock directory exists
        $this->ensureDirectoryExists($this->lockDirectory);
        $this->ensureDirectoryExists($this->lockDirectory . '/jobs');
    }

    /**
     * Acquire global scheduler lock
     *
     * @param int|null $timeout Timeout in seconds (null for default)
     * @return bool True if lock was acquired
     */
    public function acquireGlobalLock(?int $timeout = null): bool
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        
        if ($this->globalLock === null) {
            $this->globalLock = new Lock($this->getGlobalLockPath());
        }

        return $this->globalLock->acquire('global', $timeout, [
            'type' => 'global_scheduler',
            'php_sapi' => php_sapi_name(),
            'working_directory' => getcwd(),
        ]);
    }

    /**
     * Release global scheduler lock
     *
     * @return bool True if lock was released
     */
    public function releaseGlobalLock(): bool
    {
        if ($this->globalLock === null) {
            return true;
        }

        return $this->globalLock->release();
    }

    /**
     * Check if global lock is active
     *
     * @return bool True if global lock exists and is not stale
     */
    public function isGlobalLockActive(): bool
    {
        if ($this->globalLock === null) {
            $this->globalLock = new Lock($this->getGlobalLockPath());
        }

        return $this->globalLock->exists() && !$this->globalLock->isStale();
    }

    /**
     * Acquire job-specific lock
     *
     * @param string $jobName Job name
     * @param int|null $timeout Timeout in seconds (null for default)
     * @return bool True if lock was acquired
     */
    public function acquireJobLock(string $jobName, ?int $timeout = null): bool
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        $lockKey = $this->normalizeJobName($jobName);
        
        if (!isset($this->jobLocks[$lockKey])) {
            $this->jobLocks[$lockKey] = new Lock($this->getJobLockPath($lockKey));
        }

        return $this->jobLocks[$lockKey]->acquire($jobName, $timeout, [
            'type' => 'job_lock',
            'original_job_name' => $jobName,
            'normalized_name' => $lockKey,
        ]);
    }

    /**
     * Release job-specific lock
     *
     * @param string $jobName Job name
     * @return bool True if lock was released
     */
    public function releaseJobLock(string $jobName): bool
    {
        $lockKey = $this->normalizeJobName($jobName);
        
        if (!isset($this->jobLocks[$lockKey])) {
            return true;
        }

        return $this->jobLocks[$lockKey]->release();
    }

    /**
     * Check if job is locked
     *
     * @param string $jobName Job name
     * @return bool True if job is locked and lock is not stale
     */
    public function isJobLocked(string $jobName): bool
    {
        $lockKey = $this->normalizeJobName($jobName);
        
        if (!isset($this->jobLocks[$lockKey])) {
            $this->jobLocks[$lockKey] = new Lock($this->getJobLockPath($lockKey));
        }

        return $this->jobLocks[$lockKey]->exists() && !$this->jobLocks[$lockKey]->isStale();
    }

    /**
     * Clean up all stale locks
     *
     * @return int Number of locks cleaned up
     */
    public function cleanupStaleLocks(): int
    {
        $cleaned = 0;

        // Clean up global lock
        if ($this->globalLock === null) {
            $this->globalLock = new Lock($this->getGlobalLockPath());
        }
        
        if ($this->globalLock->exists() && $this->globalLock->isStale()) {
            if ($this->globalLock->release()) {
                $cleaned++;
            }
        }

        // Clean up job locks
        $jobLockDir = $this->lockDirectory . '/jobs';
        if (is_dir($jobLockDir)) {
            $lockFiles = glob($jobLockDir . '/*.lock');
            foreach ($lockFiles as $lockFile) {
                $lock = new Lock($lockFile);
                if ($lock->exists() && $lock->isStale()) {
                    if ($lock->release()) {
                        $cleaned++;
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Get all active locks information
     *
     * @return array<string, array<string, mixed>> Lock information
     */
    public function getActiveLocks(): array
    {
        $locks = [];

        // Check global lock
        if ($this->globalLock === null) {
            $this->globalLock = new Lock($this->getGlobalLockPath());
        }
        
        if ($this->globalLock->exists()) {
            $metadata = $this->globalLock->getMetadata();
            $locks['global'] = [
                'type' => 'global',
                'status' => $this->globalLock->getStatus(),
                'metadata' => $metadata ? $metadata->toArray() : null,
                'lock_file' => $this->globalLock->getLockFile(),
            ];
        }

        // Check job locks
        $jobLockDir = $this->lockDirectory . '/jobs';
        if (is_dir($jobLockDir)) {
            $lockFiles = glob($jobLockDir . '/*.lock');
            foreach ($lockFiles as $lockFile) {
                $lock = new Lock($lockFile);
                if ($lock->exists()) {
                    $metadata = $lock->getMetadata();
                    $jobName = $metadata ? $metadata->jobName : basename($lockFile, '.lock');
                    
                    $locks['jobs'][$jobName] = [
                        'type' => 'job',
                        'status' => $lock->getStatus(),
                        'metadata' => $metadata ? $metadata->toArray() : null,
                        'lock_file' => $lock->getLockFile(),
                    ];
                }
            }
        }

        return $locks;
    }

    /**
     * Force release all locks (emergency use only)
     *
     * @return int Number of locks force released
     */
    public function forceReleaseAllLocks(): int
    {
        $released = 0;

        // Force release global lock
        if ($this->globalLock === null) {
            $this->globalLock = new Lock($this->getGlobalLockPath());
        }
        
        if ($this->globalLock->exists()) {
            if ($this->globalLock->forceRelease()) {
                $released++;
            }
        }

        // Force release job locks
        $jobLockDir = $this->lockDirectory . '/jobs';
        if (is_dir($jobLockDir)) {
            $lockFiles = glob($jobLockDir . '/*.lock');
            foreach ($lockFiles as $lockFile) {
                $lock = new Lock($lockFile);
                if ($lock->exists()) {
                    if ($lock->forceRelease()) {
                        $released++;
                    }
                }
            }
        }

        return $released;
    }

    /**
     * Get lock directory path
     *
     * @return string Lock directory path
     */
    public function getLockDirectory(): string
    {
        return $this->lockDirectory;
    }

    /**
     * Get default timeout
     *
     * @return int Default timeout in seconds
     */
    public function getDefaultTimeout(): int
    {
        return $this->defaultTimeout;
    }

    /**
     * Set default timeout
     *
     * @param int $timeout Timeout in seconds
     * @return void
     */
    public function setDefaultTimeout(int $timeout): void
    {
        $this->defaultTimeout = $timeout;
    }

    /**
     * Get global lock path
     *
     * @return string Global lock file path
     */
    private function getGlobalLockPath(): string
    {
        return $this->lockDirectory . '/global.lock';
    }

    /**
     * Get job lock path
     *
     * @param string $jobName Normalized job name
     * @return string Job lock file path
     */
    private function getJobLockPath(string $jobName): string
    {
        return $this->lockDirectory . '/jobs/' . $jobName . '.lock';
    }

    /**
     * Normalize job name for file system
     *
     * @param string $jobName Original job name
     * @return string Normalized job name
     */
    private function normalizeJobName(string $jobName): string
    {
        // Replace invalid filesystem characters
        $normalized = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $jobName);
        $normalized = preg_replace('/-+/', '-', $normalized);
        return trim($normalized, '-');
    }

    /**
     * Ensure directory exists
     *
     * @param string $directory Directory path
     * @return void
     * @throws LockException If directory cannot be created
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new LockException("Failed to create lock directory: $directory");
        }
    }

    /**
     * Refresh job lock timeout
     *
     * @param string $jobName Job name
     * @param int $newTimeout New timeout in seconds
     * @return bool True if timeout was refreshed
     */
    public function refreshJobLock(string $jobName, int $newTimeout): bool
    {
        $lockKey = $this->normalizeJobName($jobName);
        
        if (!isset($this->jobLocks[$lockKey])) {
            return false;
        }

        return $this->jobLocks[$lockKey]->refresh($newTimeout);
    }

    /**
     * Get lock statistics
     *
     * @return array<string, mixed> Lock statistics
     */
    public function getStatistics(): array
    {
        $activeLocks = $this->getActiveLocks();
        
        return [
            'total_locks' => count($activeLocks),
            'global_lock_active' => isset($activeLocks['global']),
            'active_job_locks' => count($activeLocks['jobs'] ?? []),
            'lock_directory' => $this->lockDirectory,
            'default_timeout' => $this->defaultTimeout,
            'stale_locks_detected' => $this->countStaleLocks(),
        ];
    }

    /**
     * Count stale locks without cleaning them
     *
     * @return int Number of stale locks
     */
    private function countStaleLocks(): int
    {
        $stale = 0;

        // Check global lock
        if ($this->globalLock === null) {
            $this->globalLock = new Lock($this->getGlobalLockPath());
        }
        
        if ($this->globalLock->exists() && $this->globalLock->isStale()) {
            $stale++;
        }

        // Check job locks
        $jobLockDir = $this->lockDirectory . '/jobs';
        if (is_dir($jobLockDir)) {
            $lockFiles = glob($jobLockDir . '/*.lock');
            foreach ($lockFiles as $lockFile) {
                $lock = new Lock($lockFile);
                if ($lock->exists() && $lock->isStale()) {
                    $stale++;
                }
            }
        }

        return $stale;
    }
}