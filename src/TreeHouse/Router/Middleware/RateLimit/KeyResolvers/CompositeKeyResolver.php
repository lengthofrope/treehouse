<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers;

use LengthOfRope\TreeHouse\Http\Request;

/**
 * Composite Key Resolver
 *
 * Combines multiple key resolvers to create composite rate limiting keys.
 * This allows for more sophisticated rate limiting strategies that consider
 * multiple factors (e.g., IP + User ID).
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class CompositeKeyResolver implements KeyResolverInterface
{
    /**
     * Resolver configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Registered resolvers
     *
     * @var array<KeyResolverInterface>
     */
    private array $resolvers = [];

    /**
     * Create a new composite key resolver
     *
     * @param array<string, mixed> $config Resolver configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeResolvers();
    }

    /**
     * Resolve the rate limiting key for a request
     *
     * @param Request $request HTTP request
     * @return string|null Rate limiting key or null if cannot be resolved
     */
    public function resolveKey(Request $request): ?string
    {
        $keyParts = [];
        
        foreach ($this->resolvers as $resolver) {
            if ($resolver->canResolve($request)) {
                $key = $resolver->resolveKey($request);
                if ($key !== null) {
                    $keyParts[] = $key;
                }
            }
        }
        
        if (empty($keyParts)) {
            return null;
        }
        
        // Join keys with separator
        $separator = $this->config['separator'];
        $compositeKey = implode($separator, $keyParts);
        
        // Add prefix if configured
        $prefix = $this->config['prefix'];
        return $prefix ? "{$prefix}:{$compositeKey}" : $compositeKey;
    }

    /**
     * Get the resolver name
     */
    public function getName(): string
    {
        return 'composite';
    }

    /**
     * Check if this resolver can handle the request
     *
     * @param Request $request HTTP request
     * @return bool True if this resolver can generate a key for the request
     */
    public function canResolve(Request $request): bool
    {
        // Can resolve if at least one child resolver can resolve
        foreach ($this->resolvers as $resolver) {
            if ($resolver->canResolve($request)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get default configuration
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'resolvers' => ['ip', 'user'], // Default to IP + User
            'separator' => '+',
            'prefix' => 'composite',
            'fallback_mode' => 'any', // 'any' or 'all' - any resolver can provide key, or all must provide
        ];
    }

    /**
     * Set resolver configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        
        // Reinitialize resolvers if the resolver list changed
        if (isset($config['resolvers'])) {
            $this->initializeResolvers();
        }
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
     * Initialize child resolvers
     *
     * @return void
     */
    private function initializeResolvers(): void
    {
        $this->resolvers = [];
        
        foreach ($this->config['resolvers'] as $resolverConfig) {
            $resolver = $this->createResolver($resolverConfig);
            if ($resolver !== null) {
                $this->resolvers[] = $resolver;
            }
        }
    }

    /**
     * Create a resolver instance
     *
     * @param string|array<string, mixed> $resolverConfig Resolver configuration
     * @return KeyResolverInterface|null Resolver instance or null if failed
     */
    private function createResolver(string|array $resolverConfig): ?KeyResolverInterface
    {
        if (is_string($resolverConfig)) {
            $type = $resolverConfig;
            $config = [];
        } else {
            $type = $resolverConfig['type'] ?? '';
            $config = $resolverConfig['config'] ?? [];
        }
        
        return match ($type) {
            'ip' => new IpKeyResolver($config),
            'user' => new UserKeyResolver($config),
            default => null,
        };
    }

    /**
     * Add a resolver to the composite
     *
     * @param KeyResolverInterface $resolver Resolver to add
     * @return void
     */
    public function addResolver(KeyResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * Remove all resolvers
     *
     * @return void
     */
    public function clearResolvers(): void
    {
        $this->resolvers = [];
    }

    /**
     * Get all registered resolvers
     *
     * @return array<KeyResolverInterface>
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }

    /**
     * Resolve key in "all" mode - all resolvers must provide a key
     *
     * @param Request $request HTTP request
     * @return string|null Composite key or null if any resolver fails
     */
    public function resolveKeyAll(Request $request): ?string
    {
        $keyParts = [];
        
        foreach ($this->resolvers as $resolver) {
            if (!$resolver->canResolve($request)) {
                return null; // All must be able to resolve
            }
            
            $key = $resolver->resolveKey($request);
            if ($key === null) {
                return null; // All must provide a key
            }
            
            $keyParts[] = $key;
        }
        
        if (empty($keyParts)) {
            return null;
        }
        
        $separator = $this->config['separator'];
        $compositeKey = implode($separator, $keyParts);
        
        $prefix = $this->config['prefix'];
        return $prefix ? "{$prefix}:{$compositeKey}" : $compositeKey;
    }

    /**
     * Get debugging information
     *
     * @param Request $request HTTP request
     * @return array<string, mixed>
     */
    public function getDebugInfo(Request $request): array
    {
        $resolverInfo = [];
        
        foreach ($this->resolvers as $resolver) {
            $resolverInfo[] = [
                'name' => $resolver->getName(),
                'can_resolve' => $resolver->canResolve($request),
                'resolved_key' => $resolver->resolveKey($request),
            ];
        }
        
        return [
            'resolver' => $this->getName(),
            'child_resolvers' => $resolverInfo,
            'composite_key' => $this->resolveKey($request),
            'fallback_mode' => $this->config['fallback_mode'],
        ];
    }

    /**
     * Create a composite resolver with specific resolvers
     *
     * @param array<string> $resolverTypes Array of resolver type names
     * @param array<string, mixed> $config Additional configuration
     * @return static
     */
    public static function create(array $resolverTypes, array $config = []): static
    {
        $config['resolvers'] = $resolverTypes;
        return new static($config);
    }

    /**
     * Create an IP + User composite resolver
     *
     * @param array<string, mixed> $config Additional configuration
     * @return static
     */
    public static function ipAndUser(array $config = []): static
    {
        return self::create(['ip', 'user'], $config);
    }
}