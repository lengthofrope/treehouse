<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands\DatabaseCommands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\Migration;
use LengthOfRope\TreeHouse\Support\Env;

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
            
            // Get database connection once and reuse it
            $connection = $this->getDatabaseConnection();
            
            $migrations = $this->getPendingMigrations($migrationsPath, $connection);
            
            if (empty($migrations)) {
                $this->info($output, 'No pending migrations found.');
                return 0;
            }
            
            if ($step > 0) {
                $migrations = array_slice($migrations, 0, $step);
            }
            
            $this->runMigrations($migrations, $output, $connection);
            
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
        $env = Env::get('APP_ENV', 'development');
        return in_array(strtolower($env), ['production', 'prod']);
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(string $migrationsPath, Connection $connection): array
    {
        $files = glob($migrationsPath . '/*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            // Support both timestamp format and simple numbering
            if (preg_match('/^\d{3,4}_/', $filename) || preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename)) {
                $migrations[] = $file;
            }
        }
        
        sort($migrations);
        
        // Filter out already run migrations
        $this->ensureMigrationsTableExists($connection);
        
        $runMigrations = $this->getRunMigrations($connection);
        
        return array_filter($migrations, function($migrationFile) use ($runMigrations) {
            $filename = basename($migrationFile, '.php');
            return !in_array($filename, $runMigrations);
        });
    }

    /**
     * Run migrations
     */
    private function runMigrations(array $migrations, OutputInterface $output, Connection $connection): void
    {
        foreach ($migrations as $migrationFile) {
            $filename = basename($migrationFile);
            $migrationName = basename($migrationFile, '.php');
            
            if ($output->isVerbose()) {
                $output->writeln("  Running: {$filename}");
            }
            
            try {
                // Include the migration file
                require_once $migrationFile;
                
                // Extract class name from filename
                $className = $this->getClassNameFromFile($migrationFile);
                
                if (!class_exists($className)) {
                    throw new \Exception("Migration class {$className} not found in {$filename}");
                }
                
                // Instantiate and run the migration
                $migrationInstance = new $className($connection, $migrationName);
                
                if (!$migrationInstance instanceof Migration) {
                    throw new \Exception("Migration {$className} must extend Migration class");
                }
                
                // Execute migration in transaction
                $connection->beginTransaction();
                
                try {
                    $migrationInstance->up();
                    $this->recordMigration($connection, $migrationName);
                    $connection->commit();
                    
                    $this->info($output, "✓ Migrated: {$filename}");
                } catch (\Exception $e) {
                    $connection->rollback();
                    throw $e;
                }
                
            } catch (\Exception $e) {
                $this->error($output, "✗ Failed to migrate {$filename}: " . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Get database connection
     */
    private function getDatabaseConnection(): Connection
    {
        // Load environment variables
        Env::loadIfNeeded();
        
        $config = [
            'driver' => Env::get('DB_CONNECTION', Env::get('DB_DRIVER', 'mysql')),
            'host' => Env::get('DB_HOST', 'localhost'),
            'port' => (int) Env::get('DB_PORT', 3306),
            'database' => Env::get('DB_DATABASE', ''),
            'username' => Env::get('DB_USERNAME', ''),
            'password' => Env::get('DB_PASSWORD', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
        ];
        
        return new Connection($config);
    }
    
    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTableExists(Connection $connection): void
    {
        if ($connection->tableExists('migrations')) {
            return;
        }
        
        $driver = $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL,
                executed_at TEXT NOT NULL
            )";
        } else {
            $sql = "CREATE TABLE migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $connection->statement($sql);
    }
    
    /**
     * Get list of already run migrations
     */
    private function getRunMigrations(Connection $connection): array
    {
        $results = $connection->select('SELECT migration FROM migrations ORDER BY id');
        return array_column($results, 'migration');
    }
    
    /**
     * Record migration as run
     */
    private function recordMigration(Connection $connection, string $migrationName): void
    {
        // Get next batch number
        $result = $connection->selectOne('SELECT MAX(batch) as max_batch FROM migrations');
        $batch = ($result['max_batch'] ?? 0) + 1;
        
        $driver = $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $sql = "INSERT INTO migrations (migration, batch, executed_at) VALUES (?, ?, datetime('now'))";
        } else {
            $sql = "INSERT INTO migrations (migration, batch, executed_at) VALUES (?, ?, NOW())";
        }
        
        $connection->statement($sql, [$migrationName, $batch]);
    }
    
    /**
     * Extract class name from migration file
     */
    private function getClassNameFromFile(string $file): string
    {
        $content = file_get_contents($file);
        
        // Look for class declaration
        if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/i', $content, $matches)) {
            return $matches[1];
        }
        
        // Fallback: derive from filename
        $filename = basename($file, '.php');
        
        // Remove leading numbers and underscores, convert to PascalCase
        $className = preg_replace('/^\d+_/', '', $filename);
        $parts = explode('_', $className);
        
        return implode('', array_map('ucfirst', $parts));
    }
}