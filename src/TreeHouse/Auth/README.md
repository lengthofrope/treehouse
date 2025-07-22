# TreeHouse Authentication & Authorization

## Overview

The Auth layer provides comprehensive authentication and authorization capabilities including user authentication, session management, role-based access control (RBAC), permission checking, and policy-based authorization. This layer integrates seamlessly with the database and router layers to provide secure access control throughout the application.

## Features

- **Multi-Guard Authentication**: Support for multiple authentication guards (session, JWT, API, etc.)
- **JWT Authentication**: Stateless JWT-based authentication with token management
- **User Providers**: Flexible user data retrieval from database, JWT, or custom sources
- **Session Management**: Secure session handling with remember tokens
- **Role-Based Access Control**: Hierarchical role system with permission inheritance
- **Permission System**: Granular permission checking and middleware protection
- **Policy Authorization**: Resource-based authorization policies
- **Helper Functions**: Convenient global functions for common auth operations
- **Middleware Integration**: Route-level authentication and authorization
- **Password Security**: Secure password hashing and validation

### Guard Interface

The [`Guard`](Guard.php:24) interface defines the contract for authentication guards:

```php
interface Guard
{
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function id(): mixed;
    public function validate(array $credentials = []): bool;
    public function attempt(array $credentials = [], bool $remember = false): bool;
    public function once(mixed $user): bool;
    public function login(mixed $user, bool $remember = false): void;
    public function logout(): void;
}
```

### SessionGuard

The [`SessionGuard`](SessionGuard.php:32) provides session-based authentication:

```php
// Basic authentication
$guard = new SessionGuard($provider, $session, $cookie, $hash);

// Check authentication status
if ($guard->check()) {
    $user = $guard->user();
    $userId = $guard->id();
}

// Authenticate user
$credentials = ['email' => 'user@example.com', 'password' => 'secret'];
if ($guard->attempt($credentials, $remember = true)) {
    // Authentication successful
}

// Manual login
$user = User::find(1);
$guard->login($user, $remember = true);

// Logout
$guard->logout();
```

### JwtGuard

The [`JwtGuard`](JwtGuard.php:35) provides stateless JWT-based authentication:

```php
// Basic JWT authentication
$jwtConfig = new JwtConfig([
    'secret' => 'your-secret-key',
    'algorithm' => 'HS256',
    'ttl' => 900, // 15 minutes
    'issuer' => 'your-app',
    'audience' => 'your-users',
]);

$jwtGuard = new JwtGuard($provider, $jwtConfig, $request);

// Check authentication status (from JWT token)
if ($jwtGuard->check()) {
    $user = $jwtGuard->user();
    $token = $jwtGuard->getToken();
    $claims = $jwtGuard->getClaims();
}

// Authenticate user and generate JWT
$credentials = ['email' => 'user@example.com', 'password' => 'secret'];
if ($jwtGuard->attempt($credentials)) {
    $token = $jwtGuard->getToken(); // Get generated JWT token
}

// Manual login with JWT generation
$user = User::find(1);
$jwtGuard->login($user);
$token = $jwtGuard->getToken();

// Generate token for user with custom claims
$token = $jwtGuard->generateTokenForUser($user, [
    'role' => 'admin',
    'permissions' => ['read', 'write']
]);

// Logout (clears current state)
$jwtGuard->logout();
```

#### JWT Token Extraction

The JwtGuard automatically extracts JWT tokens from multiple sources:

```php
// 1. Authorization header (preferred)
// Authorization: Bearer eyJ0eXAiOiJKV1Q...

// 2. Cookie
// Cookie: jwt_token=eyJ0eXAiOiJKV1Q...

// 3. Query parameter
// ?token=eyJ0eXAiOiJKV1Q...

// Configure token extraction sources
$jwtGuard->setTokenSources(['header', 'cookie', 'query']);
```

### JwtUserProvider

The [`JwtUserProvider`](JwtUserProvider.php:42) provides stateless user resolution from JWT tokens:

