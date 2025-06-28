# TreeHouse Router System

The TreeHouse Router System provides a powerful and flexible HTTP routing solution with middleware support, route groups, parameter constraints, and seamless integration with the TreeHouse Support classes.

## Components

### Core Classes

- **[`Router`](Router.php)** - Main HTTP router for registering routes and dispatching requests
- **[`Route`](Route.php)** - Individual route representation with parameters and constraints
- **[`RouteCollection`](RouteCollection.php)** - Route storage and matching engine
- **[`MiddlewareInterface`](Middleware/MiddlewareInterface.php)** - Contract for middleware components
- **[`MiddlewareStack`](Middleware/MiddlewareStack.php)** - Middleware execution pipeline

## Features

### Route Registration

```php
use LengthOfRope\TreeHouse\Router\Router;

$router = new Router();

// Basic routes
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
$router->put('/users/{id}', 'UserController@update');
$router->delete('/users/{id}', 'UserController@destroy');

// Route with closure
$router->get('/hello', function($request) {
    return 'Hello World!';
});

// Multiple HTTP methods
$router->match(['GET', 'POST'], '/contact', 'ContactController@handle');

// Any HTTP method
$router->any('/webhook', 'WebhookController@handle');
```

### Route Parameters

```php
// Required parameters
$router->get('/users/{id}', 'UserController@show');

// Optional parameters
$router->get('/posts/{slug?}', 'PostController@show');

// Parameter constraints
$router->get('/users/{id}', 'UserController@show')
    ->where('id', '\d+');

// Multiple constraints
$router->get('/posts/{year}/{month}', 'PostController@archive')
    ->where(['year' => '\d{4}', 'month' => '\d{2}']);

// Parameter defaults
$router->get('/search/{query?}', 'SearchController@index')
    ->defaults('query', '');
```

### Named Routes

```php
// Named route
$router->get('/users/{id}', 'UserController@show')
    ->name('users.show');

// Generate URL
$url = $router->url('users.show', ['id' => 123]);
// Result: /users/123
```

### Route Groups

```php
// Group with prefix
$router->group(['prefix' => 'api/v1'], function($router) {
    $router->get('/users', 'Api\UserController@index');
    $router->post('/users', 'Api\UserController@store');
});

// Group with middleware
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', 'DashboardController@index');
    $router->get('/profile', 'ProfileController@show');
});

// Group with multiple attributes
$router->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'admin'],
    'name' => 'admin.'
], function($router) {
    $router->get('/users', 'Admin\UserController@index')
        ->name('users.index'); // Full name: admin.users.index
});
```

### Middleware

```php
// Global middleware
$router->middleware('cors');
$router->middleware(['throttle', 'auth']);

// Route-specific middleware
$router->get('/admin', 'AdminController@index')
    ->middleware('auth');

// Middleware with parameters
$router->get('/api/data', 'ApiController@data')
    ->middleware('throttle:60,1');

// Authorization middleware
$router->get('/admin', 'AdminController@index')
    ->middleware('role:admin');

$router->get('/posts/manage', 'PostController@manage')
    ->middleware('role:admin,editor');

$router->get('/users/create', 'UserController@create')
    ->middleware('permission:manage-users');

// Middleware aliases
$router->middlewareAliases([
    'auth' => 'App\Middleware\AuthMiddleware',
    'role' => 'LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware',
    'permission' => 'LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware',
    'throttle' => 'App\Middleware\ThrottleMiddleware',
]);
```

### Request Dispatching

```php
use LengthOfRope\TreeHouse\Http\Request;

// Create request from globals
$request = Request::createFromGlobals();

// Dispatch request
$response = $router->dispatch($request);

// Send response
$response->send();
```

## Support Class Integration

The Router System leverages TreeHouse Support classes for enhanced functionality:

### Collection Integration

- Route collections use [`Collection`](../Support/Collection.php) for fluent operations
- Middleware stacks utilize Collection for pipeline management
- Route parameters and groups benefit from Collection methods

```php
// Get all GET routes
$getRoutes = $router->getRoutes()->getRoutesByMethod('GET');

// Filter routes by middleware
$authRoutes = $router->getRoutes()->getRoutesByMiddleware('auth');

// Group routes by pattern
$groupedRoutes = $router->getRoutes()->groupBy('uri');
```

### Array Utilities

- Route parameter extraction uses [`Arr`](../Support/Arr.php) utilities
- Route group attribute merging leverages Arr methods
- Parameter constraint handling benefits from Arr operations

```php
// Route uses Arr::wrap for method normalization
$route = new Route('GET', '/users', $action); // Internally uses Arr::wrap(['GET'])

// Parameter defaults use Arr utilities
$route->defaults(['page' => 1, 'limit' => 10]);
```

### Helper Functions

- Route parameter access uses `dataGet()` helper
- Nested parameter extraction leverages dot notation
- Route attribute merging uses helper functions

## Advanced Features

### Route Model Binding

