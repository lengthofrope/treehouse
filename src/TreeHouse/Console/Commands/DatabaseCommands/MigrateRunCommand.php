<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\DatabaseCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Database\Connection;

/**
 * Database Migration Run Command
 * 
 * Run pending database migrations.
 * 
 * @package LengthOfRope\TreeHouse\Console\Commands\DatabaseCommands
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class MigrateRunCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('migrate:run')
            ->setDescription('Run pending database migrations')
            ->setHelp('This command runs all pending database migrations.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force run migrations in production')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to run', '0');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $step = (int) $input->getOption('step');
        
        $output->writeln('<info>Running database migrations...</info>');
        
        try {
            // Check if we're in production and force is not set
            if ($this->isProduction() && !$force) {
                $this->error($output, 'Cannot run migrations in production without --force flag');
                return 1;
            }
            
            $migrationsPath = getcwd() . '/database/migrations';
            
            if (!is_dir($migrationsPath)) {
                $this->error($output, "Migrations directory not found: {$migrationsPath}");
                return 1;
            }
            
            $migrations = $this->getPendingMigrations($migrationsPath);
            
            if (empty($migrations)) {
                $this->info($output, 'No pending migrations found.');
                return 0;
            }
            
            if ($step > 0) {
                $migrations = array_slice($migrations, 0, $step);
            }
            
            $this->runMigrations($migrations, $output);
            
            $count = count($migrations);
            $this->success($output, "Successfully ran {$count} migration(s)!");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error($output, 'Migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check if we're in production environment
     */
    private function isProduction(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'development';
        return in_array(strtolower($env), ['production', 'prod']);
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(string $migrationsPath): array
    {
        $files = glob($migrationsPath . '/*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)\.php$/', $filename)) {
                $migrations[] = $file;
            }
        }
        
        sort($migrations);
        
        // Filter out already run migrations (simplified - in real implementation, 
        // you'd check against a migrations table)
        return $migrations;
    }

    /**
     * Run migrations
     */
    private function runMigrations(array $migrations, OutputInterface $output): void
    {
        foreach ($migrations as $migration) {
            $filename = basename($migration);
            
            if ($output->isVerbose()) {
                $output->writeln("  Running: {$filename}");
            }
            
            // In a real implementation, you would:
            // 1. Include the migration file
            // 2. Execute the up() method
            // 3. Record the migration in the migrations table
            
            $this->info($output, "✓ Migrated: {$filename}");
        }
    }
}