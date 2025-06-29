# TreeHouse Router System

## Overview

The Router system provides comprehensive HTTP request routing with support for RESTful routes, middleware, route groups, parameter binding, and named routes. This layer handles all incoming HTTP requests and dispatches them to appropriate controllers or callbacks.

### Core Classes

- **[`Router`](Router.php:23)**: Main routing engine with request dispatching
- **[`Route`](Route.php:20)**: Individual route definition with parameters and middleware
- **[`RouteCollection`](RouteCollection.php:23)**: Collection of routes with matching and indexing
- **[`MiddlewareStack`](Middleware/MiddlewareStack.php:1)**: Middleware execution pipeline
- **[`PermissionMiddleware`](Middleware/PermissionMiddleware.php:1)**: RBAC permission-based route protection
- **[`RoleMiddleware`](Middleware/RoleMiddleware.php:1)**: RBAC role-based route protection

### Route Registration

The router supports all standard HTTP methods with fluent registration:

```php
// Basic route registration
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
$router->put('/users/{id}', 'UserController@update');
$router->patch('/users/{id}', 'UserController@patch');
$router->delete('/users/{id}', 'UserController@destroy');
$router->options('/users', 'UserController@options');

// Multiple methods
$router->match(['GET', 'POST'], '/contact', 'ContactController@handle');
$router->any('/webhook', 'WebhookController@handle');

// Closure routes
$router->get('/hello', function(Request $request) {
    return new Response('Hello World!');
});

// Array-based controller routes
$router->get('/profile', [ProfileController::class, 'show']);
```

### Route Parameters

Routes support dynamic parameters with optional constraints and defaults:

```php
// Basic parameters
$router->get('/users/{id}', 'UserController@show');
$router->get('/posts/{slug}', 'PostController@show');

// Optional parameters
$router->get('/posts/{id}/{slug?}', 'PostController@show');

// Parameter constraints
$router->get('/users/{id}', 'UserController@show')
    ->where('id', '[0-9]+');

$router->get('/posts/{slug}', 'PostController@show')
    ->where('slug', '[a-z0-9-]+');

// Multiple constraints
$router->get('/archive/{year}/{month}', 'ArchiveController@show')
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}']);

// Default values
$router->get('/search/{query?}', 'SearchController@index')
    ->defaults('query', '');
```

### Named Routes

Routes can be named for URL generation and reference:

```php
// Named routes
$router->get('/users/{id}', 'UserController@show')->name('users.show');
$router->post('/users', 'UserController@store')->name('users.store');

// Generate URLs from named routes
$url = $router->url('users.show', ['id' => 123]);
// Result: /users/123

$url = $router->url('users.store');
// Result: /users
```

### Route Groups

Group routes with shared attributes like prefixes, middleware, and constraints:

```php
// Basic grouping with prefix
$router->group(['prefix' => 'api'], function($router) {
    $router->get('/users', 'Api\UserController@index');
    $router->get('/posts', 'Api\PostController@index');
});
// Routes: /api/users, /api/posts

// Middleware groups
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', 'DashboardController@index');
    $router->get('/profile', 'ProfileController@show');
});

// Combined attributes
$router->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'role:admin'],
    'name' => 'admin.'
], function($router) {
    $router->get('/users', 'Admin\UserController@index')->name('users.index');
    $router->get('/settings', 'Admin\SettingsController@index')->name('settings');
});
// Named routes: admin.users.index, admin.settings

// Nested groups
$router->group(['prefix' => 'api'], function($router) {
    $router->group(['prefix' => 'v1'], function($router) {
        $router->get('/users', 'Api\V1\UserController@index');
    });
});
// Route: /api/v1/users
```

### Middleware

Middleware provides request/response filtering and processing:

```php
// Global middleware (applies to all routes)
$router->middleware('cors');
$router->middleware(['throttle', 'csrf']);

// Route-specific middleware
$router->get('/admin', 'AdminController@index')
    ->middleware('auth');

$router->post('/api/data', 'ApiController@store')
    ->middleware(['auth', 'throttle:60,1']);

// Middleware aliases
$router->middlewareAliases([
    'auth' => AuthMiddleware::class,
    'role' => RoleMiddleware::class,
    'permission' => PermissionMiddleware::class,
    'throttle' => ThrottleMiddleware::class,
]);

// Using aliased middleware
$router->get('/admin', 'AdminController@index')
    ->middleware(['auth', 'role:admin']);

$router->get('/posts/create', 'PostController@create')
    ->middleware(['auth', 'permission:posts.create']);
```

### Request Dispatching

The router handles request dispatching with middleware execution:

```php
// Basic dispatching
$request = Request::createFromGlobals();
$response = $router->dispatch($request);
$response->send();

// Access current route information
$currentRoute = $router->getCurrentRoute();
$parameters = $router->getCurrentParameters();
$userId = $router->getParameter('id', null);

// Route execution flow:
// 1. Determine HTTP method (with method spoofing support)
// 2. Match route by method and URI
// 3. Extract route parameters
// 4. Build middleware stack (global + route-specific)
// 5. Execute middleware pipeline
// 6. Execute route action (controller or closure)
// 7. Prepare and return response
```

### HTTP Method Spoofing

The router supports HTTP method spoofing for HTML forms, which can only send GET and POST requests:

```php
// HTML form with method spoofing
<form method="POST" action="/users/123">
    <input type="hidden" name="_method" value="PUT">
    <input type="text" name="name" value="John Doe">
    <button type="submit">Update User</button>
</form>

// This form will be routed to a PUT route handler
$router->put('/users/{id}', 'UserController@update');
```

**Method Spoofing Rules:**
- Only works with POST requests
- Uses `_method` parameter in form data
- Supports: `PUT`, `PATCH`, `DELETE`, `OPTIONS`
- Case-insensitive (automatically converted to uppercase)
- Invalid methods are ignored (falls back to POST)

```php
// Examples of valid method spoofing
<input type="hidden" name="_method" value="PUT">     <!-- Valid -->
<input type="hidden" name="_method" value="patch">   <!-- Valid (converted to PATCH) -->
<input type="hidden" name="_method" value="DELETE">  <!-- Valid -->
<input type="hidden" name="_method" value="GET">     <!-- Invalid (ignored) -->
<input type="hidden" name="_method" value="CUSTOM">  <!-- Invalid (ignored) -->
```

### Helper Functions

The router includes helper functions to simplify form creation with method spoofing:

```php
// Include the helper functions
require_once 'src/TreeHouse/View/helpers.php';

// Generate method spoofing field
echo methodField('PUT');
// Output: <input type="hidden" name="_method" value="PUT">

echo methodField('DELETE');
// Output: <input type="hidden" name="_method" value="DELETE">

// Generate CSRF protection field (placeholder implementation)
echo csrfField();
// Output: <input type="hidden" name="_token" value="random_token">

// Generate both method and CSRF fields
echo formMethod('PATCH');
// Output: <input type="hidden" name="_method" value="PATCH">
//         <input type="hidden" name="_token" value="random_token">

// Generate method field without CSRF
echo formMethod('DELETE', false);
// Output: <input type="hidden" name="_method" value="DELETE">
```

**Complete Form Examples:**

```html
<!-- Update form using helper functions -->
<form method="POST" action="/users/123">
    <?php echo formMethod('PUT'); ?>
    <input type="text" name="name" value="John Doe">
    <input type="email" name="email" value="john@example.com">
    <button type="submit">Update User</button>
</form>

<!-- Delete form -->
<form method="POST" action="/users/123">
    <?php echo methodField('DELETE'); ?>
    <button type="submit" onclick="return confirm('Are you sure?')">Delete User</button>
</form>

<!-- Manual method spoofing (without helpers) -->
<form method="POST" action="/posts/456">
    <input type="hidden" name="_method" value="PATCH">
    <input type="text" name="title" value="Post Title">
    <textarea name="content">Post content...</textarea>
    <button type="submit">Update Post</button>
</form>
```