```php
// Stateless mode (user data from JWT claims only)
$provider = new JwtUserProvider($jwtConfig, $hash, [
    'mode' => 'stateless',
    'user_claim' => 'user_data',
    'required_user_fields' => ['id', 'email'],
]);

// Hybrid mode (JWT + database lookup)
$provider = new JwtUserProvider($jwtConfig, $hash, [
    'mode' => 'hybrid',
    'fallback_provider' => $databaseProvider,
]);

// User resolution from JWT
$user = $provider->retrieveByCredentials(['token' => $jwtToken]);

// Validate JWT token
$isValid = $provider->validateCredentials($user, ['token' => $jwtToken]);

// Create user from JWT claims
$claims = new ClaimsManager(['sub' => '123', 'email' => 'user@example.com']);
$user = $provider->createUserFromClaims($claims);
```

### UserProvider Interface

The [`UserProvider`](UserProvider.php:25) interface defines user data retrieval:

```php
interface UserProvider
{
    public function retrieveById(mixed $identifier): mixed;
    public function retrieveByToken(mixed $identifier, string $token): mixed;
    public function updateRememberToken(mixed $user, string $token): void;
    public function retrieveByCredentials(array $credentials): mixed;
    public function validateCredentials(mixed $user, array $credentials): bool;
}
```

### DatabaseUserProvider

The [`DatabaseUserProvider`](DatabaseUserProvider.php:31) retrieves users from database:

```php
// Configuration
$config = [
    'connection' => $dbConnection,
    'table' => 'users',
    'model' => User::class, // Optional custom model
];

$provider = new DatabaseUserProvider($hash, $config);

// Retrieve user by ID
$user = $provider->retrieveById(1);

// Retrieve by credentials
$user = $provider->retrieveByCredentials(['email' => 'user@example.com']);

// Validate credentials
$isValid = $provider->validateCredentials($user, ['password' => 'secret']);
```

### AuthManager

The [`AuthManager`](AuthManager.php:31) manages multiple guards and provides unified authentication interface:

```php
// Create auth manager
$authManager = new AuthManager($config, $session, $cookie, $hash);

// Use default guard
if ($authManager->check()) {
    $user = $authManager->user();
}

// Use specific guard
if ($authManager->guard('api')->check()) {
    $user = $authManager->guard('api')->user();
}

// Authentication attempts
$credentials = ['email' => 'user@example.com', 'password' => 'secret'];
if ($authManager->attempt($credentials)) {
    // Success
}

// Login user
$authManager->login($user, $remember = true);

// Logout
$authManager->logout();
```

### GenericUser

The [`GenericUser`](GenericUser.php:26) provides a basic user implementation:

```php
// Create generic user
$user = new GenericUser([
    'id' => 1,
    'email' => 'user@example.com',
    'name' => 'John Doe',
    'role' => 'admin'
]);

// Access attributes
echo $user->getAttribute('name');
echo $user->getAuthIdentifier(); // Returns ID
echo $user->getRole();

// Array/JSON conversion
$array = $user->toArray();
$json = $user->toJson();
```

## Helper Functions

The auth system provides convenient global helper functions for common operations.

### Available Helpers

```php
// Database connection
$db = db();

// Role checking
$hasRole = hasRole('admin');
$hasAnyRole = hasAnyRole(['admin', 'editor']);
$hasAllRoles = hasAllRoles(['admin', 'manager']);

// Permission checking
$hasPermission = hasPermission('posts.create');
$hasAnyPermission = hasAnyPermission(['posts.create', 'posts.edit']);
$hasAllPermissions = hasAllPermissions(['posts.create', 'posts.publish']);

// User retrieval
$user = getCurrentUser();

// User-specific checks
$userHasRole = userHasRole($user, 'admin');
$userHasPermission = userHasPermission($user, 'posts.create');

// Quick role checks
$isAdmin = isAdmin();
$isEditor = isEditor();
$isAuthor = isAuthor();
$isMember = isMember();

// Permission shortcuts
$canManageUsers = canManageUsers();
$canManageContent = canManageContent();
$canAccessAdmin = canAccessAdmin();

// Requirement functions (throw exceptions if not met)
requireRole('admin');
requirePermission('posts.create');
requireAnyRole(['admin', 'editor']);
requireAnyPermission(['posts.create', 'posts.edit']);
```

### Helper Usage Examples

