<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cron\Jobs;

use LengthOfRope\TreeHouse\Cron\CronJob;
use LengthOfRope\TreeHouse\Cache\CacheManager;

/**
 * Cache Cleanup Job
 * 
 * Built-in cron job for cleaning up expired cache entries and maintaining
 * the cache system in optimal condition.
 * 
 * @package LengthOfRope\TreeHouse\Cron\Jobs
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CacheCleanupJob extends CronJob
{
    /**
     * Configure the job
     */
    public function __construct()
    {
        $this->setName('cache:cleanup')
            ->setDescription('Clean up expired cache entries')
            ->setSchedule('0 2 * * *') // Daily at 2 AM
            ->setPriority(30)
            ->setTimeout(600) // 10 minutes
            ->addMetadata('category', 'maintenance')
            ->addMetadata('type', 'built-in');
    }

    /**
     * Handle the job execution
     */
    public function handle(): bool
    {
        try {
            $this->logInfo('Starting cache cleanup');

            $cleaned = 0;

            // Clean file cache
            $cleaned += $this->cleanFileCache();

            // Clean view cache
            $cleaned += $this->cleanViewCache();

            $this->logInfo("Cache cleanup completed successfully", [
                'files_cleaned' => $cleaned,
                'memory_after' => $this->getMemoryUsage()
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->logError("Cache cleanup failed: {$e->getMessage()}", [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Clean file-based cache
     */
    private function cleanFileCache(): int
    {
        $cachePath = $this->getCachePath();
        
        if (!is_dir($cachePath)) {
            return 0;
        }

        $cleaned = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cachePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // Check if file is older than 24 hours
                if (time() - $file->getMTime() > 86400) {
                    if (@unlink($file->getRealPath())) {
                        $cleaned++;
                    }
                }
            }
        }

        if ($cleaned > 0) {
            $this->logInfo("Cleaned {$cleaned} cache files");
        }

        return $cleaned;
    }

    /**
     * Clean compiled view cache
     */
    private function cleanViewCache(): int
    {
        $viewCachePath = $this->getViewCachePath();
        
        if (!is_dir($viewCachePath)) {
            return 0;
        }

        $cleaned = 0;
        $files = glob($viewCachePath . '/*.php');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Check if file is older than 1 hour
                if (time() - filemtime($file) > 3600) {
                    if (@unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }

        if ($cleaned > 0) {
            $this->logInfo("Cleaned {$cleaned} view cache files");
        }

        return $cleaned;
    }

    /**
     * Get cache directory path
     */
    private function getCachePath(): string
    {
        return getcwd() . '/storage/cache';
    }

    /**
     * Get view cache directory path
     */
    private function getViewCachePath(): string
    {
        return getcwd() . '/storage/views';
    }
}