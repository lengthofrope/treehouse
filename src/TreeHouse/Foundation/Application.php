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
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Env;
use LengthOfRope\TreeHouse\Errors\ErrorHandler;
use LengthOfRope\TreeHouse\Errors\Classification\ExceptionClassifier;
use LengthOfRope\TreeHouse\Errors\Context\ContextManager;
use LengthOfRope\TreeHouse\Errors\Context\RequestCollector;
use LengthOfRope\TreeHouse\Errors\Context\UserCollector;
use LengthOfRope\TreeHouse\Errors\Context\EnvironmentCollector;
use LengthOfRope\TreeHouse\Errors\Logging\ErrorLogger;
use LengthOfRope\TreeHouse\Errors\Logging\LogFormatter;
use LengthOfRope\TreeHouse\Errors\Rendering\RenderManager;

/**
 * TreeHouse Foundation Application
 * 
 * Main application container and bootstrap class for the TreeHouse framework.
 * Handles configuration loading, service registration, and request handling.
 * 
 * @package LengthOfRope\TreeHouse\Foundation
 * @author  Bas de Kort <bdekort@proton.me>
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

        // Set global app instance for helper functions
        $GLOBALS['app'] = $this;

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

        // Load environment variables first
        $this->loadEnvironment();

        // Auto-load configuration from standard location
        $this->autoLoadConfiguration();

        // Register core services
        $this->registerCoreServices();
        
        // Register event services
        $this->registerEventServices();
        
        // Register error handling services
        $this->registerErrorServices();

        // Load routes automatically
        $this->autoLoadRoutes();

        $this->bootstrapped = true;
    }

    /**
     * Register core framework services
     */
    private function registerCoreServices(): void
    {
        // Register middleware aliases on the router
        $this->registerMiddlewareAliases();
        
        // Register the router
        $this->container->singleton('router', fn() => $this->router);

        // Register cache manager
        $this->container->singleton('cache', function () {
            $config = $this->config['cache'] ?? [
                'enabled' => true,
                'default' => 'file',
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => $this->basePath . '/storage/cache',
                        'default_ttl' => 3600,
                    ],
                ],
            ];
            return new CacheManager($config, $config['default'] ?? 'file');
        });

        // Register view factory
        $this->container->singleton('view', function () {
            $config = $this->config['view'] ?? [
                'enabled' => true,
                'paths' => [$this->basePath . '/resources/views'],
                'cache_path' => $this->basePath . '/storage/views',
                'cache_enabled' => true,
            ];
            return new ViewFactory($config);
        });

        // Register authentication manager
        $this->container->singleton('auth', function () {
            $config = $this->config['auth'] ?? [
                'default' => 'web',
                'guards' => [
                    'web' => [
                        'driver' => 'session',
                        'provider' => 'users',
                    ],
                ],
                'providers' => [
                    'users' => [
                        'driver' => 'database',
                        'table' => 'users',
                    ],
                ],
            ];
            
            // Merge global database configuration into auth providers
            if (isset($this->config['database'])) {
                $dbConfig = $this->config['database'];
                $defaultConnection = $dbConfig['default'] ?? 'mysql';
                $connectionConfig = $dbConfig['connections'][$defaultConnection] ?? [];
                
                // Add global database connection config to all database providers
                foreach ($config['providers'] as &$provider) {
                    if (($provider['driver'] ?? 'database') === 'database' && !isset($provider['connection'])) {
                        $provider['connection'] = $connectionConfig;
                    }
                }
                unset($provider);
            }
            
            // Create required dependencies using services
            $session = $this->make('session');
            $cookie = new \LengthOfRope\TreeHouse\Http\Cookie('auth_cookie');
            $hash = new \LengthOfRope\TreeHouse\Security\Hash();
            
            $authManager = new \LengthOfRope\TreeHouse\Auth\AuthManager(
                $config,
                $session,
                $cookie,
                $hash
            );
            
            // Set global reference for auth() helper function
            $GLOBALS['auth_manager'] = $authManager;
            
            return $authManager;
        });

        // Register session service
        $this->container->singleton('session', function () {
            $config = $this->config['session'] ?? [
                'name' => 'treehouse_session',
                'lifetime' => 7200, // 2 hours
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
                'save_path' => '',
            ];
            
            return new \LengthOfRope\TreeHouse\Http\Session($config);
        });

        // Register mail manager
        $this->container->singleton('mail', function () {
            $config = $this->config['mail'] ?? [];
            return new \LengthOfRope\TreeHouse\Mail\MailManager($config, $this);
        });

    }

    /**
     * Register event handling services
     */
    private function registerEventServices(): void
    {
        // Register event dispatcher
        $this->container->singleton('events', function () {
            $config = $this->config['events'] ?? [];
            $dispatcher = $config['default_dispatcher'] ?? 'sync';
            
            return match ($dispatcher) {
                'sync' => new \LengthOfRope\TreeHouse\Events\SyncEventDispatcher($this->container),
                default => new \LengthOfRope\TreeHouse\Events\SyncEventDispatcher($this->container)
            };
        });
        
        // Set event dispatcher on ActiveRecord for model events
        $eventDispatcher = $this->container->make('events');
        \LengthOfRope\TreeHouse\Database\ActiveRecord::setEventDispatcher($eventDispatcher);
    }

    /**
     * Register error handling services
     */
    private function registerErrorServices(): void
    {
        // Register error logger
        $this->container->singleton('error.logger', function () {
            $config = $this->config['errors']['logging'] ?? [];
            $defaultChannel = $config['default_channel'] ?? 'file';
            $channels = $config['channels'] ?? [];
            
            return new ErrorLogger($defaultChannel, $channels);
        });

        // Register exception classifier
        $this->container->singleton('error.classifier', function () {
            $config = $this->config['errors']['classification'] ?? [];
            return new ExceptionClassifier($config);
        });

        // Register context collectors
        $this->container->singleton('error.context.request', function () {
            // RequestCollector takes a Request object, not config array
            // It will fall back to PHP globals if no Request is provided
            return new RequestCollector(null);
        });

        $this->container->singleton('error.context.user', function () {
            // UserCollector takes an AuthManager, not config array
            // Try to get the auth manager, but allow null if not available
            try {
                $authManager = $this->make('auth');
                return new UserCollector($authManager);
            } catch (\Exception $e) {
                // If auth manager is not available, create with null
                return new UserCollector(null);
            }
        });

        $this->container->singleton('error.context.environment', function () {
            $config = $this->config['errors']['context']['collectors']['environment'] ?? [];
            return new EnvironmentCollector($config);
        });

        // Register context manager
        $this->container->singleton('error.context', function () {
            $config = $this->config['errors']['context'] ?? [];
            $contextManager = new ContextManager($config);
            
            // Register collectors
            $contextManager->addCollector($this->make('error.context.request'));
            $contextManager->addCollector($this->make('error.context.user'));
            $contextManager->addCollector($this->make('error.context.environment'));
            
            return $contextManager;
        });

        // Register render manager
        $this->container->singleton('error.renderer', function () {
            $config = $this->config['errors']['rendering'] ?? [];
            $debug = $this->isDebugMode(); // Use the same debug mode as ErrorHandler
            $defaultRenderer = $config['default_renderer'] ?? 'html';
            
            $renderManager = new RenderManager($debug, $defaultRenderer);
            
            // Clear default renderers and register custom ones with dependencies
            $renderManager->clearRenderers();
            
            // Register renderers with proper dependencies
            $renderManager->registerRenderer(new \LengthOfRope\TreeHouse\Errors\Rendering\JsonRenderer());
            $renderManager->registerRenderer(new \LengthOfRope\TreeHouse\Errors\Rendering\CliRenderer());
            
            // Register HtmlRenderer with ViewFactory injection
            $viewFactory = $this->make('view');
            $templatePath = $config['template_path'] ?? 'errors';
            $fallbackToBuiltIn = $config['fallback_to_builtin'] ?? true;
            $htmlRenderer = new \LengthOfRope\TreeHouse\Errors\Rendering\HtmlRenderer(
                $viewFactory,
                $templatePath,
                $fallbackToBuiltIn
            );
            $renderManager->registerRenderer($htmlRenderer);
            
            return $renderManager;
        });

        // Register main error handler
        $this->container->singleton('error.handler', function () {
            $config = $this->config['errors'] ?? [];
            $config['debug'] = $this->isDebugMode(); // Explicitly set debug mode
            
            $errorHandler = new ErrorHandler(
                $this->make('error.classifier'),
                $this->make('error.context'),
                $this->make('error.logger'),
                $config,
                $this->make('error.renderer')
            );
            
            return $errorHandler;
        });
    }

    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void
    {
        // Only initialize if database configuration exists
        if (isset($this->config['database'])) {
            try {
                $dbConfig = $this->config['database'];
                $defaultConnection = $dbConfig['default'] ?? 'mysql';
                $connectionConfig = $dbConfig['connections'][$defaultConnection] ?? [];
                
                if (!empty($connectionConfig)) {
                    $connection = new Connection($connectionConfig);
                    
                    // Set the connection directly on ActiveRecord
                    ActiveRecord::setConnection($connection);
                    
                    // Also register in container for dependency injection
                    $this->container->singleton('db', fn() => $connection);
                }
            } catch (\Exception $e) {
                // Silently fail if database cannot be initialized
                // This allows the application to work without a database
            }
        }
    }

    /**
     * Automatically load routes from framework defaults and application routes
     */
    private function autoLoadRoutes(): void
    {
        // Load framework default routes first
        $frameworkRoutesPath = $this->basePath . '/config/routes';
        if (is_dir($frameworkRoutesPath)) {
            $frameworkRoutes = glob($frameworkRoutesPath . '/*.php');
            foreach ($frameworkRoutes as $file) {
                $this->loadRoutes($file);
            }
        }
    }

    /**
     * Load environment variables
     */
    private function loadEnvironment(): void
    {
        // Load .env file from the base path
        $envPath = $this->basePath . '/.env';
        if (file_exists($envPath)) {
            Env::load($envPath);
        } else {
            // Fallback to automatic loading
            Env::loadIfNeeded();
        }
    }

    /**
     * Auto-load configuration from standard location
     */
    private function autoLoadConfiguration(): void
    {
        $configPath = $this->basePath . '/config';
        if (is_dir($configPath)) {
            $this->loadConfiguration($configPath);
        }
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
        
        // Initialize database connection now that configuration is loaded
        $this->initializeDatabase();
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
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle an exception and return an appropriate response
     *
     * @param \Throwable $exception The exception to handle
     * @param Request|null $request The HTTP request (if available)
     * @return Response The error response
     */
    private function handleException(\Throwable $exception, ?Request $request = null): Response
    {
        try {
            // Use the comprehensive error handler if available
            /** @var ErrorHandler $errorHandler */
            $errorHandler = $this->container->make('error.handler');
            return $errorHandler->handle($exception, $request);
        } catch (\Throwable $handlerException) {
            // If the error handler fails, fall back to basic handling
            // Log the handler failure if possible
            error_log("Error handler failed: " . $handlerException->getMessage());
        }

        // Fallback to basic error handling
        return $this->basicErrorHandling($exception);
    }

    /**
     * Basic error handling fallback
     *
     * @param \Throwable $exception The exception to handle
     * @return Response The error response
     */
    private function basicErrorHandling(\Throwable $exception): Response
    {
        // Determine status code based on exception type
        $statusCode = 500; // Default
        
        if ($exception instanceof RouteNotFoundException) {
            $statusCode = 404;
        } elseif (method_exists($exception, 'getStatusCode')) {
            $statusCode = call_user_func([$exception, 'getStatusCode']);
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
        // Check configuration first - if explicitly set, use that
        if (isset($this->config['app']['debug'])) {
            return (bool) $this->config['app']['debug'];
        }
        
        // Fall back to environment variable (handle both boolean and string values)
        if (isset($_ENV['APP_DEBUG'])) {
            $envDebug = $_ENV['APP_DEBUG'];
            if (is_bool($envDebug)) {
                return $envDebug;
            }
            return in_array(strtolower((string)$envDebug), ['true', '1', 'yes', 'on']);
        }
        
        return false;
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

    /**
     * Register middleware aliases on the router
     */
    private function registerMiddlewareAliases(): void
    {
        // Register built-in middleware aliases
        $aliases = [
            'role' => 'LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware',
            'permission' => 'LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware',
            'throttle' => 'LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitMiddleware',
        ];
        
        // Register them on the router
        $this->router->middlewareAliases($aliases);
    }
}