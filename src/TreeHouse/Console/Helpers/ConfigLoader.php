<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Helpers;

/**
 * Configuration Loader
 * 
 * Loads configuration from various sources for the TreeHouse CLI.
 * Supports .env files, PHP config files, and environment variables.
 * 
 * @package LengthOfRope\TreeHouse\Console\Helpers
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class ConfigLoader
{
    /**
     * Loaded configuration
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Configuration file paths
     *
     * @var string[]
     */
    private array $configPaths = [];

    /**
     * Create configuration loader
     */
    public function __construct()
    {
        $this->configPaths = [
            getcwd() . '/config/treehouse.php',
            getcwd() . '/config.php',
            __DIR__ . '/../../../../config/treehouse.php',
        ];
        
        $this->loadConfiguration();
    }

    /**
     * Load configuration from all sources
     */
    private function loadConfiguration(): void
    {
        // Load .env file
        $this->loadEnvFile();
        
        // Load PHP configuration files
        $this->loadConfigFiles();
        
        // Set default configuration
        $this->setDefaults();
    }

    /**
     * Load .env file
     */
    private function loadEnvFile(): void
    {
        $envPaths = [
            getcwd() . '/.env',
            __DIR__ . '/../../../../.env',
        ];
        
        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $this->parseEnvFile($envPath);
                break;
            }
        }
    }

    /**
     * Parse .env file
     */
    private function parseEnvFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes
                $value = trim($value, '"\'');
                
                // Set environment variable
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Load PHP configuration files
     */
    private function loadConfigFiles(): void
    {
        foreach ($this->configPaths as $configPath) {
            if (file_exists($configPath)) {
                $config = require $configPath;
                if (is_array($config)) {
                    $this->config = array_merge($this->config, $config);
                }
                break;
            }
        }
    }

    /**
     * Set default configuration values
     */
    private function setDefaults(): void
    {
        $defaults = [
            'cache' => [
                'default' => 'file',
                'drivers' => [
                    'file' => [
                        'path' => $this->getStoragePath('cache'),
                        'default_ttl' => 3600,
                    ],
                ],
            ],
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => $this->env('DB_HOST', 'localhost'),
                        'port' => $this->env('DB_PORT', '3306'),
                        'database' => $this->env('DB_DATABASE', 'treehouse_db'),
                        'username' => $this->env('DB_USERNAME', 'root'),
                        'password' => $this->env('DB_PASSWORD', ''),
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                    ],
                ],
                'migrations' => [
                    'path' => getcwd() . '/database/migrations',
                    'table' => 'migrations',
                ],
            ],
            'view' => [
                'paths' => [
                    getcwd() . '/resources/views',
                    getcwd() . '/public/demo/views',
                ],
                'cache_path' => $this->getStoragePath('views'),
                'cache_enabled' => true,
            ],
            'paths' => [
                'storage' => getcwd() . '/storage',
                'cache' => $this->getStoragePath('cache'),
                'views' => $this->getStoragePath('views'),
                'logs' => $this->getStoragePath('logs'),
            ],
        ];
        
        $this->config = $this->mergeConfigRecursive($defaults, $this->config);
    }

    /**
     * Get configuration value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }

    /**
     * Set configuration value using dot notation
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }
        
        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }
        
        return true;
    }

    /**
     * Get all configuration
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get environment variable
     */
    public function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        if (in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value) === 'true';
        }
        
        // Convert numeric strings
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        
        return $value;
    }

    /**
     * Get storage path
     */
    private function getStoragePath(string $subPath = ''): string
    {
        $storagePath = getcwd() . '/storage';
        
        if (!empty($subPath)) {
            $storagePath .= '/' . trim($subPath, '/');
        }
        
        // Create directory if it doesn't exist
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        return $storagePath;
    }

    /**
     * Recursively merge configuration arrays
     */
    private function mergeConfigRecursive(array $array1, array $array2): array
    {
        $merged = $array1;
        
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->mergeConfigRecursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }

    /**
     * Get database configuration
     *
     * @return array<string, mixed>
     */
    public function getDatabaseConfig(?string $connection = null): array
    {
        $connection = $connection ?? $this->get('database.default', 'mysql');
        return $this->get("database.connections.{$connection}", []);
    }

    /**
     * Get cache configuration
     *
     * @return array<string, mixed>
     */
    public function getCacheConfig(?string $driver = null): array
    {
        $driver = $driver ?? $this->get('cache.default', 'file');
        return $this->get("cache.drivers.{$driver}", []);
    }

    /**
     * Get view configuration
     *
     * @return array<string, mixed>
     */
    public function getViewConfig(): array
    {
        return $this->get('view', []);
    }
}