```php
// Automatic model binding (when integrated with models)
$router->get('/users/{user}', function($request, User $user) {
    return $user->toArray();
});
```

### Route Caching

```php
// Get route collection for caching
$routes = $router->getRoutes();
$routeData = $routes->toArray();

// Cache route data for production
file_put_contents('routes.cache', serialize($routeData));
```

### Debug Information

```php
// Get comprehensive debug info
$debugInfo = $router->getDebugInfo();

// Includes:
// - Route collection statistics
// - Middleware configuration
// - Current route information
// - Route parameters
// - Group stack state
```

## Error Handling

The router handles various error scenarios:

- **404 Not Found** - When no route matches the request
- **405 Method Not Allowed** - When route exists but method doesn't match
- **Invalid Route Action** - When route action is malformed
- **Missing Controller** - When controller class doesn't exist
- **Missing Method** - When controller method doesn't exist

## Performance Considerations

- Routes are indexed by HTTP method for O(1) lookup
- Named routes are stored in a hash map for fast URL generation
- Middleware stack uses efficient pipeline pattern
- Route compilation is cached to avoid repeated regex compilation
- Collection operations are optimized for common use cases

## Integration Examples

### Basic Application Setup

```php
use LengthOfRope\TreeHouse\Router\Router;
use LengthOfRope\TreeHouse\Http\Request;

// Create router
$router = new Router();

// Register global middleware
$router->middleware(['cors', 'throttle:1000,60']);

// Register middleware aliases
$router->middlewareAliases([
    'auth' => 'App\Middleware\AuthMiddleware',
    'guest' => 'App\Middleware\GuestMiddleware',
    'admin' => 'App\Middleware\AdminMiddleware',
]);

// Register routes
require 'routes/web.php';
require 'routes/api.php';

// Handle request
$request = Request::createFromGlobals();
$response = $router->dispatch($request);
$response->send();
```

### API Routes Example

```php
// routes/api.php
$router->group(['prefix' => 'api/v1', 'middleware' => 'api'], function($router) {
    // Public routes
    $router->post('/auth/login', 'Api\AuthController@login');
    $router->post('/auth/register', 'Api\AuthController@register');
    
    // Protected routes
    $router->group(['middleware' => 'auth:api'], function($router) {
        $router->get('/user', 'Api\UserController@profile');
        $router->put('/user', 'Api\UserController@update');
        
        // Admin routes
        $router->group(['middleware' => 'admin', 'prefix' => 'admin'], function($router) {
            $router->get('/users', 'Api\Admin\UserController@index');
            $router->delete('/users/{id}', 'Api\Admin\UserController@destroy')
                ->where('id', '\d+');
        });
    });
});
```

## Authorization Middleware

TreeHouse includes built-in authorization middleware for protecting routes based on user roles and permissions.

### Role-Based Route Protection

```php
// Single role requirement
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', 'AdminController@users');
    $router->post('/admin/settings', 'AdminController@settings');
});

// Multiple roles (OR logic) - user needs ANY of these roles
$router->group(['middleware' => 'role:admin,editor'], function($router) {
    $router->get('/posts/manage', 'PostController@manage');
    $router->post('/posts', 'PostController@store');
});

// Individual route protection
$router->get('/dashboard', 'DashboardController@index')
    ->middleware('role:admin,editor,viewer');
```

### Permission-Based Route Protection

```php
// Single permission requirement
$router->get('/users/create', 'UserController@create')
    ->middleware('permission:manage-users');

// Multiple permissions (OR logic) - user needs ANY of these permissions
$router->group(['middleware' => 'permission:edit-posts,delete-posts'], function($router) {
    $router->get('/posts/admin', 'PostController@admin');
    $router->delete('/posts/{id}', 'PostController@destroy');
});

// Specific permission for sensitive operations
$router->delete('/users/{id}', 'UserController@destroy')
    ->middleware('permission:delete-users');
```

### Combined Authorization

```php
// Multiple middleware layers
$router->group([
    'middleware' => ['auth', 'role:admin'],
    'prefix' => 'admin'
], function($router) {
    $router->get('/dashboard', 'AdminController@dashboard');
    
    // Additional permission check
    $router->group(['middleware' => 'permission:manage-users'], function($router) {
        $router->get('/users', 'AdminController@users');
        $router->post('/users', 'AdminController@createUser');
    });
});
```

### Authorization Responses

The authorization middleware provides appropriate HTTP responses:

- **401 Unauthorized** - User is not authenticated
- **403 Forbidden** - User lacks required role/permission
- **JSON responses** - For AJAX requests with error details
- **HTML responses** - For regular browser requests

### Middleware Registration

Register the authorization middleware aliases in your router setup:

```php
$router->middlewareAliases([
    'auth' => 'App\Middleware\AuthMiddleware',
    'role' => 'LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware',
    'permission' => 'LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware',
]);
```

The TreeHouse Router System provides a robust foundation for handling HTTP routing in web applications, with excellent performance characteristics, comprehensive authorization capabilities, and seamless integration with other TreeHouse components.