<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies;

use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitResult;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Fixed Window Rate Limiting Strategy
 *
 * Implements a simple fixed time window rate limiting algorithm.
 * Requests are counted within fixed time windows (e.g., per minute).
 * The window resets at fixed intervals, which can allow burst traffic
 * at window boundaries but is simple and memory efficient.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class FixedWindowStrategy implements RateLimitStrategyInterface
{
    /**
     * Strategy configuration
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Create a new fixed window strategy
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
     * @param int $windowMinutes Time window in minutes
     * @return RateLimitResult Result of the rate limit check
     */
    public function checkLimit(
        CacheInterface $cache,
        string $key,
        int $limit,
        int $windowMinutes
    ): RateLimitResult {
        $now = time();
        $windowSeconds = $windowMinutes * 60;
        
        // Calculate the current window start time
        $windowStart = intval($now / $windowSeconds) * $windowSeconds;
        $windowEnd = $windowStart + $windowSeconds;
        
        // Create cache key for this window
        $cacheKey = $this->createCacheKey($key, $windowStart);
        
        // Get current count for this window
        $currentCount = (int) $cache->get($cacheKey, 0);
        
        // Check if limit would be exceeded
        if ($currentCount >= $limit) {
            $retryAfter = $windowEnd - $now;
            return RateLimitResult::exceeded(
                limit: $limit,
                resetTime: $windowEnd,
                retryAfter: $retryAfter,
                key: $key,
                strategy: $this->getName()
            );
        }
        
        // Increment the counter
        $newCount = $cache->increment($cacheKey, 1);
        
        // If this is the first request in the window, set TTL
        if ($newCount === 1) {
            // Set TTL to window duration plus a small buffer
            $ttl = $windowSeconds + 60;
            $cache->put($cacheKey, 1, $ttl);
        }
        
        $remaining = max(0, $limit - $newCount);
        
        return RateLimitResult::allowed(
            limit: $limit,
            remaining: $remaining,
            resetTime: $windowEnd,
            key: $key,
            strategy: $this->getName()
        );
    }

    /**
     * Get the strategy name
     */
    public function getName(): string
    {
        return 'fixed';
    }

    /**
     * Get strategy-specific configuration options
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'cache_prefix' => 'rate_limit_fixed',
            'ttl_buffer' => 60, // Extra seconds for cache TTL
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
     * Create a cache key for the current window
     *
     * @param string $key Base rate limit key
     * @param int $windowStart Window start timestamp
     * @return string Cache key
     */
    private function createCacheKey(string $key, int $windowStart): string
    {
        $prefix = $this->config['cache_prefix'];
        return "{$prefix}:{$key}:{$windowStart}";
    }

    /**
     * Get information about the current window
     *
     * @param int $windowMinutes Time window in minutes
     * @return array<string, int>
     */
    public function getWindowInfo(int $windowMinutes): array
    {
        $now = time();
        $windowSeconds = $windowMinutes * 60;
        $windowStart = intval($now / $windowSeconds) * $windowSeconds;
        $windowEnd = $windowStart + $windowSeconds;
        
        return [
            'start' => $windowStart,
            'end' => $windowEnd,
            'current' => $now,
            'remaining_seconds' => $windowEnd - $now,
        ];
    }

    /**
     * Clear rate limit data for a specific key
     *
     * @param CacheInterface $cache Cache instance
     * @param string $key Rate limit key
     * @param int $windowMinutes Time window in minutes
     * @return bool True if data was cleared
     */
    public function clearLimit(CacheInterface $cache, string $key, int $windowMinutes): bool
    {
        $windowInfo = $this->getWindowInfo($windowMinutes);
        $cacheKey = $this->createCacheKey($key, $windowInfo['start']);
        
        return $cache->forget($cacheKey);
    }

    /**
     * Get current usage for a key
     *
     * @param CacheInterface $cache Cache instance
     * @param string $key Rate limit key
     * @param int $windowMinutes Time window in minutes
     * @return array<string, mixed>
     */
    public function getUsage(CacheInterface $cache, string $key, int $windowMinutes): array
    {
        $windowInfo = $this->getWindowInfo($windowMinutes);
        $cacheKey = $this->createCacheKey($key, $windowInfo['start']);
        $currentCount = (int) $cache->get($cacheKey, 0);
        
        return [
            'current_count' => $currentCount,
            'window_start' => $windowInfo['start'],
            'window_end' => $windowInfo['end'],
            'remaining_seconds' => $windowInfo['remaining_seconds'],
        ];
    }
}