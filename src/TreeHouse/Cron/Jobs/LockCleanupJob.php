<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Jobs;

use LengthOfRope\TreeHouse\Cron\CronJob;

/**
 * Lock Cleanup Job
 * 
 * Built-in cron job for cleaning up stale lock files and maintaining
 * the locking system in optimal condition.
 * 
 * @package LengthOfRope\TreeHouse\Cron\Jobs
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class LockCleanupJob extends CronJob
{
    /**
     * Configure the job
     */
    public function __construct()
    {
        $this->setName('lock:cleanup')
            ->setDescription('Clean up stale lock files')
            ->setSchedule('*/5 * * * *') // Every 5 minutes
            ->setPriority(10) // High priority
            ->setTimeout(60) // 1 minute
            ->setAllowsConcurrentExecution(true) // Allow multiple instances
            ->addMetadata('category', 'maintenance')
            ->addMetadata('type', 'built-in');
    }

    /**
     * Handle the job execution
     */
    public function handle(): bool
    {
        try {
            $this->logInfo('Starting lock cleanup');

            $cleaned = $this->cleanupStaleLocks();

            if ($cleaned > 0) {
                $this->logInfo("Lock cleanup completed", [
                    'locks_cleaned' => $cleaned
                ]);
            } else {
                $this->logInfo('No stale locks found to clean up');
            }

            return true;

        } catch (\Throwable $e) {
            $this->logError("Lock cleanup failed: {$e->getMessage()}", [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Clean up stale lock files
     */
    private function cleanupStaleLocks(): int
    {
        $lockDirectory = $this->getLockDirectory();
        
        if (!is_dir($lockDirectory)) {
            return 0;
        }

        $cleaned = 0;

        // Clean global lock if stale
        $cleaned += $this->cleanGlobalLock($lockDirectory);

        // Clean job locks if stale
        $cleaned += $this->cleanJobLocks($lockDirectory);

        return $cleaned;
    }

    /**
     * Clean global lock if stale
     */
    private function cleanGlobalLock(string $lockDirectory): int
    {
        $globalLockFile = $lockDirectory . '/global.lock';
        
        if (!file_exists($globalLockFile)) {
            return 0;
        }

        if ($this->isLockStale($globalLockFile)) {
            if (@unlink($globalLockFile)) {
                $this->logInfo('Cleaned stale global lock');
                return 1;
            }
        }

        return 0;
    }

    /**
     * Clean job locks if stale
     */
    private function cleanJobLocks(string $lockDirectory): int
    {
        $jobLockDirectory = $lockDirectory . '/jobs';
        
        if (!is_dir($jobLockDirectory)) {
            return 0;
        }

        $cleaned = 0;
        $lockFiles = glob($jobLockDirectory . '/*.lock');

        foreach ($lockFiles as $lockFile) {
            if ($this->isLockStale($lockFile)) {
                if (@unlink($lockFile)) {
                    $jobName = basename($lockFile, '.lock');
                    $this->logInfo("Cleaned stale job lock: {$jobName}");
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }

    /**
     * Check if a lock file is stale
     */
    private function isLockStale(string $lockFile): bool
    {
        if (!file_exists($lockFile)) {
            return false;
        }

        try {
            $content = file_get_contents($lockFile);
            if (!$content) {
                return true; // Empty or unreadable lock file is stale
            }

            $metadata = json_decode($content, true);
            if (!is_array($metadata)) {
                return true; // Invalid metadata format
            }

            // Check required fields
            if (!isset($metadata['pid'], $metadata['started_at'], $metadata['timeout'])) {
                return true; // Missing required fields
            }

            $pid = (int) $metadata['pid'];
            $startedAt = (int) $metadata['started_at'];
            $timeout = (int) $metadata['timeout'];

            // Check if timeout has been exceeded
            if (time() - $startedAt > $timeout) {
                return true;
            }

            // Check if process is still running
            if (!$this->isProcessRunning($pid)) {
                return true;
            }

            return false;

        } catch (\Throwable $e) {
            // If we can't read the lock file, consider it stale
            return true;
        }
    }

    /**
     * Check if a process is still running
     */
    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>NUL");
            return $output !== null && strpos($output, (string) $pid) !== false;
        } else {
            return file_exists("/proc/{$pid}");
        }
    }

    /**
     * Get lock directory path
     */
    private function getLockDirectory(): string
    {
        return getcwd() . '/storage/cron/locks';
    }
}