```php
// In controllers
class PostController
{
    public function create()
    {
        requirePermission('posts.create');
        return view('posts.create');
    }
    
    public function store(Request $request)
    {
        if (!hasPermission('posts.create')) {
            abort(403, 'Insufficient permissions');
        }
        
        // Create post logic
    }
    
    public function edit($id)
    {
        requireAnyRole(['admin', 'editor']);
        $post = Post::find($id);
        return view('posts.edit', compact('post'));
    }
}

// In views/templates
if (isAdmin()) {
    echo '<a href="/admin">Admin Panel</a>';
}

if (canManageUsers()) {
    echo '<a href="/users">Manage Users</a>';
}

// In middleware or guards
if (!hasRole('admin') && !hasPermission('admin.access')) {
    return redirect('/unauthorized');
}
```

## Usage Examples

### Basic Authentication

```php
// Setup authentication manager
$config = [
    'default' => 'web',
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
            'required_user_fields' => ['id', 'email'],
        ],
    ],
    'jwt' => [
        'secret' => env('JWT_SECRET', 'your-secret-key'),
        'algorithm' => 'HS256',
        'ttl' => 900, // 15 minutes
        'refresh_ttl' => 1209600, // 2 weeks
        'issuer' => 'your-app',
        'audience' => 'your-users',
        'blacklist_enabled' => true,
        'blacklist_grace_period' => 300,
        'leeway' => 0,
        'required_claims' => ['iss', 'aud', 'sub', 'exp'],
    ],
];

$authManager = new AuthManager($config, $session, $cookie, $hash);

// Login form processing
if ($request->method() === 'POST') {
    $credentials = [
        'email' => $request->input('email'),
        'password' => $request->input('password'),
    ];
    
    if ($authManager->attempt($credentials, $request->has('remember'))) {
        return redirect('/dashboard');
    } else {
        return back()->withErrors(['Invalid credentials']);
    }
}
```

### JWT Authentication Examples

```php
// JWT API Authentication
if ($authManager->guard('api')->check()) {
    $user = $authManager->guard('api')->user();
    $token = $authManager->guard('api')->getToken(); // Current JWT token
    $claims = $authManager->guard('api')->getClaims(); // JWT claims
}

// JWT Login (generates new token)
$credentials = ['email' => 'user@example.com', 'password' => 'secret'];
if ($authManager->attempt($credentials, false, 'api')) {
    $token = $authManager->guard('api')->getToken();
    
    // Return token to client
    return response()->json([
        'token' => $token,
        'user' => $authManager->guard('api')->user(),
        'expires_in' => 900 // TTL in seconds
    ]);
}

// Generate JWT token for existing user
$user = User::find(1);
$token = $authManager->guard('api')->generateTokenForUser($user, [
    'role' => $user->getRole(),
    'permissions' => $user->getPermissions()
]);

// Validate JWT token only
$isValid = $authManager->guard('api')->validate(['token' => $providedToken]);

// JWT Logout (clears current state)
$authManager->logout('api');
```

### Stateless JWT Usage

```php
// Pure stateless JWT (no database lookup)
$config['providers']['jwt_stateless'] = [
    'driver' => 'jwt',
    'mode' => 'stateless',
    'user_claim' => 'user_data',
    'embed_user_data' => true,
];

// Generate token with embedded user data
$user = User::find(1);
$token = $authManager->guard('mobile')->generateTokenForUser($user, [
    'user_data' => [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role,
    ]
]);

// User data will be available from JWT claims without database lookup
```

### Hybrid JWT Usage

```php
// Hybrid mode (JWT + database lookup for additional data)
$config['providers']['jwt_hybrid'] = [
    'driver' => 'jwt',
    'mode' => 'hybrid',
    'fallback_provider' => 'users', // Database provider for additional data
];

// JWT contains user ID, additional data from database
$guard = $authManager->guard('api');
if ($guard->check()) {
    $user = $guard->user(); // Full user object from database
    $jwtClaims = $guard->getClaims(); // JWT-specific claims
}
```

### Checking Authentication

```php
// Check if user is authenticated
if ($authManager->check()) {
    $user = $authManager->user();
    echo "Welcome, " . $user->getAttribute('name');
} else {
    echo "Please log in";
}

// Check if user is guest
if ($authManager->guest()) {
    return redirect('/login');
}

// Get user ID
$userId = $authManager->id();
```

### Logout

```php
// Simple logout
$authManager->logout();

// Logout from all devices
$authManager->logoutOtherDevices($currentPassword);

// Logout with redirect
$authManager->logout();
return redirect('/login')->with('message', 'Logged out successfully');
```

### Custom User Model

