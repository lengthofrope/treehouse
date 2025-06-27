<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router;

use LengthOfRope\TreeHouse\Support\Collection;

/**
 * A collection of routes that extends the base Collection class.
 *
 * This provides a fluent interface for managing routes, while also adding
 * specialized methods for route matching and URL generation.
 *
 * @package LengthOfRope\TreeHouse\Router
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 *
 * @template TKey of array-key
 * @template TValue of \LengthOfRope\TreeHouse\Router\Route
 * @extends Collection<TKey, TValue>
 */
class RouteCollection extends Collection
{
    /**
     * A cache of routes organized by HTTP method for faster lookups.
     *
     * @var array<string, Collection<int, Route>>
     */
    protected array $methodRoutes = [];

    /**
     * A cache of named routes for fast URL generation.
     *
     * @var array<string, Route>
     */
    protected array $namedRoutes = [];

    /**
     * Add a route to the collection and update indexes.
     *
     * @param Route $route The route to add.
     * @return $this
     */
    public function add(Route $route): static
    {
        $this->push($route);
        $this->rebuildIndexes();

        return $this;
    }

    /**
     * Find a route that matches the given HTTP method and URI.
     *
     * @param string $method The HTTP method to match.
     * @param string $uri The URI to match.
     * @return Route|null The matched route, or null if no route is found.
     */
    public function match(string $method, string $uri): ?Route
    {
        $method = strtoupper($method);

        if (!isset($this->methodRoutes[$method])) {
            return null;
        }

        return $this->methodRoutes[$method]->first(
            fn(Route $route) => $route->matches($method, $uri)
        );
    }

    /**
     * Get a route by its name.
     *
     * @param string $name The name of the route.
     * @return Route|null The route instance, or null if not found.
     */
    public function getByName(string $name): ?Route
    {
        if (!isset($this->namedRoutes[$name])) {
            $this->rebuildIndexes();
        }

        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name The name of the route.
     * @param array<string, mixed> $parameters The parameters to inject into the URL.
     * @return string|null The generated URL, or null if the route is not found.
     */
    public function url(string $name, array $parameters = []): ?string
    {
        if ($route = $this->getByName($name)) {
            return $route->url($parameters);
        }

        return null;
    }

    /**
     * Rebuild the method and named route indexes for faster lookups.
     *
     * @return $this
     */
    public function rebuildIndexes(): static
    {
        $this->methodRoutes = [];
        $this->namedRoutes = [];

        $this->each(function (Route $route) {
            foreach ($route->getMethods() as $method) {
                $this->methodRoutes[$method] ??= new Collection();
                $this->methodRoutes[$method]->push($route);
            }

            if ($name = $route->getName()) {
                $this->namedRoutes[$name] = $route;
            }
        });

        return $this;
    }

    /**
     * Get all registered routes.
     *
     * @return Collection<int, Route>
     */
    public function getRoutes(): Collection
    {
        return $this;
    }

    /**
     * Get routes for a specific HTTP method.
     *
     * @param string $method The HTTP method.
     * @return Collection<int, Route>
     */
    public function getRoutesByMethod(string $method): Collection
    {
        return $this->methodRoutes[strtoupper($method)] ?? new Collection();
    }
    
    /**
     * Get routes that match a specific pattern.
     *
     * @param string $pattern The URI pattern to match.
     * @return static
     */
    public function getRoutesByPattern(string $pattern): static
    {
        return $this->filter(
            fn(Route $route) => $route->getUri() === $pattern
        );
    }

    /**
     * Get routes that have a specific middleware.
     *
     * @param string $middleware The middleware name to search for.
     * @return static
     */
    public function getRoutesByMiddleware(string $middleware): static
    {
        return $this->filter(fn (Route $route) => $route->getMiddleware()->contains($middleware));
    }

    /**
     * Get all unique HTTP methods present in the collection.
     *
     * @return Collection<int, string>
     */
    public function getAllMethods(): Collection
    {
        return $this
            ->map(fn(Route $route) => $route->getMethods())
            ->flatten()
            ->unique()
            ->values();
    }

    /**
     * Get a collection of all unique URIs.
     *
     * @return Collection<int, string>
     */
    public function getAllUris(): Collection
    {
        return $this
            ->map(fn(Route $route) => $route->getUri())
            ->unique()
            ->values();
    }

    /**
     * Group routes by a given attribute.
     *
     * @param string $attribute The attribute to group by ('method', 'uri', 'name').
     * @return Collection<string, static>
     * @throws \InvalidArgumentException
     */
    public function groupBy($groupBy, $preserveKeys = false): static
    {
        if (is_string($groupBy) && in_array($groupBy, ['method', 'uri', 'name'])) {
            $groupBy = match ($groupBy) {
                'method' => fn (Route $route) => implode('|', $route->getMethods()),
                'uri' => fn (Route $route) => $route->getUri(),
                'name' => fn (Route $route) => $route->getName() ?? 'unnamed',
            };
        }

        if (!is_callable($groupBy)) {
            throw new \InvalidArgumentException("Invalid grouping attribute: {$groupBy}");
        }

        return parent::groupBy($groupBy, $preserveKeys);
    }

    /**
     * Check if a named route exists.
     *
     * @param string $name The name of the route.
     * @return bool
     */
    public function hasNamedRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }
    
    /**
     * Get all named routes.
     *
     * @return array<string, Route>
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Clear all routes from the collection.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->items = [];
        $this->methodRoutes = [];
        $this->namedRoutes = [];
    }

    /**
     * Convert the collection to a plain array of routes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->map(fn(Route $route) => $route->toArray())->all();
    }

    /**
     * Overrides the parent offsetSet to ensure indexes are rebuilt when a route is added.
     *
     * @param TKey|null $key
     * @param TValue $value
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        parent::offsetSet($key, $value);
        $this->rebuildIndexes();
    }

    /**
     * Overrides the parent offsetUnset to ensure indexes are rebuilt when a route is removed.
     *
     * @param TKey $key
     * @return void
     */
    public function offsetUnset(mixed $key): void
    {
        parent::offsetUnset($key);
        $this->rebuildIndexes();
    }

    /**
     * Get the collection's debug information.
     *
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        return [
            'total_routes' => $this->count(),
            'methods' => $this->getAllMethods()->all(),
            'named_routes' => array_keys($this->namedRoutes),
            'routes_by_method' => array_map(fn($routes) => $routes->count(), $this->methodRoutes),
        ];
    }

    /**
     * Get the string representation of the collection.
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('RouteCollection with %d routes', $this->count());
    }
}