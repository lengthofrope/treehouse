# TreeHouse Authentication

The TreeHouse Authentication system provides a flexible and secure way to authenticate users in your application. It supports multiple authentication guards, user providers, and includes features like "remember me" functionality and session management.

## Features

- **Multiple Guards**: Support for different authentication mechanisms
- **Session-based Authentication**: Secure session management with CSRF protection
- **Remember Me**: Persistent authentication using secure cookies
- **User Providers**: Flexible user data retrieval from various sources
- **Password Security**: Secure password hashing and verification
- **Session Regeneration**: Automatic session ID regeneration for security

## Components

### Guard Interface

The `Guard` interface defines the contract for authentication guards:

```php
use LengthOfRope\TreeHouse\Auth\Guard;

interface Guard
{
    public function check(): bool;
    public function guest(): bool;
    public function user(): mixed;
    public function attempt(array $credentials = [], bool $remember = false): bool;
    public function login(mixed $user, bool $remember = false): void;
    public function logout(): void;
    // ... more methods
}
```

### SessionGuard

The `SessionGuard` implements session-based authentication:

```php
use LengthOfRope\TreeHouse\Auth\SessionGuard;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Security\Hash;

$guard = new SessionGuard($session, $cookie, $userProvider, $hash);

// Check if user is authenticated
if ($guard->check()) {
    $user = $guard->user();
}

// Attempt login
if ($guard->attempt(['email' => 'user@example.com', 'password' => 'password'])) {
    // Login successful
}

// Logout
$guard->logout();
```

### UserProvider Interface

The `UserProvider` interface defines how users are retrieved and validated:

```php
use LengthOfRope\TreeHouse\Auth\UserProvider;

interface UserProvider
{
    public function retrieveById(mixed $identifier): mixed;
    public function retrieveByCredentials(array $credentials): mixed;
    public function validateCredentials(mixed $user, array $credentials): bool;
    public function updateRememberToken(mixed $user, string $token): void;
}
```

### DatabaseUserProvider

The `DatabaseUserProvider` implements database-backed user authentication:

```php
use LengthOfRope\TreeHouse\Auth\DatabaseUserProvider;
use LengthOfRope\TreeHouse\Security\Hash;

$provider = new DatabaseUserProvider($hash, [
    'table' => 'users',
    'model' => null, // Optional custom user model
    'connection' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'myapp',
        'username' => 'user',
        'password' => 'password'
    ]
]);
```

### AuthManager

The `AuthManager` provides a unified interface for managing authentication:

```php
use LengthOfRope\TreeHouse\Auth\AuthManager;

$config = [
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

$authManager = new AuthManager($config, $session, $cookie, $hash);

// Use default guard
if ($authManager->check()) {
    $user = $authManager->user();
}

// Use specific guard
$guard = $authManager->guard('web');
```

### GenericUser

The `GenericUser` class provides a simple user implementation:

```php
use LengthOfRope\TreeHouse\Auth\GenericUser;

$user = new GenericUser([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'hashed_password'
]);

$id = $user->getAuthIdentifier(); // 1
$password = $user->getAuthPassword(); // 'hashed_password'
$name = $user->name; // 'John Doe' (magic getter)
```

## Helper Functions

TreeHouse provides convenient global helper functions for common authentication operations:

### Available Helpers

```php
// Get the auth manager or a specific guard
$authManager = auth();           // Get default AuthManager
$guard = auth('web');            // Get specific guard

// Check authentication status
$isLoggedIn = check();           // Check if authenticated
$isGuest = guest();              // Check if guest (not authenticated)

// Get current user
$user = user();                  // Get current authenticated user
$user = user('api');             // Get user from specific guard

// Authentication operations
$success = attempt([             // Attempt login
    'email' => 'user@example.com',
    'password' => 'secret'
], $remember = false);

login($user, $remember = false); // Log in a user instance
logout();                       // Log out current user
```

### Helper Usage Examples

```php
// Simple authentication check
if (check()) {
    echo "Welcome back, " . user()->name;
} else {
    echo "Please log in";
}

// Login form processing
if ($_POST['login']) {
    if (attempt($_POST['credentials'])) {
        header('Location: /dashboard');
    } else {
        $error = 'Invalid credentials';
    }
}

// Conditional content
if (guest()) {
    // Show login form
    include 'login-form.php';
} else {
    // Show user content
    include 'user-dashboard.php';
}

// Multiple guards
$webUser = user('web');
$apiUser = user('api');

// Manual login
$user = User::find(1);
login($user, true); // Login with remember me
```