```php
// Custom user model with RBAC
class User extends ActiveRecord implements Authorizable
{
    use AuthorizableUser;
    
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password', 'remember_token'];
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
    
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }
    
    public function can(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', function($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }
}
```

## Security Features

### Password Hashing

```php
// Secure password hashing
$hash = new Hash();
$hashedPassword = $hash->make('plain-password');

// Verify password
$isValid = $hash->check('plain-password', $hashedPassword);

// Check if rehashing is needed
if ($hash->needsRehash($hashedPassword)) {
    $newHash = $hash->make('plain-password');
}
```

### Session Security

```php
// Session configuration
$sessionConfig = [
    'name' => 'treehouse_session',
    'lifetime' => 7200, // 2 hours
    'path' => '/',
    'domain' => '',
    'secure' => true, // HTTPS only
    'httponly' => true, // No JavaScript access
    'samesite' => 'Lax', // CSRF protection
];
```

### Remember Me Security

```php
// Remember token configuration
$rememberConfig = [
    'name' => 'remember_token',
    'lifetime' => 2628000, // 30 days
    'secure' => true,
    'httponly' => true,
];
```

## Configuration

### Guard Configuration

```php
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
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],
```

### Provider Configuration

```php
'providers' => [
    'users' => [
        'driver' => 'database',
        'table' => 'users',
        'connection' => $dbConnection,
    ],
    'jwt_users' => [
        'driver' => 'jwt',
        'mode' => 'stateless', // or 'hybrid'
        'user_claim' => 'user_data',
        'embed_user_data' => true,
        'required_user_fields' => ['id', 'email'],
        'fallback_provider' => 'users', // for hybrid mode
    ],
    'admins' => [
        'driver' => 'database',
        'table' => 'admin_users',
        'model' => AdminUser::class,
    ],
],
```

### JWT Configuration

```php
'jwt' => [
    'secret' => env('JWT_SECRET', 'your-secret-key'),
    'algorithm' => 'HS256', // HS256, HS384, HS512, RS256, RS384, RS512, ES256, ES384, ES512
    'ttl' => 900, // Access token TTL in seconds (15 minutes)
    'refresh_ttl' => 1209600, // Refresh token TTL in seconds (2 weeks)
    'issuer' => env('APP_NAME', 'TreeHouse'),
    'audience' => env('APP_URL', 'http://localhost'),
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 300, // Grace period in seconds
    'leeway' => 0, // Clock skew tolerance in seconds
    'required_claims' => [
        'iss', // Issuer
        'aud', // Audience
        'sub', // Subject (user ID)
        'exp', // Expiration time
    ],
],
```

## Database Schema

```sql
-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Password resets table
CREATE TABLE password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email)
);
```

## Error Handling

```php
// Authentication exceptions
try {
    $authManager->attempt($credentials);
} catch (AuthenticationException $e) {
    return response('Unauthorized', 401);
}

// Authorization exceptions
try {
    requireRole('admin');
} catch (AuthorizationException $e) {
    return response('Forbidden', 403);
}
```

## Testing

```php
// Test authentication
$this->assertTrue($authManager->check());
$this->assertEquals($user->id, $authManager->id());

// Test role/permission checking
$this->assertTrue($user->hasRole('admin'));
$this->assertTrue($user->can('posts.create'));
```

## Best Practices

1. **Always hash passwords** using the Hash class
2. **Use HTTPS** for authentication in production
3. **Implement CSRF protection** for forms
4. **Use remember tokens** securely with proper expiration
5. **Validate user input** before authentication attempts
6. **Log authentication events** for security monitoring
7. **Use middleware** for route protection
8. **Implement rate limiting** for login attempts
9. **Use secure session configuration** in production
10. **Regularly rotate remember tokens** for security

## Integration with Middleware

```php
// Session-based route protection
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', 'DashboardController@index');
    $router->get('/profile', 'ProfileController@show');
});

// JWT-based API route protection
$router->group(['middleware' => 'auth:api'], function($router) {
    $router->get('/api/user', 'Api\UserController@show');
    $router->post('/api/posts', 'Api\PostController@store');
});

// Multiple guard protection
$router->group(['middleware' => 'auth:web,api'], function($router) {
    $router->get('/hybrid', 'HybridController@index');
});

// Role-based protection (works with both session and JWT)
$router->group(['middleware' => ['auth:api', 'role:admin']], function($router) {
    $router->get('/api/admin', 'Api\AdminController@index');
});

// Permission-based protection (works with both session and JWT)
$router->get('/api/posts/create', 'Api\PostController@create')
    ->middleware(['auth:api', 'permission:posts.create']);

// JWT-specific middleware for mobile app
$router->group([
    'prefix' => 'mobile',
    'middleware' => 'auth:mobile'
], function($router) {
    $router->get('/dashboard', 'Mobile\DashboardController@index');
    $router->post('/upload', 'Mobile\UploadController@store');
});
```

