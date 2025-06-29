<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareStack;
use LengthOfRope\TreeHouse\Security\Csrf;
use LengthOfRope\TreeHouse\Support\Arr;
use LengthOfRope\TreeHouse\Support\Collection;

/**
 * Main HTTP router
 * 
 * Handles HTTP request routing, route registration, middleware management,
 * and request dispatching to appropriate controllers or callbacks.
 * 
 * @package LengthOfRope\TreeHouse\Router
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Router
{
    /**
     * The route collection
     * 
     * @var RouteCollection
     */
    protected RouteCollection $routes;

    /**
     * Global middleware stack
     * 
     * @var MiddlewareStack
     */
    protected MiddlewareStack $middleware;

    /**
     * Route group stack
     * 
     * @var Collection<int, array<string, mixed>>
     */
    protected Collection $groupStack;

    /**
     * Current route being processed
     * 
     * @var Route|null
     */
    protected ?Route $currentRoute = null;

    /**
     * Current request parameters
     * 
     * @var array<string, mixed>
     */
    protected array $currentParameters = [];

    /**
     * Create a new router instance
     */
    public function __construct()
    {
        $this->routes = new RouteCollection();
        $this->middleware = new MiddlewareStack();
        $this->groupStack = new Collection();
    }

    /**
     * Register a GET route
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a POST route
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * Register a PUT route
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * Register a PATCH route
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * Register a DELETE route
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * Register an OPTIONS route
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function options(string $uri, mixed $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    /**
     * Register a route that responds to any HTTP method
     * 
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    /**
     * Register a route with multiple HTTP methods
     * 
     * @param array<string> $methods HTTP methods
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * Create a route group with shared attributes
     * 
     * @param array<string, mixed> $attributes Group attributes (prefix, middleware, etc.)
     * @param callable $callback Group callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack->push($attributes);
        
        $callback($this);
        
        // Remove the last item from the stack
        $items = $this->groupStack->all();
        array_pop($items);
        $this->groupStack = new Collection($items);
    }

    /**
     * Add global middleware to the router
     * 
     * @param string|callable|array<string|callable> $middleware
     * @return $this
     */
    public function middleware(string|callable|array $middleware): static
    {
        $this->middleware->add($middleware);
        return $this;
    }

    /**
     * Register middleware aliases
     * 
     * @param array<string, string> $aliases
     * @return $this
     */
    public function middlewareAliases(array $aliases): static
    {
        $this->middleware->aliases($aliases);
        return $this;
    }

    /**
     * Dispatch the request to the appropriate route
     * 
     * @param Request $request HTTP request
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $method = $this->getRequestMethod($request);
        $uri = $request->path();

        // Find matching route
        $route = $this->routes->match($method, $uri);

        if (!$route) {
            return $this->handleNotFound($request);
        }

        // Set current route and extract parameters
        $this->currentRoute = $route;
        $this->currentParameters = $route->extractParameters($uri);

        // Validate CSRF token for state-changing requests
        if ($this->shouldValidateCsrf($request)) {
            if (!$this->validateCsrfToken($request)) {
                return $this->handleCsrfFailure($request);
            }
        }

        // Create middleware stack for this route
        $middlewareStack = $this->createRouteMiddlewareStack($route);

        // Execute the route through middleware
        return $middlewareStack->handle($request, function (Request $request) use ($route) {
            return $this->executeRoute($route, $request);
        });
    }

    /**
     * Generate a URL for a named route
     * 
     * @param string $name Route name
     * @param array<string, mixed> $parameters Route parameters
     * @return string|null
     */
    public function url(string $name, array $parameters = []): ?string
    {
        return $this->routes->url($name, $parameters);
    }

    /**
     * Get the current route
     * 
     * @return Route|null
     */
    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    /**
     * Get the current route parameters
     * 
     * @return array<string, mixed>
     */
    public function getCurrentParameters(): array
    {
        return $this->currentParameters;
    }

    /**
     * Get a specific route parameter
     * 
     * @param string $key Parameter name
     * @param mixed $default Default value
     * @return mixed
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->currentParameters[$key] ?? $default;
    }

    /**
     * Get the route collection
     * 
     * @return RouteCollection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Get the global middleware stack
     * 
     * @return MiddlewareStack
     */
    public function getMiddleware(): MiddlewareStack
    {
        return $this->middleware;
    }

    /**
     * Add a route to the collection
     * 
     * @param array<string> $methods HTTP methods
     * @param string $uri Route URI pattern
     * @param callable|string|array $action Route action
     * @return Route
     */
    protected function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $route = new Route($methods, $this->buildUri($uri), $action);

        // Apply group attributes
        $this->applyGroupAttributes($route);

        $this->routes->add($route);

        return $route;
    }

    /**
     * Build the full URI with group prefixes
     * 
     * @param string $uri Base URI
     * @return string
     */
    protected function buildUri(string $uri): string
    {
        $prefixes = $this->groupStack
            ->pluck('prefix')
            ->filter()
            ->map(fn($prefix) => trim($prefix, '/'))
            ->filter();

        if ($prefixes->isEmpty()) {
            return '/' . ltrim($uri, '/');
        }

        $fullPrefix = $prefixes->implode('/');
        return '/' . $fullPrefix . '/' . ltrim($uri, '/');
    }

    /**
     * Apply group attributes to a route
     * 
     * @param Route $route Route to modify
     * @return void
     */
    protected function applyGroupAttributes(Route $route): void
    {
        // Collect all middleware from group stack
        $middleware = $this->groupStack
            ->pluck('middleware')
            ->filter()
            ->flatten()
            ->all();

        if (!empty($middleware)) {
            $route->middleware($middleware);
        }

        // Apply other group attributes
        foreach ($this->groupStack as $group) {
            if (isset($group['where'])) {
                $route->where($group['where']);
            }

            if (isset($group['defaults'])) {
                $route->defaults($group['defaults']);
            }

            if (isset($group['name'])) {
                $currentName = $route->getName();
                $route->name($group['name'] . ($currentName ? '.' . $currentName : ''));
            }
        }
    }

    /**
     * Create middleware stack for a specific route
     * 
     * @param Route $route Route instance
     * @return MiddlewareStack
     */
    protected function createRouteMiddlewareStack(Route $route): MiddlewareStack
    {
        $stack = new MiddlewareStack();
        
        // Copy global middleware aliases
        $stack->aliases($this->middleware->getAliases());
        
        // Add global middleware
        $stack->add($this->middleware->getMiddleware()->all());
        
        // Add route-specific middleware
        $stack->add($route->getMiddleware()->all());

        return $stack;
    }

    /**
     * Execute the route action
     * 
     * @param Route $route Route to execute
     * @param Request $request HTTP request
     * @return Response
     */
    protected function executeRoute(Route $route, Request $request): Response
    {
        $action = $route->getAction();

        if (is_callable($action)) {
            $result = $action($request, ...$this->getRouteParameters($route));
        } elseif (is_string($action) && str_contains($action, '@')) {
            $result = $this->executeControllerAction($action, $request);
        } elseif (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            $result = $this->executeControllerMethod($controller, $method, $request);
        } else {
            throw new \InvalidArgumentException('Invalid route action');
        }

        return $this->prepareResponse($result);
    }

    /**
     * Get route parameters in the correct order
     * 
     * @param Route $route Route instance
     * @return array<mixed>
     */
    protected function getRouteParameters(Route $route): array
    {
        // This would need to be implemented based on how parameters are extracted
        // For now, return the current parameters as values
        return array_values($this->currentParameters);
    }

    /**
     * Execute a controller action from string format (Controller@method)
     * 
     * @param string $action Controller@method string
     * @param Request $request HTTP request
     * @return mixed
     */
    protected function executeControllerAction(string $action, Request $request): mixed
    {
        [$controller, $method] = explode('@', $action, 2);
        return $this->executeControllerMethod($controller, $method, $request);
    }

    /**
     * Execute a controller method
     * 
     * @param string $controller Controller class name
     * @param string $method Method name
     * @param Request $request HTTP request
     * @return mixed
     */
    protected function executeControllerMethod(string $controller, string $method, Request $request): mixed
    {
        if (!class_exists($controller)) {
            throw new \InvalidArgumentException("Controller '{$controller}' not found");
        }

        $instance = new $controller();

        if (!method_exists($instance, $method)) {
            throw new \InvalidArgumentException("Method '{$method}' not found in controller '{$controller}'");
        }

        return $instance->{$method}($request, ...$this->getRouteParameters($this->currentRoute));
    }

    /**
     * Prepare the response
     * 
     * @param mixed $result Route execution result
     * @return Response
     */
    protected function prepareResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        $response = new Response();

        if (is_string($result)) {
            $response->setContent($result);
        } elseif (is_array($result) || is_object($result)) {
            $response->setContent(json_encode($result));
            $response->setHeader('Content-Type', 'application/json');
        } else {
            $response->setContent((string) $result);
        }

        return $response;
    }

    /**
     * Handle 404 Not Found
     * 
     * @param Request $request HTTP request
     * @return Response
     */
    protected function handleNotFound(Request $request): Response
    {
        $response = new Response('Not Found', 404);
        $response->setHeader('Content-Type', 'text/plain');
        return $response;
    }

    /**
     * Get the actual HTTP method for the request, handling method spoofing
     *
     * @param Request $request HTTP request
     * @return string
     */
    protected function getRequestMethod(Request $request): string
    {
        $method = $request->method();
        
        // Only check for method spoofing on POST requests
        if ($method === 'POST') {
            // Check for _method parameter in request data
            $spoofedMethod = $request->input('_method');
            
            if ($spoofedMethod && is_string($spoofedMethod)) {
                $spoofedMethod = strtoupper(trim($spoofedMethod));
                
                // Only allow valid HTTP methods for spoofing
                $allowedMethods = ['PUT', 'PATCH', 'DELETE', 'OPTIONS'];
                
                if (in_array($spoofedMethod, $allowedMethods)) {
                    return $spoofedMethod;
                }
            }
        }
        
        return $method;
    }

    /**
     * Determine if CSRF validation should be performed for the request
     *
     * @param Request $request HTTP request
     * @return bool
     */
    protected function shouldValidateCsrf(Request $request): bool
    {
        $method = $request->method();
        
        // Only validate CSRF for state-changing methods
        $stateMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        
        if (!in_array($method, $stateMethods)) {
            return false;
        }
        
        // Check if request contains a CSRF token
        return $request->input('_token') !== null;
    }

    /**
     * Validate the CSRF token in the request
     *
     * @param Request $request HTTP request
     * @return bool
     */
    protected function validateCsrfToken(Request $request): bool
    {
        try {
            $session = new Session();
            $csrf = new Csrf($session);
            
            // Get all request data (query + request data)
            $data = $request->input();
            
            return $csrf->verifyRequest($data, '_token');
        } catch (\Exception $e) {
            // If session cannot be started or CSRF validation fails, deny access
            return false;
        }
    }

    /**
     * Handle CSRF validation failure
     *
     * @param Request $request HTTP request
     * @return Response
     */
    protected function handleCsrfFailure(Request $request): Response
    {
        // Check if this is an AJAX request
        if ($request->isAjax() || $request->expectsJson()) {
            $response = new Response(json_encode([
                'error' => 'CSRF token mismatch',
                'message' => 'The request could not be completed due to invalid CSRF token.'
            ]), 419);
            $response->setHeader('Content-Type', 'application/json');
        } else {
            $response = new Response('CSRF Token Mismatch', 419);
            $response->setHeader('Content-Type', 'text/plain');
        }
        
        return $response;
    }

    /**
     * Get debug information about the router
     *
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        return [
            'routes' => $this->routes->getDebugInfo(),
            'middleware' => [
                'global' => $this->middleware->toArray(),
                'aliases' => $this->middleware->getAliases(),
            ],
            'current_route' => $this->currentRoute?->toArray(),
            'current_parameters' => $this->currentParameters,
            'group_stack' => $this->groupStack->all(),
        ];
    }
}