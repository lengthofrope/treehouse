<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Middleware stack
 * 
 * Manages and executes a stack of middleware components in the correct order.
 * Implements the onion-layer pattern where middleware can execute code before
 * and after the core request handling.
 * 
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class MiddlewareStack
{
    /**
     * The middleware stack
     * 
     * @var Collection<int, MiddlewareInterface|callable|string>
     */
    protected Collection $middleware;

    /**
     * Registered middleware aliases
     * 
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Create a new middleware stack
     * 
     * @param array<MiddlewareInterface|callable|string> $middleware Initial middleware
     */
    public function __construct(array $middleware = [])
    {
        $this->middleware = new Collection($middleware);
    }

    /**
     * Add middleware to the stack
     * 
     * @param MiddlewareInterface|callable|string|array<MiddlewareInterface|callable|string> $middleware
     * @return $this
     */
    public function add(MiddlewareInterface|callable|string|array $middleware): static
    {
        if (is_array($middleware)) {
            foreach ($middleware as $m) {
                $this->middleware->push($m);
            }
        } else {
            $this->middleware->push($middleware);
        }

        return $this;
    }

    /**
     * Prepend middleware to the beginning of the stack
     *
     * @param MiddlewareInterface|callable|string $middleware
     * @return $this
     */
    public function prepend(MiddlewareInterface|callable|string $middleware): static
    {
        $items = $this->middleware->all();
        array_unshift($items, $middleware);
        $this->middleware = new Collection($items);
        return $this;
    }

    /**
     * Register a middleware alias
     * 
     * @param string $alias Middleware alias
     * @param string $className Full middleware class name
     * @return $this
     */
    public function alias(string $alias, string $className): static
    {
        $this->aliases[$alias] = $className;
        return $this;
    }

    /**
     * Register multiple middleware aliases
     * 
     * @param array<string, string> $aliases Array of alias => className pairs
     * @return $this
     */
    public function aliases(array $aliases): static
    {
        $this->aliases = array_merge($this->aliases, $aliases);
        return $this;
    }

    /**
     * Execute the middleware stack
     * 
     * @param Request $request The HTTP request
     * @param callable $destination The final destination (controller action)
     * @return Response
     */
    public function handle(Request $request, callable $destination): Response
    {
        if ($this->middleware->isEmpty()) {
            return $destination($request);
        }

        return $this->createMiddlewarePipeline($destination)($request);
    }

    /**
     * Create the middleware pipeline
     * 
     * @param callable $destination The final destination
     * @return callable
     */
    protected function createMiddlewarePipeline(callable $destination): callable
    {
        return $this->middleware
            ->reverse()
            ->reduce(
                function (callable $next, MiddlewareInterface|callable|string $middleware) {
                    return function (Request $request) use ($next, $middleware) {
                        return $this->executeMiddleware($middleware, $request, $next);
                    };
                },
                $destination
            );
    }

    /**
     * Execute a single middleware
     * 
     * @param MiddlewareInterface|callable|string $middleware
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    protected function executeMiddleware(
        MiddlewareInterface|callable|string $middleware,
        Request $request,
        callable $next
    ): Response {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->handle($request, $next);
        }

        if (is_callable($middleware)) {
            return $middleware($request, $next);
        }

        if (is_string($middleware)) {
            $middleware = $this->resolveMiddleware($middleware);
            return $middleware->handle($request, $next);
        }

        throw new \InvalidArgumentException(
            'Middleware must be an instance of MiddlewareInterface, callable, or string'
        );
    }

    /**
     * Resolve middleware from string
     * 
     * @param string $middleware Middleware class name or alias
     * @return MiddlewareInterface
     */
    protected function resolveMiddleware(string $middleware): MiddlewareInterface
    {
        // Check if it's an alias
        if (isset($this->aliases[$middleware])) {
            $middleware = $this->aliases[$middleware];
        }

        // Parse middleware with parameters (e.g., "throttle:60,1")
        [$className, $parameters] = $this->parseMiddleware($middleware);

        // Handle built-in middleware aliases
        $className = $this->resolveBuiltInMiddleware($className);

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Middleware class '{$className}' not found");
        }

        $instance = new $className(...$parameters);

        if (!$instance instanceof MiddlewareInterface) {
            throw new \InvalidArgumentException(
                "Middleware '{$className}' must implement MiddlewareInterface"
            );
        }

        return $instance;
    }

    /**
     * Resolve built-in middleware aliases to their full class names
     *
     * @param string $middleware Middleware name
     * @return string Full class name
     */
    protected function resolveBuiltInMiddleware(string $middleware): string
    {
        $builtInMiddleware = [
            'auth' => 'LengthOfRope\TreeHouse\Router\Middleware\AuthMiddleware',
            'jwt' => 'LengthOfRope\TreeHouse\Router\Middleware\JwtMiddleware',
            'role' => 'LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware',
            'permission' => 'LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware',
            'throttle' => 'LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitMiddleware',
        ];

        return $builtInMiddleware[$middleware] ?? $middleware;
    }

    /**
     * Parse middleware string to extract class name and parameters
     * 
     * @param string $middleware Middleware string
     * @return array{0: string, 1: array<mixed>}
     */
    protected function parseMiddleware(string $middleware): array
    {
        if (!str_contains($middleware, ':')) {
            return [$middleware, []];
        }

        [$className, $parametersString] = explode(':', $middleware, 2);
        $parameters = explode(',', $parametersString);

        // Convert numeric strings to integers
        $parameters = array_map(function ($param) {
            $param = trim($param);
            return is_numeric($param) ? (int) $param : $param;
        }, $parameters);

        return [$className, $parameters];
    }

    /**
     * Get all middleware in the stack
     * 
     * @return Collection<int, MiddlewareInterface|callable|string>
     */
    public function getMiddleware(): Collection
    {
        return $this->middleware;
    }

    /**
     * Get registered aliases
     * 
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Check if the stack is empty
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->middleware->isEmpty();
    }

    /**
     * Get the count of middleware in the stack
     * 
     * @return int
     */
    public function count(): int
    {
        return $this->middleware->count();
    }

    /**
     * Clear all middleware from the stack
     * 
     * @return $this
     */
    public function clear(): static
    {
        $this->middleware = new Collection();
        return $this;
    }

    /**
     * Convert the stack to an array
     * 
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return $this->middleware->map(function ($middleware) {
            if ($middleware instanceof MiddlewareInterface) {
                return get_class($middleware);
            }
            if (is_string($middleware)) {
                return $middleware;
            }
            if (is_callable($middleware)) {
                return 'callable';
            }
            return (string) $middleware;
        })->all();
    }

    /**
     * Convert the stack to its string representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            'MiddlewareStack with %d middleware: [%s]',
            $this->count(),
            implode(', ', $this->toArray())
        );
    }
}