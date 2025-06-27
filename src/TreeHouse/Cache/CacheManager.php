<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cache;

use InvalidArgumentException;
use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Str;

/**
 * Cache Manager
 *
 * Manages multiple cache drivers and provides a unified interface for caching operations.
 * Supports driver switching and configuration management.
 *
 * @package LengthOfRope\TreeHouse\Cache
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class CacheManager
{
    /**
     * The registered cache drivers.
     *
     * @var array<string, CacheInterface>
     */
    private array $drivers = [];

    /**
     * The default cache driver name.
     */
    private string $defaultDriver;

    /**
     * Cache configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new cache manager instance.
     *
     * @param array<string, mixed> $config Cache configuration
     * @param string $defaultDriver Default driver name
     */
    public function __construct(array $config = [], string $defaultDriver = 'file')
    {
        $this->config = $config;
        $this->defaultDriver = $defaultDriver;
    }

    /**
     * Get a cache driver instance.
     *
     * @param string|null $driver Driver name (null for default)
     * @return CacheInterface
     * @throws InvalidArgumentException
     */
    public function driver(?string $driver = null): CacheInterface
    {
        $driver = $driver ?? $this->defaultDriver;

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Register a custom cache driver.
     *
     * @param string $name Driver name
     * @param CacheInterface $driver Driver instance
     * @return void
     */
    public function extend(string $name, CacheInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /**
     * Set the default cache driver.
     *
     * @param string $driver Driver name
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Get the default cache driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->driver()->put($key, $value, $ttl);
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
        return $this->driver()->get($key, $default);
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key The cache key
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool
    {
        return $this->driver()->has($key);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key The cache key
     * @return bool True on success, false on failure
     */
    public function forget(string $key): bool
    {
        return $this->driver()->forget($key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool True on success, false on failure
     */
    public function flush(): bool
    {
        return $this->driver()->flush();
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
        return $this->driver()->remember($key, $ttl, $callback);
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
        return $this->driver()->rememberForever($key, $callback);
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
        return $this->driver()->increment($key, $value);
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
        return $this->driver()->decrement($key, $value);
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
        return $this->driver()->forever($key, $value);
    }

    /**
     * Get multiple items from the cache by key.
     *
     * @param array $keys Array of cache keys
     * @return array Array of key-value pairs
     */
    public function many(array $keys): array
    {
        return $this->driver()->many($keys);
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
        return $this->driver()->putMany($values, $ttl);
    }

    /**
     * Add a cache key prefix to all operations.
     *
     * @param string $prefix The prefix to add
     * @return PrefixedCache
     */
    public function prefix(string $prefix): PrefixedCache
    {
        return new PrefixedCache($this->driver(), $prefix);
    }

    /**
     * Create a cache driver instance.
     *
     * @param string $driver Driver name
     * @return CacheInterface
     * @throws InvalidArgumentException
     */
    private function createDriver(string $driver): CacheInterface
    {
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Cache driver [{$driver}] is not supported.");
    }

    /**
     * Create a file cache driver.
     *
     * @return FileCache
     */
    private function createFileDriver(): FileCache
    {
        $config = $this->config['file'] ?? [];
        $directory = $config['path'] ?? sys_get_temp_dir() . '/cache';
        $defaultTtl = $config['default_ttl'] ?? 3600;

        return new FileCache($directory, $defaultTtl);
    }

    /**
     * Get all registered drivers.
     *
     * @return array<string, CacheInterface>
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Clear all driver instances.
     *
     * @return void
     */
    public function clearDrivers(): void
    {
        $this->drivers = [];
    }

    /**
     * Get cache configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set cache configuration.
     *
     * @param array<string, mixed> $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->clearDrivers(); // Clear drivers to force recreation with new config
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Set a configuration value using dot notation.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setConfigValue(string $key, mixed $value): void
    {
        Arr::set($this->config, $key, $value);
        $this->clearDrivers(); // Clear drivers to force recreation with new config
    }

    /**
     * Generate a cache key with optional prefix and normalization.
     *
     * @param string $key
     * @param string|null $prefix
     * @return string
     */
    public function generateKey(string $key, ?string $prefix = null): string
    {
        // Normalize the key to be cache-safe
        $normalizedKey = Str::slug($key, '_');
        
        if ($prefix) {
            $normalizedPrefix = Str::slug($prefix, '_');
            return $normalizedPrefix . ':' . $normalizedKey;
        }
        
        return $normalizedKey;
    }

    /**
     * Put multiple items with a common prefix.
     *
     * @param string $prefix
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function putManyWithPrefix(string $prefix, array $values, ?int $ttl = null): bool
    {
        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedValues[$this->generateKey($key, $prefix)] = $value;
        }
        
        return $this->putMany($prefixedValues, $ttl);
    }

    /**
     * Get multiple items with a common prefix.
     *
     * @param string $prefix
     * @param array $keys
     * @return array
     */
    public function getManyWithPrefix(string $prefix, array $keys): array
    {
        $prefixedKeys = [];
        foreach ($keys as $key) {
            $prefixedKeys[] = $this->generateKey($key, $prefix);
        }
        
        $results = $this->many($prefixedKeys);
        
        // Remove prefix from result keys
        $unprefixed = [];
        foreach ($results as $prefixedKey => $value) {
            $originalKey = Str::after($prefixedKey, $prefix . ':');
            $unprefixed[$originalKey] = $value;
        }
        
        return $unprefixed;
    }
}

/**
 * Prefixed Cache Wrapper
 *
 * Wraps a cache driver to automatically prefix all cache keys.
 *
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class PrefixedCache implements CacheInterface
{
    /**
     * The underlying cache driver.
     */
    private CacheInterface $cache;

    /**
     * The cache key prefix.
     */
    private string $prefix;

    /**
     * Create a new prefixed cache instance.
     *
     * @param CacheInterface $cache The underlying cache driver
     * @param string $prefix The key prefix
     */
    public function __construct(CacheInterface $cache, string $prefix)
    {
        $this->cache = $cache;
        $this->prefix = rtrim($prefix, ':') . ':';
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->put($this->prefix . $key, $value, $ttl);
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
        return $this->cache->get($this->prefix . $key, $default);
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key The cache key
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool
    {
        return $this->cache->has($this->prefix . $key);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key The cache key
     * @return bool True on success, false on failure
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($this->prefix . $key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool True on success, false on failure
     */
    public function flush(): bool
    {
        return $this->cache->flush();
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
        return $this->cache->remember($this->prefix . $key, $ttl, $callback);
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
        return $this->cache->rememberForever($this->prefix . $key, $callback);
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
        return $this->cache->increment($this->prefix . $key, $value);
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
        return $this->cache->decrement($this->prefix . $key, $value);
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
        return $this->cache->forever($this->prefix . $key, $value);
    }

    /**
     * Get multiple items from the cache by key.
     *
     * @param array $keys Array of cache keys
     * @return array Array of key-value pairs
     */
    public function many(array $keys): array
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $results = $this->cache->many($prefixedKeys);
        
        // Remove prefix from result keys using Str helper
        $unprefixed = [];
        foreach ($results as $key => $value) {
            $originalKey = Str::after($key, $this->prefix);
            $unprefixed[$originalKey] = $value;
        }
        
        return $unprefixed;
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
        $prefixed = [];
        foreach ($values as $key => $value) {
            $prefixed[$this->prefix . $key] = $value;
        }
        
        return $this->cache->putMany($prefixed, $ttl);
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return rtrim($this->prefix, ':');
    }

    /**
     * Check if a key matches the prefix pattern.
     *
     * @param string $key
     * @return bool
     */
    public function hasPrefix(string $key): bool
    {
        return Str::startsWith($key, $this->prefix);
    }

    /**
     * Remove the prefix from a key if it exists.
     *
     * @param string $key
     * @return string
     */
    public function removePrefix(string $key): string
    {
        if ($this->hasPrefix($key)) {
            return Str::after($key, $this->prefix);
        }
        
        return $key;
    }
}