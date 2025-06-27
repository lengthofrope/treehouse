<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Foundation;

use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Router\RouteCollection;
use LengthOfRope\TreeHouse\Router\RouteNotFoundException;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\View\ViewFactory;

/**
 * TreeHouse Foundation Application
 * 
 * Main application container and bootstrap class for the TreeHouse framework.
 * Handles configuration loading, service registration, and request handling.
 * 
 * @package LengthOfRope\TreeHouse\Foundation
 * @author  TreeHouse Framework Team
 * @since   1.0.0
 */
class Application
{
    /**
     * The base path of the application
     */
    private string $basePath;

    /**
     * The router instance
     */
    private Router $router;

    /**
     * The service container
     */
    private Container $container;

    /**
     * Application configuration
     */
    private array $config = [];

    /**
     * Whether the application has been bootstrapped
     */
    private bool $bootstrapped = false;

    /**
     * Create a new application instance
     *
     * @param string $basePath The base path of the application
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: getcwd() ?: __DIR__;
        $this->container = new Container();
        $this->router = new Router(new RouteCollection());

        $this->bootstrap();
    }

    /**
     * Bootstrap the application
     */
    private function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        // Register core services
        $this->registerCoreServices();

        $this->bootstrapped = true;
    }

    /**
     * Register core framework services
     */
    private function registerCoreServices(): void
    {
        // Register the router
        $this->container->singleton('router', fn() => $this->router);

        // Register cache manager
        $this->container->singleton('cache', function () {
            $config = $this->config['cache'] ?? ['default' => 'file'];
            return new CacheManager($config, $config['default'] ?? 'file');
        });

        // Register view factory
        $this->container->singleton('view', function () {
            $config = $this->config['view'] ?? [
                'paths' => [$this->basePath . '/resources/views'],
                'cache_path' => $this->basePath . '/storage/views',
                'cache_enabled' => true,
            ];
            return new ViewFactory($config);
        });
    }

    /**
     * Load configuration from a directory
     *
     * @param string $configPath Path to configuration directory
     */
    public function loadConfiguration(string $configPath): void
    {
        if (!is_dir($configPath)) {
            return;
        }

        $configFiles = glob($configPath . '/*.php');
        
        foreach ($configFiles as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            
            // Only treat files that return arrays as configuration
            if (is_array($config)) {
                $this->config[$key] = $config;
            }
        }
    }

    /**
     * Load routes from a file
     *
     * @param string $routesFile Path to routes file
     */
    public function loadRoutes(string $routesFile): void
    {
        if (file_exists($routesFile)) {
            $router = $this->router;
            require $routesFile;
        }
    }

    /**
     * Handle an incoming HTTP request
     *
     * @param Request $request The HTTP request
     * @return Response The HTTP response
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle an exception and return an appropriate response
     *
     * @param \Throwable $exception The exception to handle
     * @return Response The error response
     */
    private function handleException(\Throwable $exception): Response
    {
        // Determine status code based on exception type
        $statusCode = 500; // Default
        
        if ($exception instanceof RouteNotFoundException) {
            $statusCode = 404;
        } elseif (method_exists($exception, 'getStatusCode')) {
            /** @var object $exception */
            $statusCode = $exception->getStatusCode();
        }

        $message = $this->isDebugMode()
            ? $exception->getMessage() . "\n\n" . $exception->getTraceAsString()
            : $this->getErrorMessage($statusCode);

        return new Response($message, $statusCode);
    }

    /**
     * Get a user-friendly error message for a status code
     *
     * @param int $statusCode HTTP status code
     * @return string Error message
     */
    private function getErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            404 => 'Page Not Found',
            403 => 'Forbidden',
            401 => 'Unauthorized',
            500 => 'Internal Server Error',
            default => 'An error occurred'
        };
    }

    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode(): bool
    {
        return ($this->config['app']['debug'] ?? false) || 
               (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true');
    }

    /**
     * Get the base path of the application
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the service container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get a configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $value Configuration value
     */
    public function setConfig(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $config[$segment] = $value;
            } else {
                if (!isset($config[$segment]) || !is_array($config[$segment])) {
                    $config[$segment] = [];
                }
                $config = &$config[$segment];
            }
        }
    }

    /**
     * Get all configuration
     */
    public function getAllConfig(): array
    {
        return $this->config;
    }

    /**
     * Resolve a service from the container
     *
     * @param string $abstract Service identifier
     * @return mixed Resolved service
     */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    /**
     * Bind a service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     */
    public function bind(string $abstract, mixed $concrete): void
    {
        $this->container->bind($abstract, $concrete);
    }

    /**
     * Bind a singleton service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     */
    public function singleton(string $abstract, mixed $concrete): void
    {
        $this->container->singleton($abstract, $concrete);
    }
}