<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Router\RateLimiter;
use LengthOfRope\TreeHouse\Router\Exceptions\RateLimitExceededException;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Rate Limit Middleware
 *
 * Provides request rate limiting functionality using configurable limits
 * and time windows. Supports IP-based limiting with plans for user-based
 * and role-based limiting.
 *
 * Usage:
 * - `throttle:60,1` - 60 requests per 1 minute
 * - `throttle:1000,60` - 1000 requests per 60 minutes
 * - `throttle:10,1,ip` - 10 requests per minute per IP (explicit)
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Rate limiter instance
     */
    private RateLimiter $rateLimiter;

    /**
     * Default rate limit configuration
     */
    private array $defaultConfig = [
        'requests' => 60,
        'minutes' => 1,
        'identifier' => 'ip'
    ];

    /**
     * Create a new rate limit middleware instance
     *
     * Parameters are passed directly from middleware string parsing:
     * - throttle:60,1 -> $requests=60, $minutes=1
     * - throttle:60,1,ip -> $requests=60, $minutes=1, $identifier='ip'
     *
     * @param int|null $requests Maximum requests allowed
     * @param int|null $minutes Time window in minutes
     * @param string|null $identifier Rate limit identifier type
     */
    public function __construct(
        ?int $requests = null,
        ?int $minutes = null,
        ?string $identifier = null
    ) {
        // Resolve cache instance
        $cache = $this->resolveCache();
        $this->rateLimiter = new RateLimiter($cache);

        // Override defaults with constructor parameters
        if ($requests !== null) {
            $this->defaultConfig['requests'] = $requests;
        }
        if ($minutes !== null) {
            $this->defaultConfig['minutes'] = $minutes;
        }
        if ($identifier !== null) {
            $this->defaultConfig['identifier'] = $identifier;
        }
    }

    /**
     * Handle the request
     *
     * @param Request $request HTTP request
     * @param callable $next Next middleware
     * @return Response
     * @throws RateLimitExceededException When rate limit is exceeded
     */
    public function handle(Request $request, callable $next): Response
    {
        // Use configuration from constructor (populated by middleware parameter parsing)
        $config = $this->defaultConfig;
        
        // Generate rate limit key
        $key = $this->generateKey($request, $config['identifier']);
        
        // Attempt to consume token
        $result = $this->rateLimiter->attempt($key, $config['requests'], $config['minutes']);
        
        if (!$result['allowed']) {
            throw new RateLimitExceededException(
                $config['requests'],
                $config['minutes'],
                $result['retry_after'],
                $config['identifier']
            );
        }

        // Continue to next middleware
        $response = $next($request);

        // Add rate limit headers to response
        $this->addRateLimitHeaders($response, $config['requests'], $result);

        return $response;
    }

    /**
     * Generate rate limit key based on identifier type
     *
     * @param Request $request HTTP request
     * @param string $identifier Identifier type (ip, user, etc.)
     * @return string Rate limit key
     */
    private function generateKey(Request $request, string $identifier): string
    {
        return match ($identifier) {
            'ip' => $this->getClientIp($request),
            'user' => $this->getUserKey($request),
            default => $this->getClientIp($request) // Default to IP
        };
    }

    /**
     * Get client IP address
     *
     * @param Request $request HTTP request
     * @return string Client IP address
     */
    private function getClientIp(Request $request): string
    {
        // Check for IP in various headers (reverse proxy, load balancer, etc.)
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            $ip = $request->server($header);
            if (!empty($ip) && $ip !== 'unknown') {
                // Handle comma-separated IPs (X-Forwarded-For can have multiple IPs)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR even if it's private (for local development)
        return $request->server('REMOTE_ADDR', 'unknown');
    }

    /**
     * Get user-based key
     *
     * @param Request $request HTTP request
     * @return string User key
     */
    private function getUserKey(Request $request): string
    {
        // Try to get authenticated user ID
        if (function_exists('auth')) {
            $auth = auth();
            if ($auth && $user = $auth->user()) {
                return 'user:' . $user->getId();
            }
        }

        // Fallback to session ID
        $sessionId = $request->cookie('session_id');
        if ($sessionId) {
            return 'session:' . $sessionId;
        }

        // Final fallback to IP
        return 'ip:' . $this->getClientIp($request);
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response HTTP response
     * @param int $limit Request limit
     * @param array $result Rate limit result
     * @return void
     */
    private function addRateLimitHeaders(Response $response, int $limit, array $result): void
    {
        $response->setHeader('X-RateLimit-Limit', (string) $limit);
        $response->setHeader('X-RateLimit-Remaining', (string) $result['remaining']);
        $response->setHeader('X-RateLimit-Reset', (string) $result['reset_time']);
    }

    /**
     * Resolve cache instance
     *
     * @return CacheInterface
     */
    private function resolveCache(): CacheInterface
    {
        // Try to get cache from global cache helper
        try {
            if (function_exists('cache')) {
                $cache = cache();
                if ($cache instanceof CacheInterface) {
                    return $cache;
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors and fallback to file cache
        }

        // Fallback: Create file cache instance
        $cacheDir = sys_get_temp_dir() . '/treehouse_rate_limits';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        return new \LengthOfRope\TreeHouse\Cache\FileCache($cacheDir, 3600);
    }
}