## Usage Examples

### Basic Authentication

```php
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Security\Hash;

// Setup
$session = new Session();
$cookie = new Cookie('app', '');
$hash = new Hash();

$config = [
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
            'connection' => [
                'driver' => 'sqlite',
                'database' => 'app.db'
            ]
        ],
    ],
];

$auth = new AuthManager($config, $session, $cookie, $hash);

// Login attempt
if ($auth->attempt(['email' => $_POST['email'], 'password' => $_POST['password']], true)) {
    // Login successful with remember me
    header('Location: /dashboard');
} else {
    // Login failed
    $error = 'Invalid credentials';
}
```

### Checking Authentication

```php
// Check if user is authenticated
if ($auth->check()) {
    $user = $auth->user();
    echo "Welcome, " . $user->name;
} else {
    header('Location: /login');
}

// Check if user is guest
if ($auth->guest()) {
    // Show login form
}
```

### Logout

```php
// Simple logout
$auth->logout();

// Logout from all devices
if ($auth->logoutOtherDevices($_POST['password'])) {
    // Successfully logged out from other devices
}
```

### Custom User Model

```php
class User extends GenericUser
{
    public function getFullName(): string
    {
        return $this->getAttribute('first_name') . ' ' . $this->getAttribute('last_name');
    }
    
    public function isAdmin(): bool
    {
        return $this->getAttribute('role') === 'admin';
    }
}

// Use with DatabaseUserProvider
$provider = new DatabaseUserProvider($hash, [
    'table' => 'users',
    'model' => User::class
]);
```

## Security Features

### Password Hashing

The authentication system uses secure password hashing:

```php
$hash = new Hash();

// Hash a password
$hashed = $hash->make('password');

// Verify a password
if ($hash->check('password', $hashed)) {
    // Password is correct
}

// Check if rehashing is needed
if ($hash->needsRehash($hashed)) {
    $newHash = $hash->make('password');
    // Update user's password hash
}
```

### Session Security

- Automatic session regeneration on login
- CSRF token management
- Secure cookie settings
- Session timeout handling

### Remember Me Security

- Cryptographically secure tokens
- Token rotation on use
- Secure cookie attributes
- Database token storage

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
    ],
]
```

### Provider Configuration

```php
'providers' => [
    'users' => [
        'driver' => 'database',
        'table' => 'users',
        'model' => App\Models\User::class, // Optional
        'connection' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => 'myapp',
            'username' => 'user',
            'password' => 'password'
        ]
    ],
]
```

## Database Schema

The authentication system expects a users table with the following structure:

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Error Handling

The authentication system throws specific exceptions:

- `InvalidArgumentException`: For configuration errors
- `RuntimeException`: For runtime errors
- Authentication failures return `false` rather than throwing exceptions

## Testing

The authentication system includes comprehensive tests:

```bash
# Run authentication tests
./vendor/bin/phpunit tests/Unit/Auth/

# Run specific test
./vendor/bin/phpunit tests/Unit/Auth/AuthManagerTest.php
```

## Best Practices

1. **Always use HTTPS** in production for authentication
2. **Implement rate limiting** for login attempts
3. **Use strong passwords** and enforce password policies
4. **Regularly rotate remember tokens**
5. **Monitor authentication logs** for suspicious activity
6. **Keep sessions secure** with proper configuration
7. **Validate and sanitize** all user input

## Integration with Middleware

```php
use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;
    
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }
    
    public function handle($request, callable $next)
    {
        if (!$this->auth->check()) {
            // Redirect to login or return 401
            header('Location: /login');
            exit;
        }
        
        return $next($request);
    }
}
```

## Authorization System

TreeHouse includes a comprehensive role-based authorization system built on top of the authentication foundation.

### Role-Based Access Control (RBAC)

The authorization system provides:

- **Role Management** - Users have roles with specific permissions
- **Permission System** - Fine-grained permission checking
- **Gate Pattern** - Laravel-inspired authorization gates
- **Policy Support** - Resource-specific authorization logic
- **Middleware Protection** - Route-level authorization
- **Template Integration** - Authorization directives in views

### Quick Start

#### 1. Configure Roles and Permissions

Edit `config/auth.php` to define your application's roles and permissions:

```php
'roles' => [
    'admin' => ['*'], // All permissions
    'editor' => ['edit-posts', 'delete-posts', 'view-posts'],
    'viewer' => ['view-posts'],
],

