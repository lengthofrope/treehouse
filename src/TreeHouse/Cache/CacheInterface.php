<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Cache;

/**
 * Cache Interface
 *
 * Defines the contract for cache implementations in the TreeHouse framework.
 * Provides methods for storing, retrieving, and managing cached data.
 *
 * @package LengthOfRope\TreeHouse\Cache
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
interface CacheInterface
{
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|null $ttl Time to live in seconds (null for forever)
     * @return bool True on success, false on failure
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key The cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The cached value or default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key The cache key
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache.
     *
     * @param string $key The cache key
     * @return bool True on success, false on failure
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache.
     *
     * @return bool True on success, false on failure
     */
    public function flush(): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string $key The cache key
     * @param int|null $ttl Time to live in seconds
     * @param callable $callback Callback to execute if key doesn't exist
     * @return mixed The cached value or callback result
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed;

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param string $key The cache key
     * @param callable $callback Callback to execute if key doesn't exist
     * @return mixed The cached value or callback result
     */
    public function rememberForever(string $key, callable $callback): mixed;

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key The cache key
     * @param int $value The increment value
     * @return int|false The new value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key The cache key
     * @param int $value The decrement value
     * @return int|false The new value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @return bool True on success, false on failure
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Get multiple items from the cache by key.
     *
     * @param array $keys Array of cache keys
     * @return array Array of key-value pairs
     */
    public function many(array $keys): array;

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array $values Array of key-value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function putMany(array $values, ?int $ttl = null): bool;
}