<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit;

/**
 * Rate Limit Configuration
 *
 * Parses and validates rate limiting configuration from middleware parameters.
 * Supports various configuration formats and provides sensible defaults.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RateLimitConfig
{
    /**
     * Default configuration values
     */
    private const DEFAULTS = [
        'strategy' => 'fixed',
        'key_resolver' => 'ip',
        'cache_store' => 'default',
        'cache_prefix' => 'rate_limit',
    ];

    /**
     * Valid strategies
     */
    private const VALID_STRATEGIES = ['fixed', 'sliding', 'token_bucket'];

    /**
     * Valid key resolvers
     */
    private const VALID_KEY_RESOLVERS = ['ip', 'user', 'composite', 'custom'];

    /**
     * Parsed configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new rate limit configuration
     *
     * @param array<string, mixed> $config Base configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULTS, $config);
    }

    /**
     * Parse middleware parameters into configuration
     *
     * Supports formats:
     * - throttle:60,1 (limit, window)
     * - throttle:60,1,sliding (limit, window, strategy)
     * - throttle:60,1,sliding,user (limit, window, strategy, key_resolver)
     * - throttle:100,1|1000,60 (multiple limits)
     *
     * @param string $parameters Middleware parameters
     * @return static
     */
    public static function fromParameters(string $parameters): self
    {
        $config = new self();
        
        // Handle multiple limits separated by |
        if (str_contains($parameters, '|')) {
            $limits = [];
            foreach (explode('|', $parameters) as $limitConfig) {
                $limits[] = $config->parseSingleLimit(trim($limitConfig));
            }
            $config->config['limits'] = $limits;
            $config->config['multiple_limits'] = true;
        } else {
            $limitConfig = $config->parseSingleLimit($parameters);
            $config->config = array_merge($config->config, $limitConfig);
            $config->config['multiple_limits'] = false;
        }

        return $config;
    }

    /**
     * Parse a single limit configuration
     *
     * @param string $parameters Single limit parameters
     * @return array<string, mixed>
     */
    private function parseSingleLimit(string $parameters): array
    {
        $parts = explode(',', $parameters);
        
        if (count($parts) < 2) {
            throw new \InvalidArgumentException(
                'Rate limit configuration requires at least limit and window: throttle:limit,window'
            );
        }

        $limit = (int) trim($parts[0]);
        $window = (int) trim($parts[1]);
        
        if ($limit <= 0 || $window <= 0) {
            throw new \InvalidArgumentException(
                'Rate limit and window must be positive integers'
            );
        }

        $config = [
            'limit' => $limit,
            'window' => $window,
            'strategy' => self::DEFAULTS['strategy'],
            'key_resolver' => self::DEFAULTS['key_resolver'],
        ];

        // Parse optional strategy
        if (isset($parts[2])) {
            $strategy = trim($parts[2]);
            if (!in_array($strategy, self::VALID_STRATEGIES, true)) {
                throw new \InvalidArgumentException(
                    "Invalid strategy '{$strategy}'. Valid strategies: " . implode(', ', self::VALID_STRATEGIES)
                );
            }
            $config['strategy'] = $strategy;
        }

        // Parse optional key resolver
        if (isset($parts[3])) {
            $keyResolver = trim($parts[3]);
            $config['key_resolver'] = $this->parseKeyResolver($keyResolver);
        }

        return $config;
    }

    /**
     * Parse key resolver configuration
     *
     * @param string $keyResolver Key resolver string
     * @return array<string, mixed>
     */
    private function parseKeyResolver(string $keyResolver): array
    {
        // Handle composite resolvers (e.g., "ip+user")
        if (str_contains($keyResolver, '+')) {
            return [
                'type' => 'composite',
                'resolvers' => explode('+', $keyResolver),
            ];
        }

        // Handle header-based resolvers (e.g., "header:x-api-key")
        if (str_starts_with($keyResolver, 'header:')) {
            return [
                'type' => 'header',
                'header' => substr($keyResolver, 7),
            ];
        }

        // Handle custom resolvers (e.g., "custom:App\\RateLimit\\TenantKeyResolver")
        if (str_starts_with($keyResolver, 'custom:')) {
            return [
                'type' => 'custom',
                'class' => substr($keyResolver, 7),
            ];
        }

        // Handle simple resolvers
        if (!in_array($keyResolver, self::VALID_KEY_RESOLVERS, true)) {
            throw new \InvalidArgumentException(
                "Invalid key resolver '{$keyResolver}'. Valid resolvers: " . implode(', ', self::VALID_KEY_RESOLVERS)
            );
        }

        return [
            'type' => $keyResolver,
        ];
    }

    /**
     * Get the rate limit
     */
    public function getLimit(): int
    {
        return $this->config['limit'] ?? 0;
    }

    /**
     * Get the time window in minutes
     */
    public function getWindow(): int
    {
        return $this->config['window'] ?? 0;
    }

    /**
     * Get the strategy
     */
    public function getStrategy(): string
    {
        return $this->config['strategy'];
    }

    /**
     * Get the key resolver configuration
     *
     * @return array<string, mixed>|string
     */
    public function getKeyResolver(): array|string
    {
        return $this->config['key_resolver'];
    }

    /**
     * Get the cache store
     */
    public function getCacheStore(): string
    {
        return $this->config['cache_store'];
    }

    /**
     * Get the cache prefix
     */
    public function getCachePrefix(): string
    {
        return $this->config['cache_prefix'];
    }

    /**
     * Check if multiple limits are configured
     */
    public function hasMultipleLimits(): bool
    {
        return $this->config['multiple_limits'] ?? false;
    }

    /**
     * Get all limit configurations (for multiple limits)
     *
     * @return array<array<string, mixed>>
     */
    public function getLimits(): array
    {
        return $this->config['limits'] ?? [];
    }

    /**
     * Get a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get all configuration as array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Validate the configuration
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function validate(): void
    {
        if ($this->hasMultipleLimits()) {
            foreach ($this->getLimits() as $limit) {
                $this->validateSingleLimit($limit);
            }
        } else {
            $this->validateSingleLimit($this->config);
        }
    }

    /**
     * Validate a single limit configuration
     *
     * @param array<string, mixed> $config Limit configuration
     * @throws \InvalidArgumentException If configuration is invalid
     */
    private function validateSingleLimit(array $config): void
    {
        if (!isset($config['limit']) || !isset($config['window'])) {
            throw new \InvalidArgumentException('Rate limit configuration must include limit and window');
        }

        if ($config['limit'] <= 0 || $config['window'] <= 0) {
            throw new \InvalidArgumentException('Rate limit and window must be positive integers');
        }

        if (!in_array($config['strategy'], self::VALID_STRATEGIES, true)) {
            throw new \InvalidArgumentException(
                "Invalid strategy '{$config['strategy']}'. Valid strategies: " . implode(', ', self::VALID_STRATEGIES)
            );
        }
    }
}