# TreeHouse Authentication & Authorization

## Overview

The Auth layer provides comprehensive authentication and authorization capabilities including user authentication, session management, role-based access control (RBAC), permission checking, and policy-based authorization. This layer integrates seamlessly with the database and router layers to provide secure access control throughout the application.

## Features

- **Multi-Guard Authentication**: Support for multiple authentication guards (session, API, etc.)
- **User Providers**: Flexible user data retrieval from database or custom sources
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
            'driver' => 'token',
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
        'driver' => 'token',
        'provider' => 'users',
        'hash' => false,
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
    'admins' => [
        'driver' => 'database',
        'table' => 'admin_users',
        'model' => AdminUser::class,
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
// Route protection with auth middleware
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', 'DashboardController@index');
    $router->get('/profile', 'ProfileController@show');
});

// Role-based protection
$router->group(['middleware' => ['auth', 'role:admin']], function($router) {
    $router->get('/admin', 'AdminController@index');
});

// Permission-based protection
$router->get('/posts/create', 'PostController@create')
    ->middleware(['auth', 'permission:posts.create']);
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