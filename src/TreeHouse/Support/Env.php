<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Support;

/**
 * Environment Variable Handler
 * 
 * Provides centralized access to environment variables with type conversion
 * and fallback support. Handles .env file loading and parsing.
 * 
 * @package LengthOfRope\TreeHouse\Support
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class Env
{
    /**
     * Whether .env file has been loaded
     */
    private static bool $loaded = false;

    /**
     * Cached environment variables
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Load .env file if not already loaded
     */
    public static function loadIfNeeded(): void
    {
        if (self::$loaded) {
            return;
        }

        self::load();
        self::$loaded = true;
    }

    /**
     * Load .env file from multiple possible locations
     */
    public static function load(?string $path = null): void
    {
        $envPaths = $path ? [$path] : [
            getcwd() . '/.env',
            __DIR__ . '/../../../../.env',
        ];

        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                self::parseEnvFile($envPath);
                break;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable with type conversion
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadIfNeeded();

        // Check cache first
        if (array_key_exists($key, self::$cache)) {
            return self::convertValue(self::$cache[$key]);
        }

        // Get from environment
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Cache the raw value
        self::$cache[$key] = $value;

        return self::convertValue($value);
    }

    /**
     * Set environment variable
     */
    public static function set(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
        self::$cache[$key] = $value;
    }

    /**
     * Check if environment variable exists
     */
    public static function has(string $key): bool
    {
        self::loadIfNeeded();
        
        return isset($_ENV[$key]) || getenv($key) !== false;
    }

    /**
     * Get all environment variables
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::loadIfNeeded();
        
        $env = [];
        foreach ($_ENV as $key => $value) {
            $env[$key] = self::convertValue($value);
        }
        
        return $env;
    }

    /**
     * Parse .env file and set environment variables
     */
    private static function parseEnvFile(string $path): void
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

                // Remove surrounding quotes
                $value = self::parseValue($value);

                // Set environment variable
                self::set($key, $value);
            }
        }
    }

    /**
     * Parse environment value, handling quotes and escaping
     */
    private static function parseValue(string $value): string
    {
        // Handle quoted values
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        // Handle escaped characters in double quotes
        if (str_contains($value, '\\')) {
            $value = str_replace(['\\n', '\\r', '\\t', '\\"', "\\'"], ["\n", "\r", "\t", '"', "'"], $value);
        }

        return $value;
    }

    /**
     * Convert string value to appropriate type
     */
    private static function convertValue(string $value): mixed
    {
        // Convert string booleans
        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }
        if ($lower === 'empty') {
            return '';
        }

        // Convert numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Clear cached environment variables
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Force reload of .env file
     */
    public static function reload(?string $path = null): void
    {
        self::$loaded = false;
        self::clearCache();
        self::load($path);
    }
}