## Support Class Integration

The router integrates with TreeHouse support classes for enhanced functionality.

### Collection Integration

Routes are managed using the Collection class for powerful querying:

```php
// Get all routes
$allRoutes = $router->getRoutes();

// Filter routes by method
$getRoutes = $router->getRoutes()->getRoutesByMethod('GET');

// Filter by middleware
$authRoutes = $router->getRoutes()->getRoutesByMiddleware('auth');

// Group routes by pattern
$groupedRoutes = $router->getRoutes()->groupBy('method');
```

### Array Utilities

Route parameters and attributes use array utilities:

```php
// Route parameter extraction
$route = new Route(['GET'], '/users/{id}/posts/{slug}', $action);
$parameters = $route->extractParameters('/users/123/posts/my-post');
// Result: ['id' => '123', 'slug' => 'my-post']

// Route URL generation
$url = $route->url(['id' => 123, 'slug' => 'my-post']);
// Result: /users/123/posts/my-post
```

### Helper Functions

Global helper functions for common routing operations:

```php
// Generate route URLs (if helper functions are available)
$url = route('users.show', ['id' => 123]);
$currentRoute = current_route();
$routeParameter = route_parameter('id');
```

### Route Model Binding

Automatic model binding for route parameters:

```php
// Implicit binding (if implemented)
$router->get('/users/{user}', function(User $user) {
    return $user->toJson();
});

// Explicit binding (if implemented)
$router->bind('user', function($value) {
    return User::find($value) ?? abort(404);
});
```

### Route Caching

Route compilation and caching for performance:

```php
// Route compilation (internal)
$route = new Route(['GET'], '/users/{id}', $action);
$compiledPattern = $route->getCompiledPattern();
// Result: #^/users/([^/]+)$#

// Route collection indexing
$routes = new RouteCollection();
$routes->add($route);
$routes->rebuildIndexes(); // Optimizes route matching
```

### Debug Information

Comprehensive debugging information for development:

```php
// Router debug info
$debugInfo = $router->getDebugInfo();
// Returns: routes, middleware, current_route, current_parameters, group_stack

// Route collection debug info
$routeDebugInfo = $router->getRoutes()->getDebugInfo();
// Returns: total_routes, routes_by_method, named_routes, middleware_usage
```

## Error Handling

The router provides comprehensive error handling:

```php
// Route not found (404)
try {
    $response = $router->dispatch($request);
} catch (RouteNotFoundException $e) {
    $response = new Response('Page Not Found', 404);
}

// Invalid controller/method
try {
    $response = $router->dispatch($request);
} catch (InvalidArgumentException $e) {
    $response = new Response('Server Error', 500);
}
```

## Performance Considerations

- **Route Compilation**: Routes are compiled to regex patterns for fast matching
- **Collection Indexing**: Routes are indexed by method and pattern for efficient lookup
- **Middleware Caching**: Middleware stacks are built once per request
- **Parameter Extraction**: Optimized parameter extraction with compiled patterns

### Basic Application Setup

```php
// Create router instance
$router = new Router();

// Register middleware aliases
$router->middlewareAliases([
    'auth' => AuthMiddleware::class,
    'role' => RoleMiddleware::class,
    'permission' => PermissionMiddleware::class,
]);

// Register global middleware
$router->middleware(['cors', 'throttle']);

// Register routes
$router->get('/', 'HomeController@index')->name('home');
$router->get('/about', 'PageController@about')->name('about');

// Protected routes
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', 'DashboardController@index')->name('dashboard');
    $router->get('/profile', 'ProfileController@show')->name('profile');
});

// API routes
$router->group(['prefix' => 'api', 'middleware' => 'api'], function($router) {
    $router->get('/users', 'Api\UserController@index');
    $router->post('/users', 'Api\UserController@store');
    $router->get('/users/{id}', 'Api\UserController@show');
    $router->put('/users/{id}', 'Api\UserController@update');
    $router->delete('/users/{id}', 'Api\UserController@destroy');
});

// Handle request
$request = Request::createFromGlobals();
$response = $router->dispatch($request);
$response->send();
```