### JWT Token Header Examples

```php
// Client-side usage (JavaScript)
// Set Authorization header for API requests
fetch('/api/user', {
    headers: {
        'Authorization': 'Bearer ' + jwtToken,
        'Accept': 'application/json',
    }
});

// Alternative: Use cookie for browser-based apps
document.cookie = `jwt_token=${jwtToken}; path=/; httpOnly; secure`;

// Alternative: Use query parameter (less secure, for special cases)
fetch(`/api/user?token=${jwtToken}`);
```

## Authorization System

The TreeHouse framework includes a comprehensive Role-Based Access Control (RBAC) system that integrates seamlessly with the authentication layer.

### Role-Based Access Control (RBAC)

The RBAC system provides hierarchical role management with granular permissions:

#### 1. Configure Roles and Permissions

```php
// config/permissions.php
return [
    'roles' => [
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Full system access',
            'permissions' => ['*'] // All permissions
        ],
        'editor' => [
            'name' => 'Editor',
            'description' => 'Content management access',
            'permissions' => [
                'posts.create', 'posts.edit', 'posts.delete',
                'categories.manage', 'media.upload'
            ]
        ],
        'author' => [
            'name' => 'Author',
            'description' => 'Content creation access',
            'permissions' => ['posts.create', 'posts.edit', 'media.upload']
        ],
        'member' => [
            'name' => 'Member',
            'description' => 'Basic user access',
            'permissions' => ['profile.edit', 'posts.view']
        ]
    ],
    
    'permissions' => [
        'posts.create' => 'Create new posts',
        'posts.edit' => 'Edit existing posts',
        'posts.delete' => 'Delete posts',
        'posts.publish' => 'Publish posts',
        'users.manage' => 'Manage user accounts',
        'admin.access' => 'Access admin panel',
        // ... more permissions
    ]
];
```

#### 2. Update User Model

```php
class User extends ActiveRecord implements Authorizable
{
    use AuthorizableUser;
    
    // Relationships
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
    
    // Role checking
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }
    
    // Permission checking
    public function can(string $permission): bool
    {
        // Check if user has permission through roles
        return $this->roles()
            ->whereHas('permissions', function($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }
}
```

#### 3. Run Migration

```bash
php bin/treehouse migrate
```

#### Checking Roles

```php
// Check single role
if ($user->hasRole('admin')) {
    // User is admin
}

// Check multiple roles (OR)
if ($user->hasAnyRole(['admin', 'editor'])) {
    // User is admin OR editor
}

// Check multiple roles (AND)
if ($user->hasAllRoles(['admin', 'manager'])) {
    // User has both admin AND manager roles
}

// Using helper functions
if (hasRole('admin')) {
    // Current user is admin
}
```

#### Assigning Roles

```php
// Assign role to user
$user->assignRole('editor');

// Remove role from user
$user->removeRole('editor');

// Get user roles
$roles = $user->getRoles(); // Returns array of role slugs
$role = $user->getRole(); // Returns primary role
```

#### Checking Permissions

```php
// Check single permission
if ($user->can('posts.create')) {
    // User can create posts
}

// Check multiple permissions (OR)
if ($user->hasAnyPermission(['posts.create', 'posts.edit'])) {
    // User can create OR edit posts
}

// Check multiple permissions (AND)
if ($user->hasAllPermissions(['posts.create', 'posts.publish'])) {
    // User can both create AND publish posts
}

// Using helper functions
if (hasPermission('posts.create')) {
    // Current user can create posts
}
```

#### Custom Permissions

```php
// Define custom permission logic
class PostPolicy extends Policy
{
    public function update(Authorizable $user, Post $post): bool
    {
        // Users can edit their own posts or if they have edit permission
        return $this->isOwner($user, $post) || $user->can('posts.edit');
    }
    
    public function delete(Authorizable $user, Post $post): bool
    {
        // Only admins or post owners can delete
        return $user->hasRole('admin') || $this->isOwner($user, $post);
    }
}
```

