<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router;

use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Rate Limiter
 * 
 * Implements token bucket algorithm for rate limiting using the cache layer.
 * Provides efficient request throttling with automatic cleanup.
 * 
 * @package LengthOfRope\TreeHouse\Router
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimiter
{
    /**
     * Cache instance for storing rate limit data
     */
    private CacheInterface $cache;

    /**
     * Create a new rate limiter instance
     *
     * @param CacheInterface $cache Cache implementation
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Attempt to consume a token from the rate limiter
     *
     * @param string $key Rate limit key (e.g., IP address, user ID)
     * @param int $limit Maximum number of requests allowed
     * @param int $windowInMinutes Time window in minutes
     * @return array{allowed: bool, current: int, remaining: int, reset_time: int, retry_after: int}
     */
    public function attempt(string $key, int $limit, int $windowInMinutes): array
    {
        $cacheKey = $this->getCacheKey($key);
        $windowInSeconds = $windowInMinutes * 60;
        $now = time();

        // Get current bucket data
        $bucket = $this->cache->get($cacheKey, [
            'count' => 0,
            'window_start' => $now
        ]);

        // Check if we need to reset the window
        $timeSinceWindowStart = $now - $bucket['window_start'];
        if ($timeSinceWindowStart >= $windowInSeconds) {
            // Reset the bucket for new window
            $bucket = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        $current = $bucket['count'];
        $remaining = max(0, $limit - $current);
        $resetTime = $bucket['window_start'] + $windowInSeconds;
        $retryAfter = max(0, $resetTime - $now);

        // Check if request is allowed
        if ($current >= $limit) {
            return [
                'allowed' => false,
                'current' => $current,
                'remaining' => 0,
                'reset_time' => $resetTime,
                'retry_after' => $retryAfter
            ];
        }

        // Consume token
        $bucket['count']++;
        
        // Store updated bucket with TTL
        $this->cache->put($cacheKey, $bucket, $windowInSeconds + 60); // Extra 60s for cleanup

        return [
            'allowed' => true,
            'current' => $bucket['count'],
            'remaining' => $limit - $bucket['count'],
            'reset_time' => $resetTime,
            'retry_after' => 0
        ];
    }

    /**
     * Get current rate limit status without consuming a token
     *
     * @param string $key Rate limit key
     * @param int $limit Maximum number of requests allowed
     * @param int $windowInMinutes Time window in minutes
     * @return array{current: int, remaining: int, reset_time: int}
     */
    public function getStatus(string $key, int $limit, int $windowInMinutes): array
    {
        $cacheKey = $this->getCacheKey($key);
        $windowInSeconds = $windowInMinutes * 60;
        $now = time();

        $bucket = $this->cache->get($cacheKey, [
            'count' => 0,
            'window_start' => $now
        ]);

        // Check if window has expired
        $timeSinceWindowStart = $now - $bucket['window_start'];
        if ($timeSinceWindowStart >= $windowInSeconds) {
            return [
                'current' => 0,
                'remaining' => $limit,
                'reset_time' => $now + $windowInSeconds
            ];
        }

        $current = $bucket['count'];
        $resetTime = $bucket['window_start'] + $windowInSeconds;

        return [
            'current' => $current,
            'remaining' => max(0, $limit - $current),
            'reset_time' => $resetTime
        ];
    }

    /**
     * Clear rate limit for a specific key
     *
     * @param string $key Rate limit key
     * @return bool Success status
     */
    public function clear(string $key): bool
    {
        $cacheKey = $this->getCacheKey($key);
        return $this->cache->forget($cacheKey);
    }

    /**
     * Clear all rate limits
     *
     * @return bool Success status
     */
    public function clearAll(): bool
    {
        // Note: This is a simple implementation. In production,
        // you might want to use cache tags or prefixes for more efficient bulk deletion
        return $this->cache->flush();
    }

    /**
     * Generate cache key for rate limiting
     *
     * @param string $key Base key
     * @return string Cache key
     */
    private function getCacheKey(string $key): string
    {
        return 'rate_limit:' . md5($key);
    }
}