### API Routes Example

```php
// RESTful API with versioning
$router->group(['prefix' => 'api'], function($router) {
    $router->group(['prefix' => 'v1'], function($router) {
        // Public endpoints
        $router->post('/auth/login', 'Api\V1\AuthController@login');
        $router->post('/auth/register', 'Api\V1\AuthController@register');
        
        // Protected endpoints
        $router->group(['middleware' => 'auth:api'], function($router) {
            $router->get('/user', 'Api\V1\UserController@profile');
            $router->put('/user', 'Api\V1\UserController@updateProfile');
            
            // Resource routes
            $router->get('/posts', 'Api\V1\PostController@index');
            $router->post('/posts', 'Api\V1\PostController@store')
                ->middleware('permission:posts.create');
            $router->get('/posts/{id}', 'Api\V1\PostController@show');
            $router->put('/posts/{id}', 'Api\V1\PostController@update')
                ->middleware('permission:posts.update');
            $router->delete('/posts/{id}', 'Api\V1\PostController@destroy')
                ->middleware('permission:posts.delete');
        });
    });
});
```

## Authorization Middleware

The router includes built-in RBAC middleware for route protection:

### Role-Based Route Protection

```php
// Protect routes by user roles
$router->get('/admin', 'AdminController@index')
    ->middleware('role:admin');

$router->get('/moderator', 'ModeratorController@index')
    ->middleware('role:admin,moderator'); // Multiple roles (OR)

// Group protection
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', 'Admin\UserController@index');
    $router->get('/admin/settings', 'Admin\SettingsController@index');
});
```

### Permission-Based Route Protection

```php
// Protect routes by specific permissions
$router->get('/posts/create', 'PostController@create')
    ->middleware('permission:posts.create');

$router->put('/posts/{id}', 'PostController@update')
    ->middleware('permission:posts.update');

$router->delete('/posts/{id}', 'PostController@destroy')
    ->middleware('permission:posts.delete');

// Multiple permissions (user must have ALL)
$router->get('/admin/reports', 'ReportController@index')
    ->middleware('permission:reports.view,reports.export');
```

### Combined Authorization

```php
// Combine role and permission checks
$router->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'role:admin,manager']
], function($router) {
    $router->get('/dashboard', 'Admin\DashboardController@index');
    
    $router->get('/users', 'Admin\UserController@index')
        ->middleware('permission:users.view');
    
    $router->post('/users', 'Admin\UserController@store')
        ->middleware('permission:users.create');
});
```

### Authorization Responses

```php
// Middleware returns appropriate responses:
// - 401 Unauthorized: User not authenticated
// - 403 Forbidden: User lacks required role/permission
// - 200 OK: User authorized, continue to route action

// Custom authorization handling in controllers
class AdminController
{
    public function index(Request $request)
    {
        // User is guaranteed to have admin role due to middleware
        return view('admin.dashboard');
    }
}
```

### Middleware Registration

```php
// Register RBAC middleware in application bootstrap
$router->middlewareAliases([
    'role' => \LengthOfRope\TreeHouse\Router\Middleware\RoleMiddleware::class,
    'permission' => \LengthOfRope\TreeHouse\Router\Middleware\PermissionMiddleware::class,
]);

// Use in route definitions
$router->get('/admin', 'AdminController@index')
    ->middleware(['auth', 'role:admin']);
```

## Integration with Other Layers

The Router layer integrates with all other framework layers:

- **Foundation Layer**: Automatic service registration and dependency injection
- **Auth Layer**: User authentication and RBAC middleware integration
- **Http Layer**: Request/Response handling and middleware execution
- **View Layer**: Controller response rendering and template integration
- **Database Layer**: Route model binding and parameter resolution
- **Console Layer**: Route listing and debugging commands