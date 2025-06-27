<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router;

use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Individual route representation
 * 
 * Represents a single route with its HTTP methods, URI pattern, action,
 * middleware, and parameter constraints.
 * 
 * @package LengthOfRope\TreeHouse\Router
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Route
{
    /**
     * The HTTP methods the route responds to
     * 
     * @var array<string>
     */
    protected array $methods;

    /**
     * The route URI pattern
     * 
     * @var string
     */
    protected string $uri;

    /**
     * The route action (callback or controller@method)
     * 
     * @var callable|string|array
     */
    protected mixed $action;

    /**
     * The route middleware stack
     * 
     * @var Collection<int, string|callable>
     */
    protected Collection $middleware;

    /**
     * The route parameter constraints
     * 
     * @var array<string, string>
     */
    protected array $wheres = [];

    /**
     * The route parameter defaults
     * 
     * @var array<string, mixed>
     */
    protected array $defaults = [];

    /**
     * The route name
     * 
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * The compiled route pattern
     * 
     * @var string|null
     */
    protected ?string $compiled = null;

    /**
     * The route parameter names
     * 
     * @var array<string>|null
     */
    protected ?array $parameterNames = null;

    /**
     * Create a new route instance
     * 
     * @param array<string>|string $methods HTTP methods
     * @param string $uri URI pattern
     * @param callable|string|array $action Route action
     */
    public function __construct(array|string $methods, string $uri, mixed $action)
    {
        $this->methods = Arr::wrap($methods);
        $this->uri = $uri;
        $this->action = $action;
        $this->middleware = new Collection();
    }

    /**
     * Get the HTTP methods for the route
     * 
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get the route URI
     * 
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the route action
     * 
     * @return callable|string|array
     */
    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * Get the route middleware
     * 
     * @return Collection<int, string|callable>
     */
    public function getMiddleware(): Collection
    {
        return $this->middleware;
    }

    /**
     * Add middleware to the route
     * 
     * @param string|callable|array<string|callable> $middleware
     * @return $this
     */
    public function middleware(string|callable|array $middleware): static
    {
        $middleware = Arr::wrap($middleware);
        
        foreach ($middleware as $m) {
            $this->middleware->push($m);
        }

        return $this;
    }

    /**
     * Set parameter constraint for the route
     * 
     * @param string|array<string, string> $name Parameter name or array of constraints
     * @param string|null $pattern Regular expression pattern
     * @return $this
     */
    public function where(string|array $name, ?string $pattern = null): static
    {
        if (is_array($name)) {
            $this->wheres = array_merge($this->wheres, $name);
        } else {
            $this->wheres[$name] = $pattern;
        }

        return $this;
    }

    /**
     * Set parameter defaults for the route
     * 
     * @param string|array<string, mixed> $name Parameter name or array of defaults
     * @param mixed $value Default value
     * @return $this
     */
    public function defaults(string|array $name, mixed $value = null): static
    {
        if (is_array($name)) {
            $this->defaults = array_merge($this->defaults, $name);
        } else {
            $this->defaults[$name] = $value;
        }

        return $this;
    }

    /**
     * Set the route name
     * 
     * @param string $name Route name
     * @return $this
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the route name
     * 
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Check if the route matches the given request
     * 
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return bool
     */
    public function matches(string $method, string $uri): bool
    {
        if (!in_array(strtoupper($method), $this->methods)) {
            return false;
        }

        $pattern = $this->getCompiledPattern();
        return (bool) preg_match($pattern, $uri);
    }

    /**
     * Extract parameters from the given URI
     * 
     * @param string $uri Request URI
     * @return array<string, string>
     */
    public function extractParameters(string $uri): array
    {
        $pattern = $this->getCompiledPattern();
        $parameterNames = $this->getParameterNames();

        if (!preg_match($pattern, $uri, $matches)) {
            return [];
        }

        // Remove the full match
        array_shift($matches);

        $parameters = [];
        foreach ($parameterNames as $index => $name) {
            $value = $matches[$index] ?? null;
            
            // Handle optional parameters - if no value captured, use default
            if ($value === null || $value === '') {
                $parameters[$name] = $this->defaults[$name] ?? null;
            } else {
                $parameters[$name] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Get the compiled route pattern
     * 
     * @return string
     */
    protected function getCompiledPattern(): string
    {
        if ($this->compiled === null) {
            $this->compiled = $this->compileRoute();
        }

        return $this->compiled;
    }

    /**
     * Get the parameter names from the route
     * 
     * @return array<string>
     */
    protected function getParameterNames(): array
    {
        if ($this->parameterNames === null) {
            preg_match_all('/\{([^}]+)\}/', $this->uri, $matches);
            $this->parameterNames = array_map(function($param) {
                // Remove the optional marker from parameter names
                return rtrim($param, '?');
            }, $matches[1] ?? []);
        }

        return $this->parameterNames;
    }

    /**
     * Compile the route pattern into a regular expression
     * 
     * @return string
     */
    protected function compileRoute(): string
    {
        $pattern = $this->uri;

        // Replace route parameters with regex patterns
        $pattern = preg_replace_callback('/\/\{([^}]+)\}/', function ($matches) {
            $parameter = $matches[1];
            $optional = false;

            // Check if parameter is optional
            if (str_ends_with($parameter, '?')) {
                $optional = true;
                $parameter = rtrim($parameter, '?');
            }

            // Get constraint pattern or use default
            $constraint = $this->wheres[$parameter] ?? '[^/]+';

            // Make optional parameters optional in regex
            if ($optional) {
                return "(?:/({$constraint}))?";
            }

            return "/({$constraint})";
        }, $pattern);

        // Escape forward slashes and add anchors
        $pattern = str_replace('/', '\/', $pattern);
        return '/^' . $pattern . '$/';
    }

    /**
     * Generate a URL for this route with the given parameters
     * 
     * @param array<string, mixed> $parameters Route parameters
     * @return string
     */
    public function url(array $parameters = []): string
    {
        $url = $this->uri;

        // Replace parameters in the URL
        foreach ($parameters as $key => $value) {
            $url = str_replace('{' . $key . '}', (string) $value, $url);
            $url = str_replace('{' . $key . '?}', (string) $value, $url);
        }

        // Remove any remaining optional parameters and their trailing slashes
        $url = preg_replace('/\/\{[^}]+\?\}/', '', $url);
        
        // Clean up any double slashes that might have been created
        $url = preg_replace('/\/+/', '/', $url);
        
        // Remove trailing slash if it's not the root
        if ($url !== '/' && str_ends_with($url, '/')) {
            $url = rtrim($url, '/');
        }

        return $url;
    }

    /**
     * Get route information as array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'methods' => $this->methods,
            'uri' => $this->uri,
            'action' => $this->action,
            'middleware' => $this->middleware->all(),
            'wheres' => $this->wheres,
            'defaults' => $this->defaults,
            'name' => $this->name,
        ];
    }

    /**
     * Convert the route to its string representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s %s',
            implode('|', $this->methods),
            $this->uri
        );
    }
}