#### Route Protection

```php
// Protect routes with roles
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin', 'AdminController@index');
    $router->get('/users', 'UserController@index');
});

// Protect routes with permissions
$router->get('/posts/create', 'PostController@create')
    ->middleware('permission:posts.create');

// Multiple role/permission options
$router->get('/dashboard', 'DashboardController@index')
    ->middleware('role:admin,editor,author');

$router->get('/moderate', 'ModerationController@index')
    ->middleware('permission:posts.moderate,comments.moderate');
```

#### Authentication Directives

```php
// In templates/views
<?php if (auth()->check()): ?>
    <p>Welcome, <?= auth()->user()->name ?></p>
    <a href="/logout">Logout</a>
<?php else: ?>
    <a href="/login">Login</a>
<?php endif; ?>
```

#### Role-Based Directives

```php
// Role-based content
<?php if (hasRole('admin')): ?>
    <a href="/admin">Admin Panel</a>
<?php endif; ?>

<?php if (hasAnyRole(['admin', 'editor'])): ?>
    <a href="/posts/create">Create Post</a>
<?php endif; ?>
```

#### Permission-Based Directives

```php
// Permission-based content
<?php if (hasPermission('posts.create')): ?>
    <a href="/posts/create">New Post</a>
<?php endif; ?>

<?php if (hasPermission('users.manage')): ?>
    <a href="/users">Manage Users</a>
<?php endif; ?>

// Complex permission checks
<?php if (hasAllPermissions(['posts.create', 'posts.publish'])): ?>
    <a href="/posts/create?publish=1">Create & Publish</a>
<?php endif; ?>
```

### Authorization Helper Functions

```php
// Permission checking helpers
function canCreatePosts(): bool {
    return hasPermission('posts.create');
}

function canManageUsers(): bool {
    return hasAnyPermission(['users.create', 'users.edit', 'users.delete']);
}

function canAccessAdmin(): bool {
    return hasRole('admin') || hasPermission('admin.access');
}

// Role hierarchy helpers
function isAdminOrHigher(): bool {
    return hasRole('admin');
}

function isEditorOrHigher(): bool {
    return hasAnyRole(['admin', 'editor']);
}
```

#### Creating Policies

```php
class UserPolicy extends Policy
{
    public function viewAny(Authorizable $user): bool
    {
        return $user->can('users.view');
    }
    
    public function view(Authorizable $user, User $model): bool
    {
        return $user->can('users.view') || $user->id === $model->id;
    }
    
    public function create(Authorizable $user): bool
    {
        return $user->can('users.create');
    }
    
    public function update(Authorizable $user, User $model): bool
    {
        return $user->can('users.edit') || $user->id === $model->id;
    }
    
    public function delete(Authorizable $user, User $model): bool
    {
        return $user->can('users.delete') && $user->id !== $model->id;
    }
}
```

#### Registering Policies

```php
// Register policies with Gate
Gate::policy(User::class, UserPolicy::class);
Gate::policy(Post::class, PostPolicy::class);

// Use policies in controllers
class UserController
{
    public function index()
    {
        if (Gate::denies('viewAny', User::class)) {
            abort(403);
        }
        
        return view('users.index');
    }
    
    public function edit(User $user)
    {
        if (Gate::denies('update', $user)) {
            abort(403);
        }
        
        return view('users.edit', compact('user'));
    }
}
```

#### Using Policies

```php
// In controllers
if (Gate::allows('update', $post)) {
    // User can update the post
}

if (Gate::denies('delete', $user)) {
    abort(403, 'Unauthorized action');
}

// Check multiple abilities
if (Gate::any(['update', 'delete'], $post)) {
    // User can either update or delete
}

// For specific user
if (Gate::forUser($otherUser, 'view', $post)) {
    // Other user can view the post
}
```

## Integration with Other Layers

The Auth layer integrates with all other framework layers:

- **Foundation Layer**: Automatic service registration and dependency injection
- **Database Layer**: User data storage and RBAC model relationships
- **Router Layer**: Authentication and authorization middleware
- **Http Layer**: Session and cookie management
- **Console Layer**: User management commands and RBAC setup
- **View Layer**: Authentication directives and user context