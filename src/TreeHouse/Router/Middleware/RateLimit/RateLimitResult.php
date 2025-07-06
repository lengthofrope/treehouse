<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit;

/**
 * Rate Limit Result
 *
 * Encapsulates the result of a rate limit check, including whether the limit
 * was exceeded, current usage statistics, and timing information.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitResult
{
    /**
     * Create a new rate limit result
     *
     * @param bool $allowed Whether the request is allowed
     * @param int $limit The rate limit (requests per window)
     * @param int $remaining Remaining requests in current window
     * @param int $resetTime Unix timestamp when window resets
     * @param int|null $retryAfter Seconds to wait before retrying (when limited)
     * @param string|null $key The rate limit key used
     * @param string|null $strategy The strategy used
     */
    public function __construct(
        private bool $allowed,
        private int $limit,
        private int $remaining,
        private int $resetTime,
        private ?int $retryAfter = null,
        private ?string $key = null,
        private ?string $strategy = null
    ) {}

    /**
     * Check if the request is allowed
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Check if the rate limit was exceeded
     */
    public function isExceeded(): bool
    {
        return !$this->allowed;
    }

    /**
     * Get the rate limit (requests per window)
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get remaining requests in current window
     */
    public function getRemaining(): int
    {
        return $this->remaining;
    }

    /**
     * Get the unix timestamp when the window resets
     */
    public function getResetTime(): int
    {
        return $this->resetTime;
    }

    /**
     * Get seconds to wait before retrying (when limited)
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the rate limit key used
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Get the strategy used
     */
    public function getStrategy(): ?string
    {
        return $this->strategy;
    }

    /**
     * Convert to array for debugging/logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'reset_time' => $this->resetTime,
            'retry_after' => $this->retryAfter,
            'key' => $this->key,
            'strategy' => $this->strategy,
        ];
    }

    /**
     * Create a result for an allowed request
     */
    public static function allowed(
        int $limit,
        int $remaining,
        int $resetTime,
        ?string $key = null,
        ?string $strategy = null
    ): self {
        return new self(
            allowed: true,
            limit: $limit,
            remaining: $remaining,
            resetTime: $resetTime,
            retryAfter: null,
            key: $key,
            strategy: $strategy
        );
    }

    /**
     * Create a result for an exceeded limit
     */
    public static function exceeded(
        int $limit,
        int $resetTime,
        int $retryAfter,
        ?string $key = null,
        ?string $strategy = null
    ): self {
        return new self(
            allowed: false,
            limit: $limit,
            remaining: 0,
            resetTime: $resetTime,
            retryAfter: $retryAfter,
            key: $key,
            strategy: $strategy
        );
    }
}