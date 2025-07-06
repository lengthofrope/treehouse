<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit;

use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthorizationException;

/**
 * Rate Limiting Middleware
 *
 * Main middleware class that implements rate limiting for HTTP requests.
 * Supports multiple strategies, flexible key resolution, and integrates
 * with TreeHouse's existing cache and error handling systems.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Rate limit manager
     */
    private RateLimitManager $manager;

    /**
     * Headers manager
     */
    private RateLimitHeaders $headers;

    /**
     * Cache manager
     */
    private CacheManager $cache;

    /**
     * Middleware configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Rate limit configuration
     */
    private ?RateLimitConfig $rateLimitConfig = null;

    /**
     * Create a new rate limiting middleware
     *
     * @param mixed ...$args Middleware arguments (limit, window, strategy, key_resolver)
     */
    public function __construct(...$args)
    {
        // Initialize cache manager
        $this->cache = cache();

        // Parse middleware arguments
        if (count($args) === 1 && is_array($args[0])) {
            // Old style: config array
            $this->config = $args[0];
        } else {
            // New style: parameters as arguments
            $this->config = $this->parseArguments($args);
        }

        // Initialize components
        $this->manager = new RateLimitManager($this->config);
        $this->headers = new RateLimitHeaders($this->config['headers'] ?? []);

        // Parse rate limit configuration if parameters provided
        if (isset($this->config['parameters'])) {
            $this->rateLimitConfig = RateLimitConfig::fromParameters($this->config['parameters']);
        }
    }

    /**
     * Handle the request
     *
     * @param Request $request HTTP request
     * @param callable $next Next middleware
     * @return Response HTTP response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Skip rate limiting if disabled
        if (!$this->isEnabled()) {
            return $next($request);
        }

        // Get rate limit configuration
        $config = $this->getRateLimitConfig($request);
        if ($config === null) {
            return $next($request);
        }

        try {
            // Check rate limit
            $result = $this->manager->checkRateLimit($request, $this->cache->driver(), $config);

            // If limit exceeded, throw exception
            if ($result->isExceeded()) {
                $this->handleRateLimitExceeded($result, $request);
            }

            // Continue to next middleware
            $response = $next($request);

            // Add rate limiting headers to response
            return $this->headers->addHeaders($response, $result);

        } catch (\Exception $e) {
            // If rate limiting fails, log error and continue
            if (function_exists('error_log')) {
                error_log("Rate limiting error: " . $e->getMessage());
            }

            // In case of errors, allow the request to continue
            return $next($request);
        }
    }

    /**
     * Handle rate limit exceeded
     *
     * @param RateLimitResult $result Rate limit result
     * @param Request $request HTTP request
     * @throws AuthorizationException
     */
    private function handleRateLimitExceeded(RateLimitResult $result, Request $request): void
    {
        $limitDetails = [
            'limit' => $result->getLimit(),
            'reset_time' => $result->getResetTime(),
            'key' => $result->getKey(),
            'strategy' => $result->getStrategy(),
        ];

        $userIdentifier = $this->getUserIdentifier($request);

        throw AuthorizationException::rateLimitExceeded(
            limit: "requests per window",
            userIdentifier: $userIdentifier,
            retryAfterSeconds: $result->getRetryAfter(),
            limitDetails: $limitDetails
        );
    }

    /**
     * Get user identifier for rate limiting
     *
     * @param Request $request HTTP request
     * @return string|null User identifier
     */
    private function getUserIdentifier(Request $request): ?string
    {
        // Try to get authenticated user ID
        if (function_exists('auth')) {
            $authManager = auth();
            if ($authManager && $authManager->check()) {
                $user = $authManager->user();
                if ($user && method_exists($user, 'getId')) {
                    return (string) $user->getId();
                }
            }
        }

        // Fall back to IP address
        return $request->ip();
    }

    /**
     * Parse middleware arguments
     *
     * @param array<mixed> $args Arguments
     * @return array<string, mixed>
     */
    private function parseArguments(array $args): array
    {
        if (empty($args)) {
            return [];
        }

        // If first argument is a string, treat as parameters
        if (is_string($args[0])) {
            return ['parameters' => $args[0]];
        }

        // Otherwise, treat as configuration array
        return is_array($args[0]) ? $args[0] : [];
    }

    /**
     * Get rate limit configuration
     *
     * @param Request $request HTTP request
     * @return RateLimitConfig|null Rate limit configuration
     */
    private function getRateLimitConfig(Request $request): ?RateLimitConfig
    {
        // Use pre-parsed configuration if available
        if ($this->rateLimitConfig !== null) {
            return $this->rateLimitConfig;
        }

        // Try to get from request query parameters (for backward compatibility)
        $parameters = $request->query('_rate_limit');
        if ($parameters) {
            return RateLimitConfig::fromParameters($parameters);
        }

        return null;
    }

    /**
     * Check if rate limiting is enabled
     */
    private function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Set middleware configuration
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->manager->setConfig($config);
    }

    /**
     * Get middleware configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get rate limit manager
     */
    public function getManager(): RateLimitManager
    {
        return $this->manager;
    }

    /**
     * Get headers manager
     */
    public function getHeaders(): RateLimitHeaders
    {
        return $this->headers;
    }

    /**
     * Create middleware instance from parameters string
     *
     * @param string $parameters Parameters string (e.g., "60,1,fixed,ip")
     * @return static
     */
    public static function fromParameters(string $parameters): static
    {
        return new static($parameters);
    }

    /**
     * Create middleware instance with configuration
     *
     * @param array<string, mixed> $config Configuration array
     * @return static
     */
    public static function withConfig(array $config): static
    {
        return new static($config);
    }

    /**
     * Get rate limiting statistics for debugging
     *
     * @param Request $request HTTP request
     * @return array<string, mixed>|null Statistics or null if not available
     */
    public function getStatistics(Request $request): ?array
    {
        $config = $this->getRateLimitConfig($request);
        if ($config === null) {
            return null;
        }

        try {
            $keyResolver = $this->manager->getKeyResolver($config->getKeyResolver());
            $key = $keyResolver->resolveKey($request);
            
            if ($key === null) {
                return null;
            }

            return [
                'key' => $key,
                'strategy' => $config->getStrategy(),
                'limit' => $config->getLimit(),
                'window' => $config->getWindow(),
                'key_resolver' => $config->getKeyResolver(),
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}