'permissions' => [
    'manage-users' => ['admin'],
    'edit-posts' => ['admin', 'editor'],
    'view-posts' => ['admin', 'editor', 'viewer'],
],
```

#### 2. Update User Model

Your User model should implement the `Authorizable` interface:

```php
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;

class User extends ActiveRecord implements Authorizable
{
    use AuthorizableUser;
    
    protected array $fillable = ['name', 'email', 'password', 'role'];
}
```

#### 3. Run Migration

Add the role column to your users table:

```bash
./bin/th migrate:run
```

### Role Management

#### Checking Roles

```php
// In controllers
if ($user->hasRole('admin')) {
    // Admin functionality
}

if ($user->hasAnyRole(['admin', 'editor'])) {
    // Admin or editor functionality
}
```

#### Assigning Roles

```php
$user->assignRole('editor');
$user->removeRole('viewer');
```

### Permission System

#### Checking Permissions

```php
// In controllers
if ($user->can('manage-users')) {
    // User management code
}

if ($user->cannot('delete-posts')) {
    // Handle insufficient permissions
}

// Using Gate
if (Gate::allows('edit-post', $post)) {
    // Edit post logic
}
```

#### Custom Permissions

Define custom permission logic using Gate:

```php
Gate::define('edit-post', function($user, $post) {
    return $user->id === $post->author_id || $user->hasRole('admin');
});
```

### Middleware Protection

#### Route Protection

```php
// Protect routes with roles
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', 'AdminController@users');
});

// Protect with permissions
$router->get('/posts/create', 'PostController@create')
       ->middleware('permission:edit-posts');

// Multiple roles (OR logic)
$router->group(['middleware' => 'role:admin,editor'], function($router) {
    $router->get('/posts/manage', 'PostController@manage');
});
```

### Template Authorization

#### Authentication Directives

```html
<!-- Show content only to authenticated users -->
<div th:auth>
    Welcome back, {user.name}!
</div>

<!-- Show content only to guests -->
<div th:guest>
    Please <a href="/login">log in</a> to continue.
</div>
```

#### Role-Based Directives

```html
<!-- Single role -->
<div th:role="admin">
    <a href="/admin">Administrator Panel</a>
</div>

<!-- Multiple roles (OR logic) -->
<div th:role="admin,editor">
    <a href="/posts/manage">Manage Posts</a>
</div>
```

#### Permission-Based Directives

```html
<!-- Single permission -->
<div th:permission="manage-users">
    <button>Add User</button>
</div>

<!-- Multiple permissions (OR logic) -->
<div th:permission="edit-posts,delete-posts">
    <button>Manage Posts</button>
</div>
```

### Authorization Helper Functions

```php
// Check authentication
if (auth()->check()) {
    $user = auth()->user();
}

// Check permissions
if (can('manage-users')) {
    // User management logic
}

if (cannot('delete-posts')) {
    // Handle restriction
}
```

### Policy-Based Authorization

#### Creating Policies

```php
use LengthOfRope\TreeHouse\Auth\Policy;

class PostPolicy extends Policy
{
    public function view(Authorizable $user, Post $post): bool
    {
        return $user->can('view-posts') || $post->author_id === $user->id;
    }
    
    public function update(Authorizable $user, Post $post): bool
    {
        return $user->hasRole('admin') || $post->author_id === $user->id;
    }
}
```

#### Registering Policies

```php
Gate::policy(Post::class, PostPolicy::class);
```

#### Using Policies

```php
// In controllers
if (Gate::allows('update', $post)) {
    // Update post
}

if (Gate::denies('delete', $post)) {
    abort(403, 'Cannot delete this post');
}
```

This authentication and authorization system provides enterprise-grade security features while maintaining TreeHouse's lightweight philosophy.