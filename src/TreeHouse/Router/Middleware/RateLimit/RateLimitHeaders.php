<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit;

use LengthOfRope\TreeHouse\Http\Response;

/**
 * Rate Limit Headers
 *
 * Manages HTTP headers for rate limiting responses, including standard
 * X-RateLimit-* headers and Retry-After header for exceeded limits.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitHeaders
{
    /**
     * Standard rate limiting header names
     */
    private const HEADER_LIMIT = 'X-RateLimit-Limit';
    private const HEADER_REMAINING = 'X-RateLimit-Remaining';
    private const HEADER_RESET = 'X-RateLimit-Reset';
    private const HEADER_RETRY_AFTER = 'Retry-After';

    /**
     * Custom header configuration
     *
     * @var array<string, string>
     */
    private array $headerNames;

    /**
     * Create a new rate limit headers manager
     *
     * @param array<string, string> $headerNames Custom header names
     */
    public function __construct(array $headerNames = [])
    {
        $this->headerNames = array_merge([
            'limit' => self::HEADER_LIMIT,
            'remaining' => self::HEADER_REMAINING,
            'reset' => self::HEADER_RESET,
            'retry_after' => self::HEADER_RETRY_AFTER,
        ], $headerNames);
    }

    /**
     * Add rate limiting headers to a response
     *
     * @param Response $response The response to modify
     * @param RateLimitResult $result The rate limit result
     * @return Response The modified response
     */
    public function addHeaders(Response $response, RateLimitResult $result): Response
    {
        // Always add limit and reset headers
        $response->setHeader($this->headerNames['limit'], (string) $result->getLimit());
        $response->setHeader($this->headerNames['reset'], (string) $result->getResetTime());

        // Add remaining header (0 if exceeded)
        $response->setHeader($this->headerNames['remaining'], (string) $result->getRemaining());

        // Add retry-after header if limit exceeded
        if ($result->isExceeded() && $result->getRetryAfter() !== null) {
            $response->setHeader($this->headerNames['retry_after'], (string) $result->getRetryAfter());
        }

        return $response;
    }

    /**
     * Get headers as an array
     *
     * @param RateLimitResult $result The rate limit result
     * @return array<string, string>
     */
    public function getHeadersArray(RateLimitResult $result): array
    {
        $headers = [
            $this->headerNames['limit'] => (string) $result->getLimit(),
            $this->headerNames['remaining'] => (string) $result->getRemaining(),
            $this->headerNames['reset'] => (string) $result->getResetTime(),
        ];

        // Add retry-after header if limit exceeded
        if ($result->isExceeded() && $result->getRetryAfter() !== null) {
            $headers[$this->headerNames['retry_after']] = (string) $result->getRetryAfter();
        }

        return $headers;
    }

    /**
     * Get the header name for a specific type
     *
     * @param string $type Header type (limit, remaining, reset, retry_after)
     * @return string|null The header name or null if not found
     */
    public function getHeaderName(string $type): ?string
    {
        return $this->headerNames[$type] ?? null;
    }

    /**
     * Set custom header names
     *
     * @param array<string, string> $headerNames Custom header names
     * @return void
     */
    public function setHeaderNames(array $headerNames): void
    {
        $this->headerNames = array_merge($this->headerNames, $headerNames);
    }

    /**
     * Get all configured header names
     *
     * @return array<string, string>
     */
    public function getHeaderNames(): array
    {
        return $this->headerNames;
    }

    /**
     * Create headers for a successful request
     *
     * @param int $limit The rate limit
     * @param int $remaining Remaining requests
     * @param int $resetTime Reset timestamp
     * @return array<string, string>
     */
    public function createSuccessHeaders(int $limit, int $remaining, int $resetTime): array
    {
        return [
            $this->headerNames['limit'] => (string) $limit,
            $this->headerNames['remaining'] => (string) $remaining,
            $this->headerNames['reset'] => (string) $resetTime,
        ];
    }

    /**
     * Create headers for an exceeded limit
     *
     * @param int $limit The rate limit
     * @param int $resetTime Reset timestamp
     * @param int $retryAfter Seconds to wait before retrying
     * @return array<string, string>
     */
    public function createExceededHeaders(int $limit, int $resetTime, int $retryAfter): array
    {
        return [
            $this->headerNames['limit'] => (string) $limit,
            $this->headerNames['remaining'] => '0',
            $this->headerNames['reset'] => (string) $resetTime,
            $this->headerNames['retry_after'] => (string) $retryAfter,
        ];
    }
}