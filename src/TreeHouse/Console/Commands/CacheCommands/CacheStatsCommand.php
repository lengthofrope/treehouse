<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\CacheCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;

/**
 * Cache Stats Command
 * 
 * Display cache statistics and information.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\CacheCommands
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class CacheStatsCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('cache:stats')
            ->setDescription('Display cache statistics')
            ->setHelp('This command displays statistics about the TreeHouse cache system.')
            ->addOption('driver', 'd', InputOption::VALUE_OPTIONAL, 'Cache driver to show stats for');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $driver = $input->getOption('driver') ?? 'file';
        
        $output->writeln('<info>Cache Statistics</info>');
        $output->writeln('================');
        $output->writeln('');
        
        $this->showFileStats($output);
        $this->showViewStats($output);
        
        return 0;
    }

    /**
     * Show file cache statistics
     */
    private function showFileStats(OutputInterface $output): void
    {
        $cachePath = getcwd() . '/storage/cache';
        
        $output->writeln('<comment>File Cache:</comment>');
        
        if (!is_dir($cachePath)) {
            $output->writeln('  Status: <error>Not initialized</error>');
            $output->writeln('');
            return;
        }
        
        $files = glob($cachePath . '/*');
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }
        
        $output->writeln('  Status: <info>Active</info>');
        $output->writeln('  Path: ' . $cachePath);
        $output->writeln('  Files: ' . $fileCount);
        $output->writeln('  Size: ' . $this->formatBytes($totalSize));
        $output->writeln('');
    }

    /**
     * Show view cache statistics
     */
    private function showViewStats(OutputInterface $output): void
    {
        $viewCachePath = getcwd() . '/storage/views';
        
        $output->writeln('<comment>View Cache:</comment>');
        
        if (!is_dir($viewCachePath)) {
            $output->writeln('  Status: <error>Not initialized</error>');
            $output->writeln('');
            return;
        }
        
        $files = glob($viewCachePath . '/*');
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }
        
        $output->writeln('  Status: <info>Active</info>');
        $output->writeln('  Path: ' . $viewCachePath);
        $output->writeln('  Compiled Templates: ' . $fileCount);
        $output->writeln('  Size: ' . $this->formatBytes($totalSize));
        $output->writeln('');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}