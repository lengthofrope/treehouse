<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cache;

use LengthOfRope\TreeHouse\Support\Carbon;
use LengthOfRope\TreeHouse\Support\Str;
use LengthOfRope\TreeHouse\Support\Arr;

/**
 * File Cache Implementation
 *
 * A file-based cache implementation that stores cached data as serialized files
 * on the filesystem. Each cache entry includes metadata for expiration handling.
 *
 * @package LengthOfRope\TreeHouse\Cache
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class FileCache implements CacheInterface
{
    /**
     * The cache directory path.
     */
    private string $directory;

    /**
     * Default cache TTL in seconds.
     */
    private int $defaultTtl;

    /**
     * Create a new file cache instance.
     *
     * @param string $directory The cache directory path
     * @param int $defaultTtl Default TTL in seconds (3600 = 1 hour)
     */
    public function __construct(string $directory, int $defaultTtl = 3600)
    {
        $this->directory = rtrim($directory, '/\\');
        $this->defaultTtl = $defaultTtl;
        
        $this->ensureDirectoryExists();
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|null $ttl Time to live in seconds (null for default TTL)
     * @return bool True on success, false on failure
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expiration = $ttl > 0 ? Carbon::now()->addSeconds($ttl)->getTimestamp() : null;
        
        return $this->storeWithMetadata($key, $value, $expiration);
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key The cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The cached value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return $default;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return $default;
        }

        $data = unserialize($contents);
        if ($data === false) {
            // Invalid cache file, remove it
            unlink($path);
            return $default;
        }

        // Check if expired
        if ($data['expiration'] !== null && Carbon::now()->getTimestamp() > $data['expiration']) {
            unlink($path);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key The cache key
     * @return bool True if key exists and not expired, false otherwise
     */
    public function has(string $key): bool
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return false;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $data = unserialize($contents);
        if ($data === false) {
            // Invalid cache file, remove it
            unlink($path);
            return false;
        }

        // Check if expired
        if ($data['expiration'] !== null && Carbon::now()->getTimestamp() > $data['expiration']) {
            unlink($path);
            return false;
        }

        return true;
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key The cache key
     * @return bool True on success, false on failure
     */
    public function forget(string $key): bool
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool True on success, false on failure
     */
    public function flush(): bool
    {
        $files = glob($this->directory . '/*');
        
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string $key The cache key
     * @param int|null $ttl Time to live in seconds
     * @param callable $callback Callback to execute if key doesn't exist
     * @return mixed The cached value or callback result
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param string $key The cache key
     * @param callable $callback Callback to execute if key doesn't exist
     * @return mixed The cached value or callback result
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->forever($key, $value);

        return $value;
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key The cache key
     * @param int $value The increment value
     * @return int|false The new value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
            return false;
        }

        $new = (int)$current + $value;
        
        if ($this->put($key, $new)) {
            return $new;
        }

        return false;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key The cache key
     * @param int $value The decrement value
     * @return int|false The new value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @return bool True on success, false on failure
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->storeWithMetadata($key, $value, null);
    }

    /**
     * Get multiple items from the cache by key.
     *
     * @param array $keys Array of cache keys
     * @return array Array of key-value pairs
     */
    public function many(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array $values Array of key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function putMany(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->put($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the full path for a cache key.
     *
     * @param string $key The cache key
     * @return string The full file path
     */
    private function path(string $key): string
    {
        // Sanitize the key to ensure it's safe for filesystem
        $safeKey = Str::slug($key, '_');
        $hash = hash('sha256', $safeKey);
        return $this->directory . '/' . $hash . '.cache';
    }

    /**
     * Ensure the cache directory exists.
     *
     * @return void
     */
    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * Clean up expired cache files.
     *
     * @return int Number of files cleaned up
     */
    public function cleanup(): int
    {
        $files = glob($this->directory . '/*.cache');
        
        if ($files === false) {
            return 0;
        }

        $cleaned = 0;
        $now = Carbon::now()->getTimestamp();

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $data = unserialize($contents);
            if ($data === false) {
                // Invalid cache file, remove it
                unlink($file);
                $cleaned++;
                continue;
            }

            // Check if expired
            if ($data['expiration'] !== null && $now > $data['expiration']) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics
     */
    public function getStats(): array
    {
        $files = glob($this->directory . '/*.cache');
        
        if ($files === false) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'expired_files' => 0
            ];
        }

        $totalFiles = count($files);
        $totalSize = 0;
        $expiredFiles = 0;
        $now = Carbon::now()->getTimestamp();

        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $contents = file_get_contents($file);
            if ($contents !== false) {
                $data = unserialize($contents);
                if ($data !== false && $data['expiration'] !== null && $now > $data['expiration']) {
                    $expiredFiles++;
                }
            }
        }

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'expired_files' => $expiredFiles,
            'directory' => $this->directory,
            'default_ttl' => $this->defaultTtl
        ];
    }

    /**
     * Get cache entries matching a pattern.
     *
     * @param string $pattern Pattern to match against original keys
     * @return array Array of matching cache entries
     */
    public function getByPattern(string $pattern): array
    {
        $files = glob($this->directory . '/*.cache');
        
        if ($files === false) {
            return [];
        }

        $matches = [];
        $now = Carbon::now()->getTimestamp();

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $data = unserialize($contents);
            if ($data === false) {
                continue;
            }

            // Skip expired entries
            if ($data['expiration'] !== null && $now > $data['expiration']) {
                continue;
            }

            // Extract original key from filename (this is a limitation of hash-based storage)
            // For pattern matching, we'd need to store the original key in the data
            if (isset($data['original_key']) && Str::is($pattern, $data['original_key'])) {
                $matches[$data['original_key']] = $data['value'];
            }
        }

        return $matches;
    }

    /**
     * Store cache data with original key for pattern matching.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $expiration
     * @return bool
     */
    private function storeWithMetadata(string $key, mixed $value, ?int $expiration): bool
    {
        $data = [
            'value' => $value,
            'expiration' => $expiration,
            'created_at' => Carbon::now()->getTimestamp(),
            'original_key' => $key // Store original key for pattern matching
        ];

        $path = $this->path($key);
        $serialized = serialize($data);

        return file_put_contents($path, $serialized, LOCK_EX) !== false;
    }

    /**
     * Get cache directory information.
     *
     * @return array Directory information
     */
    public function getDirectoryInfo(): array
    {
        return [
            'path' => $this->directory,
            'exists' => is_dir($this->directory),
            'writable' => is_writable($this->directory),
            'size' => $this->getDirectorySize()
        ];
    }

    /**
     * Get the total size of the cache directory.
     *
     * @return int Size in bytes
     */
    private function getDirectorySize(): int
    {
        $files = glob($this->directory . '/*.cache');
        
        if ($files === false) {
            return 0;
        }

        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        return $totalSize;
    }
}