# TreeHouse Foundation Layer

The Foundation layer provides the core application infrastructure for the TreeHouse framework. It includes the main application container, dependency injection container, and bootstrapping functionality.

## Overview

The Foundation layer consists of two main components:
- **Application**: Main application bootstrap and request handling
- **Container**: Dependency injection container for service management

## Components

### Application

The `Application` class serves as the main entry point and bootstrap for TreeHouse applications.

#### Key Features

- **Service Container Integration**: Built-in dependency injection container
- **Configuration Management**: Load and manage application configuration
- **Route Handling**: Integrate with the router for HTTP request processing
- **Exception Handling**: Centralized error handling with debug support
- **Core Services**: Automatic registration of framework services

#### Usage

```php
use LengthOfRope\TreeHouse\Foundation\Application;

// Create application instance
$app = new Application('/path/to/app');

// Load configuration
$app->loadConfiguration('/path/to/config');

// Load routes
$app->loadRoutes('/path/to/routes.php');

// Handle incoming request
$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();
```

#### Configuration

The application automatically loads configuration files from a directory:

```php
// Load all .php files from config directory
$app->loadConfiguration('./config');

// Access configuration values
$debugMode = $app->config('app.debug', false);
$cacheDriver = $app->config('cache.default', 'file');

// Set configuration values
$app->setConfig('app.timezone', 'UTC');
```

#### Core Services

The application automatically registers these core services:

- **router**: Router instance for handling HTTP requests
- **cache**: Cache manager for application caching
- **view**: View factory for template rendering

### Container

The `Container` class provides dependency injection and service location functionality.

#### Key Features

- **Service Binding**: Bind classes, closures, or instances to service names
- **Singleton Support**: Register services as singletons for shared instances
- **Automatic Resolution**: Automatically resolve class dependencies via reflection
- **Circular Dependency Detection**: Prevent infinite loops in dependency resolution
- **Service Aliases**: Create aliases for services

#### Usage

```php
use LengthOfRope\TreeHouse\Foundation\Container;

$container = new Container();

// Bind a service
$container->bind('logger', function() {
    return new FileLogger('/var/log/app.log');
});

// Register a singleton
$container->singleton('database', function() {
    return new DatabaseConnection('localhost', 'mydb');
});

// Register an instance
$container->instance('config', $configArray);

// Resolve services
$logger = $container->make('logger');
$database = $container->make('database');

// Create aliases
$container->alias('db', 'database');
$db = $container->make('db'); // Same as database
```

#### Automatic Dependency Injection

The container can automatically resolve class dependencies:

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

1. **Initialization**: Create container and router instances
2. **Service Registration**: Register core framework services
3. **Configuration Loading**: Load application configuration files
4. **Route Registration**: Register application routes
5. **Request Handling**: Process incoming HTTP requests

## Core Services

### Router Service

```php
$router = $app->make('router');
$router->get('/users', [UserController::class, 'index']);
```

### Cache Service

```php
$cache = $app->make('cache');
$cache->put('key', 'value', 3600);
$value = $cache->get('key');
```

### View Service

```php
$view = $app->make('view');
$content = $view->make('welcome', ['name' => 'John']);
```

## Error Handling

The application provides centralized exception handling:

```php
try {
    $response = $app->handle($request);
} catch (\Throwable $e) {
    // Application handles exceptions and returns appropriate responses
    // - 404 for RouteNotFoundException
    // - 500 for general exceptions
    // - Debug information in debug mode
}
```

## Configuration Structure

Expected configuration structure:

```
config/
├── app.php          // Application settings
├── cache.php        // Cache configuration
├── database.php     // Database settings
├── view.php         // View configuration
└── ...
```

Example `config/app.php`:

```php
<?php
return [
    'debug' => env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key' => env('APP_KEY'),
];
```

## Environment Integration

The Foundation layer integrates with environment variables:

```php
// Debug mode from environment
$debug = $app->config('app.debug') || $_ENV['APP_DEBUG'] === 'true';

// Other environment variables
$databaseUrl = $_ENV['DATABASE_URL'] ?? 'sqlite:memory:';
```

## Best Practices

### Service Registration

```php
// Register services in a service provider
$app->singleton('emailService', function() {
    return new EmailService(
        $app->config('mail.driver'),
        $app->config('mail.host')
    );
});
```

### Configuration Management

```php
// Use dot notation for nested configuration
$app->setConfig('database.connections.mysql.host', 'localhost');
$host = $app->config('database.connections.mysql.host');
```

### Dependency Injection

```php
// Type-hint dependencies in constructors
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway,
        private Logger $logger,
        private EmailService $email
    ) {}
}

// Container automatically resolves all dependencies
$orderService = $app->make(OrderService::class);
```

## Integration with Other Layers

The Foundation layer integrates seamlessly with other TreeHouse components:

- **Router**: Handles HTTP request routing and middleware
- **HTTP**: Processes requests and responses
- **Cache**: Provides caching services for the application
- **View**: Renders templates and manages view compilation
- **Database**: Manages database connections and queries
- **Auth**: Handles authentication and authorization

This layer forms the backbone of TreeHouse applications, providing the essential infrastructure needed for building robust web applications.