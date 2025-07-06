<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\HttpException;

/**
 * Exception thrown when rate limit is exceeded
 * 
 * Provides structured information about rate limit violations
 * including remaining time and current limits.
 * 
 * @package LengthOfRope\TreeHouse\Router\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitExceededException extends HttpException
{
    /**
     * Error code for rate limit violations
     */
    protected string $errorCode = 'RATE_001';

    /**
     * Create a new rate limit exceeded exception
     *
     * @param int $limit Number of requests allowed
     * @param int $windowInMinutes Time window in minutes 
     * @param int $retryAfter Seconds to wait before retry
     * @param string $identifier Rate limit identifier (IP, user, etc.)
     */
    public function __construct(
        int $limit,
        int $windowInMinutes,
        int $retryAfter,
        string $identifier = 'request'
    ) {
        $message = "Rate limit exceeded for {$identifier}. Limit: {$limit} requests per {$windowInMinutes} minute(s). Try again in {$retryAfter} seconds.";
        
        $headers = [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + $retryAfter),
            'Retry-After' => (string) $retryAfter,
        ];

        $context = [
            'rate_limit' => [
                'limit' => $limit,
                'window_minutes' => $windowInMinutes,
                'retry_after' => $retryAfter,
                'identifier' => $identifier,
                'reset_time' => time() + $retryAfter,
            ]
        ];

        parent::__construct(429, $message, $headers, null, $context);
    }

    /**
     * Get the rate limit information
     *
     * @return array<string, mixed>
     */
    public function getRateLimitInfo(): array
    {
        return $this->context['rate_limit'] ?? [];
    }
}