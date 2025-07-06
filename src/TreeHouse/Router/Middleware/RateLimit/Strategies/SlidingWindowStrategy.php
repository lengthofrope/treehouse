<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies;

use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitResult;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Sliding Window Rate Limiting Strategy
 *
 * Implements a sliding window rate limiting algorithm that provides more
 * precise rate limiting than fixed windows. It tracks request timestamps
 * and only counts requests within the sliding time window.
 *
 * This approach eliminates the "burst at window boundary" problem of
 * fixed windows but requires more memory to store request timestamps.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SlidingWindowStrategy implements RateLimitStrategyInterface
{
    /**
     * Strategy configuration
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Create a new sliding window strategy
     *
     * @param array<string, mixed> $config Strategy configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Check if a request is allowed under the rate limit
     *
     * @param CacheInterface $cache Cache instance for storing counters
     * @param string $key Unique key for this rate limit
     * @param int $limit Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return RateLimitResult Result of the rate limit check
     */
    public function checkLimit(
        CacheInterface $cache,
        string $key,
        int $limit,
        int $windowSeconds
    ): RateLimitResult {
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Create cache key for this sliding window
        $cacheKey = $this->createCacheKey($key);
        
        // Get current request timestamps
        $timestamps = $this->getTimestamps($cache, $cacheKey);
        
        // Remove expired timestamps (outside the sliding window)
        $validTimestamps = array_filter($timestamps, fn($timestamp) => $timestamp > $windowStart);
        
        // Check if limit would be exceeded
        if (count($validTimestamps) >= $limit) {
            // Calculate retry after based on oldest timestamp in window
            $oldestTimestamp = min($validTimestamps);
            $retryAfter = ($oldestTimestamp + $windowSeconds) - $now;
            
            return RateLimitResult::exceeded(
                limit: $limit,
                resetTime: $oldestTimestamp + $windowSeconds,
                retryAfter: max(1, $retryAfter),
                key: $key,
                strategy: $this->getName()
            );
        }
        
        // Add current timestamp
        $validTimestamps[] = $now;
        
        // Store updated timestamps
        $this->storeTimestamps($cache, $cacheKey, $validTimestamps, $windowSeconds);
        
        $remaining = max(0, $limit - count($validTimestamps));
        
        // Calculate reset time (when oldest timestamp expires)
        $resetTime = !empty($validTimestamps) ? min($validTimestamps) + $windowSeconds : $now + $windowSeconds;
        
        return RateLimitResult::allowed(
            limit: $limit,
            remaining: $remaining,
            resetTime: $resetTime,
            key: $key,
            strategy: $this->getName()
        );
    }

    /**
     * Get the strategy name
     */
    public function getName(): string
    {
        return 'sliding';
    }

    /**
     * Get strategy-specific configuration options
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'cache_prefix' => 'rate_limit_sliding',
            'ttl_buffer' => 60, // Extra seconds for cache TTL
            'max_timestamps' => 1000, // Maximum timestamps to store per key
        ];
    }

    /**
     * Set strategy configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Create a cache key for the sliding window
     *
     * @param string $key Base rate limit key
     * @return string Cache key
     */
    private function createCacheKey(string $key): string
    {
        $prefix = $this->config['cache_prefix'];
        return "{$prefix}:{$key}";
    }

    /**
     * Get stored timestamps from cache
     *
     * @param CacheInterface $cache Cache instance
     * @param string $cacheKey Cache key
     * @return array<int> Array of timestamps
     */
    private function getTimestamps(CacheInterface $cache, string $cacheKey): array
    {
        $data = $cache->get($cacheKey, []);
        
        if (!is_array($data)) {
            return [];
        }
        
        // Ensure all values are integers (timestamps)
        return array_map('intval', array_filter($data, 'is_numeric'));
    }

    /**
     * Store timestamps in cache
     *
     * @param CacheInterface $cache Cache instance
     * @param string $cacheKey Cache key
     * @param array<int> $timestamps Array of timestamps
     * @param int $windowSeconds Window size in seconds
     * @return void
     */
    private function storeTimestamps(CacheInterface $cache, string $cacheKey, array $timestamps, int $windowSeconds): void
    {
        // Limit the number of stored timestamps to prevent memory issues
        $maxTimestamps = $this->config['max_timestamps'];
        if (count($timestamps) > $maxTimestamps) {
            // Keep only the most recent timestamps
            rsort($timestamps);
            $timestamps = array_slice($timestamps, 0, $maxTimestamps);
        }
        
        // Set TTL to window duration plus buffer
        $ttl = $windowSeconds + $this->config['ttl_buffer'];
        
        $cache->put($cacheKey, $timestamps, $ttl);
    }

    /**
     * Clear rate limit data for a specific key
     *
     * @param CacheInterface $cache Cache instance
     * @param string $key Rate limit key
     * @param int $windowSeconds Time window in seconds
     * @return bool True if data was cleared
     */
    public function clearLimit(CacheInterface $cache, string $key, int $windowSeconds): bool
    {
        $cacheKey = $this->createCacheKey($key);
        return $cache->forget($cacheKey);
    }

    /**
     * Get current usage for a key
     *
     * @param CacheInterface $cache Cache instance
     * @param string $key Rate limit key
     * @param int $windowSeconds Time window in seconds
     * @return array<string, mixed>
     */
    public function getUsage(CacheInterface $cache, string $key, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        $cacheKey = $this->createCacheKey($key);
        
        $timestamps = $this->getTimestamps($cache, $cacheKey);
        $validTimestamps = array_filter($timestamps, fn($timestamp) => $timestamp > $windowStart);
        
        return [
            'current_count' => count($validTimestamps),
            'window_start' => $windowStart,
            'window_end' => $now,
            'oldest_request' => !empty($validTimestamps) ? min($validTimestamps) : null,
            'newest_request' => !empty($validTimestamps) ? max($validTimestamps) : null,
            'total_stored_timestamps' => count($timestamps),
        ];
    }

    /**
     * Get window information for the sliding window
     *
     * @param int $windowSeconds Time window in seconds
     * @return array<string, int>
     */
    public function getWindowInfo(int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        return [
            'start' => $windowStart,
            'end' => $now,
            'current' => $now,
            'window_size_seconds' => $windowSeconds,
        ];
    }
}