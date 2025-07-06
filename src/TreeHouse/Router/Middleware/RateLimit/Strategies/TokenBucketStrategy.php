<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies;

use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitResult;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Token Bucket Rate Limiting Strategy
 *
 * Implements a token bucket algorithm that allows for burst traffic
 * while maintaining an average rate limit. Tokens are added to the bucket
 * at a steady rate, and each request consumes one token.
 *
 * This strategy is ideal for APIs that need to allow occasional bursts
 * while preventing sustained high traffic.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class TokenBucketStrategy implements RateLimitStrategyInterface
{
    /**
     * Strategy configuration
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Create a new token bucket strategy
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
     * @param int $limit Maximum requests allowed (bucket capacity)
     * @param int $windowSeconds Refill interval in seconds
     * @return RateLimitResult Result of the rate limit check
     */
    public function checkLimit(
        CacheInterface $cache,
        string $key,
        int $limit,
        int $windowSeconds
    ): RateLimitResult {
        $now = time();
        $cacheKey = $this->createCacheKey($key);
        
        // Get current bucket state
        $bucketState = $this->getBucketState($cache, $cacheKey);
        
        // Calculate tokens to add based on time elapsed
        $tokensToAdd = $this->calculateTokensToAdd($bucketState, $now, $limit, $windowSeconds);
        
        // Update bucket with new tokens
        $currentTokens = min($limit, $bucketState['tokens'] + $tokensToAdd);
        
        // Check if request can be served
        if ($currentTokens < 1) {
            // No tokens available, calculate retry after
            $refillRate = $limit / $windowSeconds; // tokens per second
            $timeToNextToken = (1 - $currentTokens) / $refillRate;
            $retryAfter = max(1, (int) ceil($timeToNextToken));
            
            // Update bucket state without consuming token
            $this->storeBucketState($cache, $cacheKey, [
                'tokens' => $currentTokens,
                'last_refill' => $now,
            ], $windowSeconds);
            
            return RateLimitResult::exceeded(
                limit: $limit,
                resetTime: $now + $retryAfter,
                retryAfter: $retryAfter,
                key: $key,
                strategy: $this->getName()
            );
        }
        
        // Consume one token
        $remainingTokens = $currentTokens - 1;
        
        // Store updated bucket state
        $this->storeBucketState($cache, $cacheKey, [
            'tokens' => $remainingTokens,
            'last_refill' => $now,
        ], $windowSeconds);
        
        // Calculate when bucket will be full again
        $refillRate = $limit / $windowSeconds;
        $tokensNeeded = $limit - $remainingTokens;
        $timeToFull = $tokensNeeded / $refillRate;
        $resetTime = $now + (int) ceil($timeToFull);
        
        return RateLimitResult::allowed(
            limit: $limit,
            remaining: (int) floor($remainingTokens),
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
        return 'token_bucket';
    }

    /**
     * Get strategy-specific configuration options
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'cache_prefix' => 'rate_limit_token_bucket',
            'ttl_buffer' => 300, // Extra seconds for cache TTL
            'initial_tokens' => null, // Start with full bucket if null
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
     * Create a cache key for the token bucket
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
     * Get bucket state from cache
     *
     * @param CacheInterface $cache Cache instance
     * @param string $cacheKey Cache key
     * @return array<string, mixed> Bucket state
     */
    private function getBucketState(CacheInterface $cache, string $cacheKey): array
    {
        $state = $cache->get($cacheKey, null);
        
        if (!is_array($state) || !isset($state['tokens'], $state['last_refill'])) {
            // Initialize new bucket
            $initialTokens = $this->config['initial_tokens'] ?? 0; // Start empty by default
            return [
                'tokens' => $initialTokens,
                'last_refill' => time(),
            ];
        }
        
        return [
            'tokens' => (float) $state['tokens'],
            'last_refill' => (int) $state['last_refill'],
        ];
    }

    /**
     * Store bucket state in cache
     *
     * @param CacheInterface $cache Cache instance
     * @param string $cacheKey Cache key
     * @param array<string, mixed> $state Bucket state
     * @param int $windowSeconds Window size in seconds
     * @return void
     */
    private function storeBucketState(CacheInterface $cache, string $cacheKey, array $state, int $windowSeconds): void
    {
        // Set TTL to prevent stale buckets
        $ttl = $windowSeconds * 2 + $this->config['ttl_buffer'];
        $cache->put($cacheKey, $state, $ttl);
    }

    /**
     * Calculate tokens to add based on elapsed time
     *
     * @param array<string, mixed> $bucketState Current bucket state
     * @param int $now Current timestamp
     * @param int $capacity Bucket capacity (max tokens)
     * @param int $refillPeriod Refill period in seconds
     * @return float Tokens to add
     */
    private function calculateTokensToAdd(array $bucketState, int $now, int $capacity, int $refillPeriod): float
    {
        $timeSinceRefill = $now - $bucketState['last_refill'];
        
        if ($timeSinceRefill <= 0) {
            return 0;
        }
        
        // Calculate refill rate (tokens per second)
        $refillRate = $capacity / $refillPeriod;
        
        // Calculate tokens to add
        return $timeSinceRefill * $refillRate;
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
        $cacheKey = $this->createCacheKey($key);
        $bucketState = $this->getBucketState($cache, $cacheKey);
        
        // Calculate current tokens including refill
        $tokensToAdd = $this->calculateTokensToAdd($bucketState, $now, $windowSeconds, $windowSeconds);
        $currentTokens = min($windowSeconds, $bucketState['tokens'] + $tokensToAdd);
        
        $refillRate = $windowSeconds / $windowSeconds; // tokens per second
        $timeToFullBucket = ($windowSeconds - $currentTokens) / $refillRate;
        
        return [
            'current_tokens' => $currentTokens,
            'bucket_capacity' => $windowSeconds,
            'refill_rate' => $refillRate,
            'last_refill' => $bucketState['last_refill'],
            'time_to_full_bucket' => $timeToFullBucket,
            'next_token_in_seconds' => $currentTokens < 1 ? (1 - $currentTokens) / $refillRate : 0,
        ];
    }

    /**
     * Get window information for the token bucket
     *
     * @param int $windowSeconds Refill period in seconds
     * @return array<string, int>
     */
    public function getWindowInfo(int $windowSeconds): array
    {
        $now = time();
        
        return [
            'refill_period' => $windowSeconds,
            'current_time' => $now,
            'tokens_per_second' => 1.0 / $windowSeconds,
        ];
    }

    /**
     * Reset bucket to full capacity
     *
     * @param CacheInterface $cache Cache instance
     * @param string $key Rate limit key
     * @param int $capacity Bucket capacity
     * @param int $windowSeconds Window size in seconds
     * @return bool True if bucket was reset
     */
    public function resetBucket(CacheInterface $cache, string $key, int $capacity, int $windowSeconds): bool
    {
        $cacheKey = $this->createCacheKey($key);
        
        $this->storeBucketState($cache, $cacheKey, [
            'tokens' => $capacity,
            'last_refill' => time(),
        ], $windowSeconds);
        
        return true;
    }
}