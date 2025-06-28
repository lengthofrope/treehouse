# TreeHouse Foundation Layer

## Overview

The Foundation layer provides the core application infrastructure for the TreeHouse framework, including the main application container, dependency injection system, and service registration. This layer serves as the bootstrap and orchestration center for all framework components.

### Application

The [`Application`](Application.php:28) class is the main entry point and container for the TreeHouse framework. It handles application bootstrapping, service registration, configuration management, and request handling.

#### Key Features

- **Automatic Bootstrap**: Loads environment variables, configuration, and routes automatically
- **Service Container Integration**: Built-in dependency injection container with singleton support
- **Configuration Management**: Dot-notation configuration access with automatic loading
- **Route Management**: Automatic route loading from configuration directories
- **Error Handling**: Comprehensive exception handling with debug mode support
- **Environment Integration**: Automatic `.env` file loading and environment variable support

#### Usage

```php
// Create application instance
$app = new Application('/path/to/project');

// Handle HTTP request
$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();

// Access services
$router = $app->make('router');
$cache = $app->make('cache');
$view = $app->make('view');
$auth = $app->make('auth');
```

#### Configuration

The application automatically loads configuration from the `/config` directory:

```php
// Get configuration values
$dbConfig = $app->config('database.connections.mysql');
$debugMode = $app->config('app.debug', false);

// Set configuration values
$app->setConfig('app.timezone', 'UTC');
```

#### Core Services

The application automatically registers these core services:

- **router**: [`Router`](../Router/Router.php:1) instance for HTTP routing
- **cache**: [`CacheManager`](../Cache/CacheManager.php:1) for caching operations
- **view**: [`ViewFactory`](../View/ViewFactory.php:1) for template rendering
- **auth**: [`AuthManager`](../Auth/AuthManager.php:1) for authentication
- **session**: [`Session`](../Http/Session.php:1) for session management
- **db**: [`Connection`](../Database/Connection.php:1) for database operations

### Container

The [`Container`](Container.php:22) class provides a powerful dependency injection system with automatic dependency resolution, singleton support, and service binding.

#### Key Features

- **Service Binding**: Bind interfaces to implementations
- **Singleton Support**: Register services as singletons
- **Automatic Resolution**: Automatic constructor dependency injection
- **Circular Dependency Detection**: Prevents infinite resolution loops
- **Service Aliases**: Create aliases for service identifiers

#### Usage

```php
// Bind services
$container->bind('logger', LoggerImplementation::class);
$container->singleton('cache', CacheManager::class);

// Register instances
$container->instance('config', $configArray);

// Resolve services
$logger = $container->make('logger');
$cache = $container->make('cache');

// Create aliases
$container->alias('log', 'logger');
```

#### Automatic Dependency Injection

The container automatically resolves constructor dependencies:

```php
class UserService
{
    public function __construct(
        private DatabaseConnection $db,
        private Logger $logger
    ) {}
}

// Container automatically injects dependencies
$userService = $container->make(UserService::class);
```

## Application Bootstrap Process

1. **Environment Loading**: Loads `.env` file from application base path
2. **Configuration Loading**: Automatically loads all PHP files from `/config` directory
3. **Service Registration**: Registers core framework services in the container
4. **Database Initialization**: Sets up database connection if configuration exists
5. **Route Loading**: Loads route files from `/config/routes` directory

### Router Service

- Handles HTTP request routing and middleware execution
- Supports route parameters, middleware, and controller actions
- Automatic route loading from configuration files

### Cache Service

- File-based caching with configurable TTL
- Automatic cache directory creation
- Support for multiple cache stores

### View Service

- Template rendering with caching support
- Configurable view paths and cache directories
- Component and layout support

### Database Service

- ActiveRecord ORM integration
- Automatic connection setup from configuration
- Support for multiple database connections

## Error Handling

The application provides comprehensive error handling:

- **Route Not Found**: Returns 404 responses for unmatched routes
- **Exception Handling**: Catches and formats all exceptions
- **Debug Mode**: Shows detailed error information when enabled
- **Production Mode**: Returns user-friendly error messages

```php
// Debug mode configuration
'app' => [
    'debug' => env('APP_DEBUG', false),
]
```

## Configuration Structure

Configuration files are automatically loaded from the `/config` directory:

```
config/
├── app.php          # Application settings
├── auth.php         # Authentication configuration
├── cache.php        # Cache configuration
├── database.php     # Database connections
├── permissions.php  # RBAC permissions
├── view.php         # View engine settings
└── routes/
    ├── web.php      # Web routes
    └── api.php      # API routes
```

## Environment Integration

The Foundation layer integrates with environment variables:

```php
// Environment variable access
$dbHost = env('DB_HOST', 'localhost');
$appDebug = env('APP_DEBUG', false);

// Configuration with environment fallbacks
'database' => [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 3306),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
]
```

### Service Registration

Services are registered during application bootstrap:

```php
// Register custom services
$app->singleton('custom.service', function () {
    return new CustomService();
});

// Access registered services
$service = $app->make('custom.service');
```

### Configuration Management

Configuration supports dot notation for nested access:

```php
// Nested configuration access
$mysqlConfig = $app->config('database.connections.mysql');
$cacheEnabled = $app->config('cache.enabled', true);
```

### Dependency Injection

The container provides automatic dependency injection:

```php
// Service with dependencies
class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}
}

// Automatic resolution
$emailService = $app->make(EmailService::class);
```

## Integration with Other Layers

The Foundation layer integrates with all other framework layers:

- **Router Layer**: Provides routing services and middleware execution
- **Database Layer**: Manages database connections and ActiveRecord setup
- **Auth Layer**: Handles authentication and authorization services
- **View Layer**: Manages template rendering and view compilation
- **Console Layer**: Provides application context for console commands
- **Cache Layer**: Manages caching services and storage backends