<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies;

use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitResult;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Rate Limit Strategy Interface
 *
 * Defines the contract for rate limiting strategies. Each strategy implements
 * a different algorithm for tracking and enforcing rate limits.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface RateLimitStrategyInterface
{
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
    ): RateLimitResult;

    /**
     * Get the strategy name
     */
    public function getName(): string;

    /**
     * Get strategy-specific configuration options
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array;

    /**
     * Set strategy configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void;
}