<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Router\Exceptions\RouteNotFoundException;
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
     *
     * @param RouteCollection|bool $routesOrRegisterCsrf Routes collection or whether to register CSRF endpoint
     * @param bool|null $registerCsrfEndpoint Whether to automatically register the CSRF token endpoint
     * @param bool|null $registerVendorAssets Whether to automatically register vendor asset routes
     */
    public function __construct(RouteCollection|bool $routesOrRegisterCsrf = true, ?bool $registerCsrfEndpoint = null, ?bool $registerVendorAssets = null)
    {
        // Handle backward compatibility with old constructor signature
        if ($routesOrRegisterCsrf instanceof RouteCollection) {
            // Old signature: Router(RouteCollection $routes)
            $this->routes = $routesOrRegisterCsrf;
            $registerCsrf = $registerCsrfEndpoint ?? true;
            $registerAssets = $registerVendorAssets ?? true;
        } else {
            // New signature: Router(bool $registerCsrfEndpoint = true, bool $registerCsrfEndpoint = null, bool $registerVendorAssets = null)
            $this->routes = new RouteCollection();
            $registerCsrf = $routesOrRegisterCsrf;
            
            // If only CSRF is disabled but assets not specified, disable assets too for test compatibility
            if ($routesOrRegisterCsrf === false && $registerVendorAssets === null) {
                $registerAssets = false;
            } else {
                $registerAssets = $registerVendorAssets ?? true;
            }
        }
        
        $this->middleware = new MiddlewareStack();
        $this->groupStack = new Collection();
        
        // Register built-in CSRF token endpoint if requested
        if ($registerCsrf) {
            $this->registerCsrfEndpoint();
        }
        
        // Register vendor asset routes if requested
        if ($registerAssets) {
            $this->registerVendorAssets();
        }
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
            throw new RouteNotFoundException($method, $uri);
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
        
        // Validate CSRF for all state-changing methods (mandatory)
        $stateMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        
        return in_array($method, $stateMethods);
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
            
            // CSRF token is mandatory for state-changing requests
            if (!isset($data['_token'])) {
                return false;
            }
            
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
            // Try to render a proper error page using the view system
            try {
                // Load view helpers if not already loaded
                if (!function_exists('view')) {
                    require_once dirname(__DIR__) . '/View/helpers.php';
                }
                
                // Try to render the security error view with proper data
                $errorData = [
                    'title' => 'Security Error - CSRF Token Mismatch',
                    'icon' => '🔒',
                    'heading' => 'CSRF Token Mismatch',
                    'error_type' => 'error',
                    'message' => 'The request could not be completed due to an invalid or missing CSRF token. This is a security measure to protect against cross-site request forgery attacks.',
                    'code' => 419,
                    'request_id' => uniqid('req_'),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'suggestions' => [
                        'Refresh the page and try again',
                        'Clear your browser cache and cookies',
                        'If the problem persists, contact support'
                    ]
                ];
                
                $content = view('errors.security', $errorData)->render();
                $response = new Response($content, 419);
                $response->setHeader('Content-Type', 'text/html');
            } catch (\Exception $e) {
                // Fallback to basic error page if view rendering fails
                $content = $this->generateBasicErrorPage(419, 'CSRF Token Mismatch', 'The request could not be completed due to an invalid or missing CSRF token. Please refresh the page and try again.', '🔒');
                $response = new Response($content, 419);
                $response->setHeader('Content-Type', 'text/html');
            }
        }
        
        return $response;
    }
    
    /**
     * Generate a basic HTML error page as fallback
     *
     * @param int $code HTTP status code
     * @param string $title Error title
     * @param string $message Error message
     * @param string $icon Error icon (emoji)
     * @return string HTML content
     */
    protected function generateBasicErrorPage(int $code, string $title, string $message, string $icon = '❌'): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$code} - {$title}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 0; background: #f8f9fa; }
        .container { max-width: 600px; margin: 100px auto; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .error-icon { font-size: 80px; margin-bottom: 20px; }
        .error-code { font-size: 48px; font-weight: bold; color: #dc3545; margin-bottom: 10px; }
        .error-title { font-size: 24px; font-weight: 600; color: #343a40; margin-bottom: 16px; }
        .error-message { font-size: 16px; color: #6c757d; line-height: 1.5; margin-bottom: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; margin: 0 5px; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">{$icon}</div>
        <div class="error-code">{$code}</div>
        <div class="error-title">{$title}</div>
        <div class="error-message">{$message}</div>
        <a href="/" class="btn">Go Home</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }

    /**
     * Register the built-in CSRF token endpoint
     *
     * @return void
     */
    protected function registerCsrfEndpoint(): void
    {
        $this->get('/_csrf/token', function (Request $request) {
            // Validate same-origin request to prevent cross-origin token harvesting
            if (!$this->validateSameOrigin($request)) {
                $response = new Response(json_encode([
                    'error' => 'Forbidden',
                    'message' => 'Cross-origin requests are not allowed for CSRF token endpoint'
                ]), 403);
                $response->setHeader('Content-Type', 'application/json');
                return $response;
            }
            
            try {
                $session = new Session();
                $csrf = new Csrf($session);
                
                $token = $csrf->getToken();
                
                $response = new Response(json_encode([
                    'token' => $token,
                    'field' => '_token'
                ]));
                $response->setHeader('Content-Type', 'application/json');
                $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
                $response->setHeader('Pragma', 'no-cache');
                $response->setHeader('Expires', '0');
                
                return $response;
            } catch (\Exception $e) {
                $response = new Response(json_encode([
                    'error' => 'Unable to generate CSRF token',
                    'message' => 'Session could not be started'
                ]), 500);
                $response->setHeader('Content-Type', 'application/json');
                
                return $response;
            }
        });
    }

    /**
     * Register vendor asset routes for TreeHouse JavaScript and CSS files
     *
     * @return void
     */
    protected function registerVendorAssets(): void
    {
        $this->get('/_assets/treehouse/{path}', function (Request $request, string $path) {
            return $this->serveVendorAsset($path, $request);
        })->where('path', '.*');
    }

    /**
     * Serve vendor assets with override support
     *
     * @param string $path Asset path
     * @param Request $request HTTP request
     * @return Response
     */
    protected function serveVendorAsset(string $path, Request $request): Response
    {
        // Security: validate path to prevent directory traversal
        if (str_contains($path, '..') || str_contains($path, '/..') || str_starts_with($path, '/')) {
            return new Response('Forbidden', 403);
        }

        // Check for application override first
        $publicPath = $this->getPublicPath() . "/assets/treehouse/{$path}";
        if (file_exists($publicPath)) {
            return $this->serveFile($publicPath, $request);
        }

        // Serve from vendor package
        $vendorPath = $this->getVendorPath() . "/lengthofrope/treehouse/assets/{$path}";
        if (file_exists($vendorPath)) {
            return $this->serveFile($vendorPath, $request);
        }

        // If not found in vendor, try framework root (for development)
        // Check multiple possible locations for the framework assets
        $possibleFrameworkPaths = [
            dirname(__DIR__, 2) . "/assets/{$path}", // From src/TreeHouse/Router go up 2 levels
            getcwd() . "/assets/{$path}",             // From current working directory
            __DIR__ . "/../../../assets/{$path}"     // Alternative path calculation
        ];
        
        foreach ($possibleFrameworkPaths as $frameworkAssetPath) {
            if (file_exists($frameworkAssetPath)) {
                return $this->serveFile($frameworkAssetPath, $request);
            }
        }

        return new Response('Not Found', 404);
    }

    /**
     * Serve a file with appropriate headers
     *
     * @param string $filePath File path to serve
     * @param Request $request HTTP request
     * @return Response
     */
    protected function serveFile(string $filePath, Request $request): Response
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return new Response('Not Found', 404);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return new Response('Internal Server Error', 500);
        }

        $mimeType = $this->getMimeType($filePath);
        $etag = md5_file($filePath);
        $lastModified = filemtime($filePath);

        // Check if client has cached version
        $clientEtag = $request->header('If-None-Match');
        $clientModified = $request->header('If-Modified-Since');

        if ($clientEtag === $etag || ($clientModified && strtotime($clientModified) >= $lastModified)) {
            return new Response('', 304);
        }

        $response = new Response($content);
        $response->setHeader('Content-Type', $mimeType);
        $response->setHeader('Cache-Control', 'public, max-age=31536000'); // 1 year
        $response->setHeader('ETag', $etag);
        $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

        return $response;
    }

    /**
     * Get MIME type for file
     *
     * @param string $filePath File path
     * @return string MIME type
     */
    protected function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            default => 'application/octet-stream'
        };
    }

    /**
     * Get public directory path
     *
     * @return string Public directory path
     */
    protected function getPublicPath(): string
    {
        // Try to find public directory relative to current working directory
        $cwd = getcwd();
        
        // Check common public directory locations
        $possiblePaths = [
            $cwd . '/public',
            $cwd . '/web',
            $cwd . '/www',
            $cwd
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return $cwd;
    }

    /**
     * Get vendor directory path
     *
     * @return string Vendor directory path
     */
    protected function getVendorPath(): string
    {
        // Try to find vendor directory
        $cwd = getcwd();
        
        // Check if we're in a TreeHouse project or if TreeHouse is a vendor package
        $possiblePaths = [
            $cwd . '/vendor',
            dirname(__DIR__, 3), // From TreeHouse package perspective
            $cwd . '/../vendor',
            dirname($cwd) . '/vendor'
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path) && is_dir($path . '/lengthofrope/treehouse')) {
                return $path;
            }
        }

        // Fallback: assume we're in the TreeHouse package itself
        return dirname(__DIR__, 3);
    }

    /**
     * Validate that the request comes from the same origin
     *
     * @param Request $request HTTP request
     * @return bool
     */
    protected function validateSameOrigin(Request $request): bool
    {
        $currentHost = $request->getHost();
        $currentScheme = $request->isSecure() ? 'https' : 'http';
        $currentOrigin = $currentScheme . '://' . $currentHost;
        
        // Check Origin header (preferred for CORS and modern browsers)
        $origin = $request->header('Origin');
        if ($origin) {
            return $this->isSameOrigin($origin, $currentOrigin);
        }
        
        // Fallback to Referer header for older browsers
        $referer = $request->header('Referer');
        if ($referer) {
            return $this->isSameOrigin($referer, $currentOrigin);
        }
        
        // If neither Origin nor Referer is present, this might be a direct request
        // For security, we should be strict and deny such requests to the CSRF endpoint
        return false;
    }

    /**
     * Check if the given URL/origin matches the current origin
     *
     * @param string $url URL or origin to check
     * @param string $currentOrigin Current origin
     * @return bool
     */
    protected function isSameOrigin(string $url, string $currentOrigin): bool
    {
        // Parse the URL to extract scheme, host, and port
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }
        
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        
        // Construct the origin from the parsed URL
        $urlOrigin = $scheme . '://' . $host;
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $urlOrigin .= ':' . $port;
        }
        
        return $urlOrigin === $currentOrigin;
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