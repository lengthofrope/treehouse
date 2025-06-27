<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\CacheCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Cache\FileCache;

/**
 * Cache Clear Command
 * 
 * Clears cached data from the TreeHouse cache system.
 * Supports clearing all cache or specific cache drivers.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\CacheCommands
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class CacheClearCommand extends Command
{

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('cache:clear')
            ->setDescription('Clear cached data')
            ->setHelp('This command clears cached data from the TreeHouse cache system.')
            ->addOption('driver', 'd', InputOption::VALUE_OPTIONAL, 'Cache driver to clear (default: all drivers)')
            ->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Specific cache key pattern to clear')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force clearing without confirmation');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $driver = $input->getOption('driver');
            $keyPattern = $input->getOption('key');
            $force = (bool) $input->getOption('force');
            
            // Get cache manager
            $cacheManager = $this->createCacheManager();
            
            if ($keyPattern) {
                return $this->clearSpecificKeys($cacheManager, $keyPattern, $driver, $force, $output);
            }
            
            if ($driver) {
                return $this->clearDriver($cacheManager, $driver, $force, $output);
            }
            
            return $this->clearAllCache($cacheManager, $force, $output);
            
        } catch (\Exception $e) {
            $this->error($output, "Failed to clear cache: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear all cache
     */
    private function clearAllCache(CacheManager $cacheManager, bool $force, OutputInterface $output): int
    {
        if (!$force && !$this->confirm($output, 'Are you sure you want to clear all cache?', false)) {
            $this->comment($output, 'Cache clear cancelled.');
            return 0;
        }

        $output->writeln('<info>Clearing all cache...</info>');
        
        // Clear file cache
        $fileCache = new FileCache($this->getCachePath());
        if ($fileCache->flush()) {
            $this->info($output, '✓ File cache cleared successfully');
        } else {
            $this->warn($output, '⚠ Failed to clear file cache');
        }
        
        // Clear view cache
        $this->clearViewCache($output);
        
        $this->info($output, 'Cache cleared successfully!');
        return 0;
    }

    /**
     * Clear specific cache driver
     */
    private function clearDriver(CacheManager $cacheManager, string $driver, bool $force, OutputInterface $output): int
    {
        if (!$force && !$this->confirm($output, "Are you sure you want to clear the '{$driver}' cache?", false)) {
            $this->comment($output, 'Cache clear cancelled.');
            return 0;
        }

        $output->writeln("<info>Clearing '{$driver}' cache...</info>");
        
        try {
            $cache = $cacheManager->driver($driver);
            if ($cache->flush()) {
                $this->info($output, "✓ '{$driver}' cache cleared successfully");
            } else {
                $this->warn($output, "⚠ Failed to clear '{$driver}' cache");
            }
        } catch (\Exception $e) {
            $this->error($output, "Failed to clear '{$driver}' cache: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * Clear specific cache keys by pattern
     */
    private function clearSpecificKeys(CacheManager $cacheManager, string $pattern, ?string $driver, bool $force, OutputInterface $output): int
    {
        if (!$force && !$this->confirm($output, "Are you sure you want to clear cache keys matching '{$pattern}'?", false)) {
            $this->comment($output, 'Cache clear cancelled.');
            return 0;
        }

        $output->writeln("<info>Clearing cache keys matching '{$pattern}'...</info>");
        
        // For file cache, we can scan the directory
        if (!$driver || $driver === 'file') {
            $cleared = $this->clearFilesByPattern($pattern, $output);
            $this->info($output, "✓ Cleared {$cleared} cache files");
        }
        
        return 0;
    }

    /**
     * Clear view cache
     */
    private function clearViewCache(OutputInterface $output): void
    {
        $viewCachePath = $this->getViewCachePath();
        
        if (!is_dir($viewCachePath)) {
            return;
        }
        
        $files = glob($viewCachePath . '/*');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $cleared++;
            }
        }
        
        if ($cleared > 0) {
            $this->info($output, "✓ Cleared {$cleared} compiled view files");
        }
    }

    /**
     * Clear files by pattern
     */
    private function clearFilesByPattern(string $pattern, OutputInterface $output): int
    {
        $cachePath = $this->getCachePath();
        $cleared = 0;
        
        if (!is_dir($cachePath)) {
            return 0;
        }
        
        // Convert pattern to regex
        $regex = str_replace(['*', '?'], ['.*', '.'], $pattern);
        $regex = '/^' . $regex . '$/';
        
        $files = scandir($cachePath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            if (preg_match($regex, $file)) {
                $filePath = $cachePath . '/' . $file;
                if (is_file($filePath) && unlink($filePath)) {
                    $cleared++;
                    if ($output->isVerbose()) {
                        $output->writeln("  Removed: {$file}");
                    }
                }
            }
        }
        
        return $cleared;
    }

    /**
     * Get cache path
     */
    private function getCachePath(): string
    {
        return getcwd() . '/storage/cache';
    }

    /**
     * Get view cache path
     */
    private function getViewCachePath(): string
    {
        return getcwd() . '/storage/views';
    }

    /**
     * Create cache manager instance
     */
    private function createCacheManager(): CacheManager
    {
        $config = [
            'file' => [
                'path' => $this->getCachePath(),
                'default_ttl' => 3600,
            ],
        ];
        
        return new CacheManager($config, 'file');
    }
}