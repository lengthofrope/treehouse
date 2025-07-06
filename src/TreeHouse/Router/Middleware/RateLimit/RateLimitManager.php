<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit;

use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\RateLimitStrategyInterface;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\FixedWindowStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\SlidingWindowStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\TokenBucketStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\KeyResolverInterface;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\IpKeyResolver;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\UserKeyResolver;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\CompositeKeyResolver;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\HeaderKeyResolver;
use LengthOfRope\TreeHouse\Cache\CacheInterface;
use LengthOfRope\TreeHouse\Http\Request;

/**
 * Rate Limit Manager
 *
 * Orchestrates rate limiting by coordinating strategies and key resolvers.
 * Manages the creation and configuration of rate limiting components.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitManager
{
    /**
     * Available strategies
     *
     * @var array<string, class-string<RateLimitStrategyInterface>>
     */
    private array $strategies = [
        'fixed' => FixedWindowStrategy::class,
        'sliding' => SlidingWindowStrategy::class,
        'token_bucket' => TokenBucketStrategy::class,
    ];

    /**
     * Available key resolvers
     *
     * @var array<string, class-string<KeyResolverInterface>>
     */
    private array $keyResolvers = [
        'ip' => IpKeyResolver::class,
        'user' => UserKeyResolver::class,
        'composite' => CompositeKeyResolver::class,
        'header' => HeaderKeyResolver::class,
    ];

    /**
     * Strategy instances cache
     *
     * @var array<string, RateLimitStrategyInterface>
     */
    private array $strategyInstances = [];

    /**
     * Key resolver instances cache
     *
     * @var array<string, KeyResolverInterface>
     */
    private array $keyResolverInstances = [];

    /**
     * Manager configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new rate limit manager
     *
     * @param array<string, mixed> $config Manager configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_strategy' => 'fixed',
            'default_key_resolver' => 'ip',
            'cache_prefix' => 'rate_limit',
        ], $config);
    }

    /**
     * Check rate limit for a request
     *
     * @param Request $request HTTP request
     * @param CacheInterface $cache Cache instance
     * @param RateLimitConfig $config Rate limit configuration
     * @return RateLimitResult Rate limit check result
     */
    public function checkRateLimit(
        Request $request,
        CacheInterface $cache,
        RateLimitConfig $config
    ): RateLimitResult {
        // Handle multiple limits
        if ($config->hasMultipleLimits()) {
            return $this->checkMultipleLimits($request, $cache, $config);
        }

        // Single limit check
        return $this->checkSingleLimit($request, $cache, $config);
    }

    /**
     * Check a single rate limit
     *
     * @param Request $request HTTP request
     * @param CacheInterface $cache Cache instance
     * @param RateLimitConfig $config Rate limit configuration
     * @return RateLimitResult Rate limit check result
     */
    private function checkSingleLimit(
        Request $request,
        CacheInterface $cache,
        RateLimitConfig $config
    ): RateLimitResult {
        // Resolve the rate limiting key
        $keyResolver = $this->getKeyResolver($config->getKeyResolver());
        $key = $keyResolver->resolveKey($request);
        
        if ($key === null) {
            // If key cannot be resolved, allow the request
            return RateLimitResult::allowed(
                limit: $config->getLimit(),
                remaining: $config->getLimit(),
                resetTime: time() + $config->getWindow()
            );
        }

        // Get the strategy and check the limit
        $strategy = $this->getStrategy($config->getStrategy());
        
        return $strategy->checkLimit(
            cache: $cache,
            key: $key,
            limit: $config->getLimit(),
            windowSeconds: $config->getWindow()
        );
    }

    /**
     * Check multiple rate limits (OR logic - any limit can be exceeded)
     *
     * @param Request $request HTTP request
     * @param CacheInterface $cache Cache instance
     * @param RateLimitConfig $config Rate limit configuration
     * @return RateLimitResult Rate limit check result
     */
    private function checkMultipleLimits(
        Request $request,
        CacheInterface $cache,
        RateLimitConfig $config
    ): RateLimitResult {
        $results = [];
        
        foreach ($config->getLimits() as $limitConfig) {
            $singleConfig = new RateLimitConfig($limitConfig);
            $result = $this->checkSingleLimit($request, $cache, $singleConfig);
            $results[] = $result;
            
            // If any limit is exceeded, return that result
            if ($result->isExceeded()) {
                return $result;
            }
        }

        // All limits passed, return the most restrictive one
        return $this->getMostRestrictiveResult($results);
    }

    /**
     * Get the most restrictive result from multiple results
     *
     * @param array<RateLimitResult> $results Rate limit results
     * @return RateLimitResult Most restrictive result
     */
    private function getMostRestrictiveResult(array $results): RateLimitResult
    {
        if (empty($results)) {
            throw new \InvalidArgumentException('No rate limit results provided');
        }

        // Find the result with the lowest remaining count
        $mostRestrictive = $results[0];
        
        foreach ($results as $result) {
            if ($result->getRemaining() < $mostRestrictive->getRemaining()) {
                $mostRestrictive = $result;
            }
        }

        return $mostRestrictive;
    }

    /**
     * Get a strategy instance
     *
     * @param string $strategyName Strategy name
     * @return RateLimitStrategyInterface Strategy instance
     */
    public function getStrategy(string $strategyName): RateLimitStrategyInterface
    {
        if (!isset($this->strategyInstances[$strategyName])) {
            if (!isset($this->strategies[$strategyName])) {
                throw new \InvalidArgumentException("Unknown rate limiting strategy: {$strategyName}");
            }

            $strategyClass = $this->strategies[$strategyName];
            $this->strategyInstances[$strategyName] = new $strategyClass();
        }

        return $this->strategyInstances[$strategyName];
    }

    /**
     * Get a key resolver instance
     *
     * @param array<string, mixed>|string $keyResolverConfig Key resolver configuration
     * @return KeyResolverInterface Key resolver instance
     */
    public function getKeyResolver(array|string $keyResolverConfig): KeyResolverInterface
    {
        if (is_string($keyResolverConfig)) {
            $keyResolverConfig = ['type' => $keyResolverConfig];
        }

        $type = $keyResolverConfig['type'] ?? $this->config['default_key_resolver'];
        $cacheKey = $type . '_' . md5(serialize($keyResolverConfig));

        if (!isset($this->keyResolverInstances[$cacheKey])) {
            $this->keyResolverInstances[$cacheKey] = $this->createKeyResolver($keyResolverConfig);
        }

        return $this->keyResolverInstances[$cacheKey];
    }

    /**
     * Create a key resolver instance
     *
     * @param array<string, mixed> $config Key resolver configuration
     * @return KeyResolverInterface Key resolver instance
     */
    private function createKeyResolver(array $config): KeyResolverInterface
    {
        $type = $config['type'] ?? $this->config['default_key_resolver'];

        switch ($type) {
            case 'ip':
                return new IpKeyResolver($config);
                
            case 'user':
                return new UserKeyResolver($config);
                
            case 'composite':
                return new CompositeKeyResolver($config);
                
            case 'header':
                return new HeaderKeyResolver($config);
                
            case 'custom':
                $className = $config['class'] ?? null;
                if (!$className || !class_exists($className)) {
                    throw new \InvalidArgumentException("Custom key resolver class not found: {$className}");
                }
                return new $className($config);
                
            default:
                if (!isset($this->keyResolvers[$type])) {
                    throw new \InvalidArgumentException("Unknown key resolver type: {$type}");
                }
                
                $resolverClass = $this->keyResolvers[$type];
                return new $resolverClass($config);
        }
    }

    /**
     * Register a custom strategy
     *
     * @param string $name Strategy name
     * @param class-string<RateLimitStrategyInterface> $className Strategy class
     * @return void
     */
    public function registerStrategy(string $name, string $className): void
    {
        if (!is_subclass_of($className, RateLimitStrategyInterface::class)) {
            throw new \InvalidArgumentException(
                "Strategy class must implement RateLimitStrategyInterface: {$className}"
            );
        }

        $this->strategies[$name] = $className;
        
        // Clear cached instance if it exists
        unset($this->strategyInstances[$name]);
    }

    /**
     * Register a custom key resolver
     *
     * @param string $name Key resolver name
     * @param class-string<KeyResolverInterface> $className Key resolver class
     * @return void
     */
    public function registerKeyResolver(string $name, string $className): void
    {
        if (!is_subclass_of($className, KeyResolverInterface::class)) {
            throw new \InvalidArgumentException(
                "Key resolver class must implement KeyResolverInterface: {$className}"
            );
        }

        $this->keyResolvers[$name] = $className;
        
        // Clear cached instances
        $this->keyResolverInstances = array_filter(
            $this->keyResolverInstances,
            fn($value, $key) => !str_starts_with($key, $name . '_'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Get available strategies
     *
     * @return array<string, class-string<RateLimitStrategyInterface>>
     */
    public function getAvailableStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Get available key resolvers
     *
     * @return array<string, class-string<KeyResolverInterface>>
     */
    public function getAvailableKeyResolvers(): array
    {
        return $this->keyResolvers;
    }

    /**
     * Get manager configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set manager configuration
     *
     * @param array<string, mixed> $config Configuration
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}