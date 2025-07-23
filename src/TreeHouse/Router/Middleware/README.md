# TreeHouse JWT Middleware System

The TreeHouse framework provides a comprehensive JWT middleware system for protecting routes with authentication, authorization, and role-based access control. This system seamlessly integrates with the existing authentication framework and supports both stateless JWT and session-based authentication.

## 📋 Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Middleware Components](#middleware-components)
- [Usage Examples](#usage-examples)
- [Route Protection Helper](#route-protection-helper)
- [Configuration](#configuration)
- [Testing](#testing)
- [Advanced Usage](#advanced-usage)
- [Best Practices](#best-practices)

## 🎯 Overview

The JWT middleware system consists of four main components:

1. **AuthMiddleware** - Core authentication middleware supporting multiple guards
2. **JwtMiddleware** - Dedicated JWT-only authentication middleware
3. **PermissionMiddleware** - Permission-based access control
4. **RoleMiddleware** - Role-based access control
5. **RouteProtectionHelper** - Fluent API for building protection chains

### Key Features

- **Multi-Guard Support**: Use multiple authentication guards (JWT, session, etc.)
- **Stateless Authentication**: JWT-based authentication without server-side sessions
- **Role & Permission Control**: Fine-grained access control
- **Automatic Token Extraction**: From headers, cookies, and query parameters
- **JSON & HTML Responses**: Proper error responses for APIs and web pages
- **CORS Support**: Built-in CORS headers for API endpoints
- **Fluent Configuration**: Easy-to-use helper for complex protection scenarios

## 🚀 Quick Start

### 1. Basic JWT Authentication

```php
// Protect a route with JWT authentication
Route::middleware('jwt:api')->get('/api/profile', function () {
    return auth('api')->user();
});

// Or use the helper
Route::middleware(RouteProtectionHelper::api())->get('/api/users', 'UserController@index');
```

### 2. Role-Based Protection

```php
// Require admin role
Route::middleware(['jwt:api', 'role:admin'])->get('/admin/dashboard', 'AdminController@dashboard');

// Or with helper
Route::middleware(RouteProtectionHelper::admin('api'))->group(function () {
    Route::get('/admin/users', 'AdminController@users');
    Route::get('/admin/settings', 'AdminController@settings');
});
```

### 3. Permission-Based Protection

```php
// Require specific permissions
Route::middleware(['jwt:api', 'permission:edit-posts,delete-posts'])->group(function () {
    Route::put('/posts/{id}', 'PostController@update');
    Route::delete('/posts/{id}', 'PostController@destroy');
});
```

## 🛡️ Middleware Components

### AuthMiddleware

Core authentication middleware that supports multiple guards.

```php
// Single guard
Route::middleware('auth:api')->get('/protected', $handler);

// Multiple guards (tries in order)
Route::middleware('auth:web,api')->get('/flexible', $handler);

// Default guard
Route::middleware('auth')->get('/default', $handler);
```

**Features:**
- Supports any authentication guard
- Tries multiple guards in order
- Sets request context for JWT guards
- Proper error responses with guard information

### JwtMiddleware

Dedicated JWT-only authentication middleware for APIs.

```php
// Single JWT guard
Route::middleware('jwt:api')->get('/api/data', $handler);

// Multiple JWT guards
Route::middleware('jwt:api,mobile')->get('/api/flexible', $handler);

// Default JWT guards (api, mobile)
Route::middleware('jwt')->get('/api/default', $handler);
```

**Features:**
- Only works with JWT guards
- Automatic CORS headers
- JWT-specific error responses
- Token expiration headers
- Debug information in development

### PermissionMiddleware

Permission-based access control middleware.

```php
// Single permission
Route::middleware('permission:edit-posts')->put('/posts/{id}', $handler);

// Multiple permissions (OR logic)
Route::middleware('permission:edit-posts,delete-posts')->get('/posts/manage', $handler);

// With specific guard
Route::middleware(['jwt:api', 'permission:admin-access:auth:api'])->get('/admin', $handler);
```

**Features:**
- Single or multiple permissions
- OR logic for multiple permissions
- Guard-specific permission checking
- Detailed error responses

### RoleMiddleware

Role-based access control middleware.

```php
// Single role
Route::middleware('role:admin')->get('/admin', $handler);

// Multiple roles (OR logic)
Route::middleware('role:admin,editor')->get('/content', $handler);

// With specific guard
Route::middleware(['jwt:api', 'role:admin:auth:api'])->get('/api/admin', $handler);
```

**Features:**
- Single or multiple roles
- OR logic for multiple roles
- Guard-specific role checking
- Detailed error responses

## 📚 Usage Examples

### API Authentication

```php
// Basic API routes
Route::prefix('api')->group(function () {
    // Public routes
    Route::post('/login', 'AuthController@login');
    Route::post('/register', 'AuthController@register');
    
    // Protected routes
    Route::middleware('jwt:api')->group(function () {
        Route::get('/profile', 'UserController@profile');
        Route::put('/profile', 'UserController@updateProfile');
        
        // Admin only
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/users', 'AdminController@users');
            Route::delete('/admin/users/{id}', 'AdminController@deleteUser');
        });
        
        // Content management
        Route::middleware('permission:edit-posts')->group(function () {
            Route::post('/posts', 'PostController@store');
            Route::put('/posts/{id}', 'PostController@update');
        });
    });
});
```

### Multi-Guard Authentication

```php
// Routes that work with both web and API authentication
Route::middleware('auth:web,api')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        // Check which guard was used
        if (auth('api')->check()) {
            return response()->json(['user' => $user]);
        }
        
        return view('dashboard', compact('user'));
    });
});
```

### Advanced Permission Control

```php
// Complex permission scenarios
Route::prefix('content')->middleware('jwt:api')->group(function () {
    // View content (basic permission)
    Route::get('/', 'ContentController@index')
         ->middleware('permission:view-content');
    
    // Edit content (multiple permissions)
    Route::middleware('permission:edit-content,moderate-content')->group(function () {
        Route::put('/{id}', 'ContentController@update');
        Route::patch('/{id}/status', 'ContentController@updateStatus');
    });
    
    // Admin content management
    Route::middleware(['role:admin', 'permission:manage-content'])->group(function () {
        Route::delete('/{id}', 'ContentController@destroy');
        Route::post('/bulk-action', 'ContentController@bulkAction');
    });
});
```

## 🔧 Route Protection Helper

The `RouteProtectionHelper` provides a fluent API for building complex middleware chains.

### Basic Usage

```php
use LengthOfRope\TreeHouse\Router\Middleware\RouteProtectionHelper;

// Web authentication with admin role
$middleware = RouteProtectionHelper::web('admin')->getMiddleware();
// Result: ['auth:web', 'role:admin:auth:web']

// API authentication with permissions and rate limiting
$middleware = RouteProtectionHelper::api(null, 'edit-posts', 100)->getMiddleware();
// Result: ['jwt:api', 'permission:edit-posts:auth:api', 'throttle:100,1']
```

### Advanced Chains

```php
// Complex protection chain
$protection = RouteProtectionHelper::create()
    ->jwt(['api', 'mobile'])
    ->roles(['admin', 'editor'], 'api')
    ->permissions(['edit-posts', 'publish-posts'], 'api')
    ->throttle(100, 5, 'sliding')
    ->custom('cors');

Route::middleware($protection->getMiddleware())->group(function () {
    // Highly protected routes
});
```

### Static Helpers

```php
// Pre-configured protection patterns
RouteProtectionHelper::web($roles, $permissions)              // Web authentication
RouteProtectionHelper::api($roles, $permissions, $rateLimit)  // API authentication
RouteProtectionHelper::admin($guards)                         // Admin-only
RouteProtectionHelper::multiAuth($guards, $roles, $perms)     // Multi-guard
RouteProtectionHelper::guest()                                // Guest-only
RouteProtectionHelper::optional($guards)                      // Optional auth
```

## ⚙️ Configuration

### Auth Configuration

```php
// config/auth.php
return [
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'mobile' => [
            'driver' => 'jwt',
            'provider' => 'jwt_users',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => 'users',
        ],
        'jwt_users' => [
            'driver' => 'jwt',
            'mode' => 'stateless',
            'user_claim' => 'user_data',
        ],
    ],
    
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => 'HS256',
        'ttl' => 900, // 15 minutes
    ],
];
```

### Middleware Registration

Middleware are automatically registered in the `MiddlewareStack` and `Application`:

```php
// Built-in aliases
'auth' => AuthMiddleware::class,
'jwt' => JwtMiddleware::class,
'role' => RoleMiddleware::class,
'permission' => PermissionMiddleware::class,
```

## 🧪 Testing

### Unit Tests

The middleware system includes comprehensive unit tests:

```bash
# Run all middleware tests
./vendor/bin/phpunit tests/Unit/Router/Middleware/

# Run specific middleware tests
./vendor/bin/phpunit tests/Unit/Router/Middleware/AuthMiddlewareTest.php
./vendor/bin/phpunit tests/Unit/Router/Middleware/JwtMiddlewareTest.php
```

### Test Coverage

- **AuthMiddleware**: 18 test methods covering guard selection, authentication flows, and error handling
- **JwtMiddleware**: 15 test methods covering JWT-specific functionality and CORS
- **PermissionMiddleware**: 12 test methods covering permission checking and guard integration  
- **RoleMiddleware**: 12 test methods covering role checking and guard integration
- **RouteProtectionHelper**: 25 test methods covering fluent API and static helpers
- **Integration Tests**: 12 test methods covering middleware stack integration

### Testing Your Middleware

```php
use Tests\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\JwtMiddleware;

class MyMiddlewareTest extends TestCase
{
    public function testJwtAuthenticationRequired()
    {
        $response = $this->get('/api/protected');
        $response->assertStatus(401);
        $response->assertJsonStructure([
            'error',
            'message',
            'code',
            'guards_tried'
        ]);
    }
    
    public function testValidJwtAllowsAccess()
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/api/protected');
        
        $response->assertStatus(200);
    }
}
```

## 🔧 Advanced Usage

### Custom Middleware

You can create custom middleware that works with the JWT system:

```php
class CustomJwtMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Check JWT authentication first
        if (!auth('api')->check()) {
            return new Response('Custom: JWT required', 401);
        }
        
        // Add custom logic
        $user = auth('api')->user();
        if (!$user->isActive()) {
            return new Response('Account suspended', 403);
        }
        
        return $next($request);
    }
}
```

### Dynamic Guard Selection

```php
class DynamicAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Select guard based on request
        $guard = $request->header('X-Auth-Type') === 'mobile' ? 'mobile' : 'api';
        
        if (!auth($guard)->check()) {
            return new Response('Authentication required', 401);
        }
        
        return $next($request);
    }
}
```

### Conditional Middleware

```php
// Apply different middleware based on route
Route::get('/api/data', 'DataController@index')
     ->middleware(function ($request, $next) {
         $middlewares = ['jwt:api'];
         
         if ($request->route()->parameter('sensitive')) {
             $middlewares[] = 'role:admin';
         }
         
         return app(MiddlewareStack::class)
             ->add($middlewares)
             ->handle($request, $next);
     });
```

## 📋 Best Practices

### 1. Guard Selection

```php
// ✅ Good: Use specific guards for different contexts
Route::middleware('jwt:api')->prefix('api')->group($apiRoutes);
Route::middleware('auth:web')->prefix('admin')->group($webRoutes);

// ❌ Avoid: Mixing incompatible guards
Route::middleware('auth:web,jwt')->get('/mixed', $handler); // Confusing
```

### 2. Middleware Ordering

```php
// ✅ Good: Authentication first, then authorization
Route::middleware(['jwt:api', 'role:admin', 'permission:manage-users'])
     ->get('/admin/users', $handler);

// ❌ Avoid: Authorization before authentication
Route::middleware(['role:admin', 'jwt:api']) // Wrong order
     ->get('/admin/users', $handler);
```

### 3. Error Handling

```php
// ✅ Good: Let middleware handle auth errors
Route::middleware('jwt:api')->get('/api/data', function () {
    return auth('api')->user(); // Middleware ensures user exists
});

// ❌ Avoid: Manual auth checking after middleware
Route::middleware('jwt:api')->get('/api/data', function () {
    if (!auth('api')->check()) { // Redundant
        return response('Unauthorized', 401);
    }
    return auth('api')->user();
});
```

### 4. Performance

```php
// ✅ Good: Use specific JWT middleware for APIs
Route::prefix('api')->middleware('jwt:api')->group($routes);

// ⚠️ Less optimal: Generic auth middleware for APIs
Route::prefix('api')->middleware('auth:api')->group($routes);
```

### 5. Testing

```php
// ✅ Good: Test middleware in isolation
public function testRequiresAuthentication()
{
    $middleware = new JwtMiddleware('api');
    $request = Request::create('/api/test');
    
    $response = $middleware->handle($request, function () {
        return new Response('Success');
    });
    
    $this->assertEquals(401, $response->getStatusCode());
}
```

## 🎉 Summary

The TreeHouse JWT middleware system provides:

- **Complete Authentication**: Multiple guards, stateless JWT, session support
- **Fine-grained Authorization**: Roles, permissions, custom logic
- **Developer-Friendly**: Fluent API, comprehensive testing, clear documentation
- **Production-Ready**: Error handling, CORS support, performance optimized
- **Extensible**: Easy to customize and extend for specific needs

The system seamlessly integrates with TreeHouse's existing authentication framework while providing modern JWT capabilities for APIs and SPAs.