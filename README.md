# TreeHouse PHP Framework

A modern, lightweight PHP framework built from scratch with zero external dependencies, featuring a comprehensive layered architecture.

## WORK IN PROGRESS

Please note that this framework is in WIP state. It is nowhere near production ready.

## Architecture Overview

TreeHouse Framework is built with a clean layered architecture consisting of 11 core layers:

1. **[Foundation Layer](src/TreeHouse/Foundation/README.md)** - Application bootstrap, dependency injection container, and service registration
2. **[Database Layer](src/TreeHouse/Database/README.md)** - ActiveRecord ORM, QueryBuilder, connections, migrations, and relationships
3. **[Router Layer](src/TreeHouse/Router/README.md)** - HTTP routing, middleware, route groups, parameter binding, and named routes
4. **[Auth Layer](src/TreeHouse/Auth/README.md)** - Authentication, authorization, RBAC system, guards, providers, and policies
5. **[Console Layer](src/TreeHouse/Console/README.md)** - CLI application, command system, user management, and cache operations
6. **[Cache Layer](src/TreeHouse/Cache/README.md)** - File-based caching, prefixed caching, pattern matching, and performance optimization
7. **[Http Layer](src/TreeHouse/Http/README.md)** - Request/response handling, session management, cookies, file uploads, and security
8. **[Security Layer](src/TreeHouse/Security/README.md)** - CSRF protection, AES-256-CBC encryption, password hashing, and input sanitization
9. **[Support Layer](src/TreeHouse/Support/README.md)** - Array utilities, Collection class, String utilities, Carbon dates, Environment, and UUIDs
10. **[Validation Layer](src/TreeHouse/Validation/README.md)** - Comprehensive input validation with 25+ built-in rules and custom rule support
11. **[View Layer](src/TreeHouse/View/README.md)** - Template engine with HTML-valid syntax, components, layouts, and compilation

## Features

### Core Framework
- **Zero Dependencies**: Pure PHP implementation with no external libraries
- **Modern PHP 8.4+**: Built for the latest PHP features and type declarations
- **Layered Architecture**: Clean separation of concerns across 11 specialized layers
- **Dependency Injection**: Advanced container with automatic resolution and service registration
- **Configuration Management**: Environment-based configuration with type conversion

### HTTP & Routing
- **Flexible Routing**: HTTP routing with middleware support, parameter binding, and route groups
- **Named Routes**: URL generation and route caching for performance
- **Middleware System**: Request/response processing with built-in and custom middleware
- **Request Handling**: Comprehensive HTTP request parsing with file upload support
- **Response Management**: Flexible response building with headers, cookies, and redirects

### Authentication & Authorization
- **Multi-Guard Authentication**: Session-based and custom authentication guards
- **Role-Based Access Control (RBAC)**: Complete permission system with roles, permissions, and policies
- **User Providers**: Database and custom user providers with flexible user management
- **Authorization Middleware**: Route-level protection with role and permission checking
- **Template Integration**: Authentication and authorization directives in templates

### Database & ORM
- **ActiveRecord ORM**: Eloquent-style models with relationships and query building
- **Query Builder**: Fluent SQL query construction with method chaining
- **Database Migrations**: Schema management with version control
- **Relationships**: HasOne, HasMany, BelongsTo, and BelongsToMany relationships
- **Connection Management**: Multiple database connections with automatic management

### Template Engine
- **HTML-Valid Syntax**: ThymeLeaf-inspired template engine with `th:` attributes
- **Component System**: Reusable template components with parameter passing
- **Layout Inheritance**: Master layouts with sections and content injection
- **Template Compilation**: Optimized template compilation with caching
- **Authorization Integration**: Built-in auth, role, and permission directives

### Security & Validation
- **CSRF Protection**: Token-based CSRF protection with automatic validation
- **AES-256-CBC Encryption**: Secure encryption/decryption with payload integrity
- **Password Hashing**: Secure password hashing with configurable algorithms
- **Input Sanitization**: XSS protection and data sanitization utilities
- **Comprehensive Validation**: 25+ built-in validation rules with custom rule support

### Utilities & Support
- **Array Utilities**: Dot notation support, fluent operations, and data manipulation
- **Collection Class**: Extensive data manipulation with 50+ methods
- **String Utilities**: Case conversion, validation, manipulation, and formatting
- **Date/Time Handling**: Carbon integration with fluent date/time API
- **UUID Generation**: Support for UUID v1, v3, v4, v5 with validation
- **Environment Management**: Type-safe environment variable handling

### Caching & Performance
- **File-Based Caching**: High-performance file caching with automatic cleanup
- **Prefixed Caching**: Namespace support for cache organization
- **Pattern Matching**: Wildcard-based cache invalidation
- **Cache Statistics**: Performance monitoring and cache analytics

### Console & CLI
- **CLI Application**: Comprehensive command-line interface for development
- **User Management**: Create, update, delete users with role assignment
- **Cache Operations**: Cache clearing, statistics, and management
- **Database Operations**: Migration running and database management
- **Development Server**: Built-in development server with hot reloading

## Installation

### Create a New Project

```bash
composer create-project lengthofrope/treehouse my-app
cd my-app
composer install
./bin/th serve
```

### Or Install via Composer

```bash
composer require lengthofrope/treehouse
```

### Using the CLI Tool

Create a new project:

```bash
# Install TreeHouse globally
composer global require lengthofrope/treehouse

# Create new project
./bin/treehouse new my-app
cd my-app
composer install
./bin/treehouse serve
```

## Quick Start

### 1. Create a Controller

```php
<?php

namespace App\Controllers;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

class HomeController
{
    public function index(): string
    {
        return view('home', [
            'title' => 'Welcome to TreeHouse',
            'message' => 'Your application is running!',
            'posts' => Post::query()->published()->latest()->get()
        ]);
    }
    
    public function dashboard(): string
    {
        // Authorization check
        if (!auth()->user()->can('access-dashboard')) {
            abort(403, 'Access denied');
        }
        
        return view('dashboard', [
            'user' => auth()->user(),
            'stats' => $this->getDashboardStats()
        ]);
    }
}
```

### 2. Define Routes with Authorization

In `config/routes/web.php`:

```php
<?php

use App\Controllers\HomeController;
use App\Controllers\AdminController;
use App\Controllers\PostController;

// Public routes
$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/posts/{id}', [PostController::class, 'show'])
       ->where('id', '\d+')
       ->name('posts.show');

// Authentication required
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    $router->get('/profile', [UserController::class, 'profile'])->name('profile');
});

// Role-based authorization
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    $router->post('/admin/users', [AdminController::class, 'createUser']);
});

// Permission-based authorization
$router->get('/posts/create', [PostController::class, 'create'])
       ->middleware('permission:create-posts')
       ->name('posts.create');

// Multiple permissions (OR logic)
$router->group(['middleware' => 'permission:edit-posts,manage-content'], function($router) {
    $router->get('/posts/{id}/edit', [PostController::class, 'edit']);
    $router->put('/posts/{id}', [PostController::class, 'update']);
});
```

### 3. Create Models with Relationships

```php
<?php

namespace App\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;

class User extends ActiveRecord implements Authorizable
{
    use AuthorizableUser;
    
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password', 'remember_token'];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
}

class Post extends ActiveRecord
{
    protected array $fillable = ['title', 'content', 'published', 'user_id'];
    protected array $casts = ['published' => 'boolean'];
    
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }
    
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
}
```

### 4. Advanced Database Usage

```php
// Complex queries with relationships
$posts = Post::query()
    ->with(['author', 'tags'])
    ->where('published', true)
    ->whereHas('author', function($query) {
        $query->where('active', true);
    })
    ->orderBy('created_at', 'desc')
    ->paginate(10);

// Raw queries when needed
$stats = db()->query()
    ->select('COUNT(*) as total', 'AVG(rating) as avg_rating')
    ->from('posts')
    ->where('published', true)
    ->first();

// Transactions
db()->transaction(function() {
    $user = User::create($userData);
    $profile = $user->profile()->create($profileData);
    $user->assignRole('author');
});
```

## Directory Structure

```
my-app/
├── src/
│   ├── App/                    # Application code
│   │   ├── Controllers/        # HTTP controllers
│   │   ├── Models/            # Database models
│   │   ├── Services/          # Business logic
│   │   ├── Middleware/        # HTTP middleware
│   │   └── Policies/          # Authorization policies
│   └── TreeHouse/             # Framework core (11 layers)
│       ├── Foundation/        # Application bootstrap & DI
│       ├── Database/          # ORM, QueryBuilder, Migrations
│       ├── Router/            # HTTP routing & middleware
│       ├── Auth/              # Authentication & RBAC
│       ├── Console/           # CLI commands & application
│       ├── Cache/             # Caching system
│       ├── Http/              # Request/response handling
│       ├── Security/          # CSRF, encryption, hashing
│       ├── Support/           # Utilities & helper classes
│       ├── Validation/        # Input validation system
│       └── View/              # Template engine
├── config/                    # Configuration files
│   ├── app.php               # Application settings
│   ├── database.php          # Database connections
│   ├── cache.php             # Cache configuration
│   └── routes/               # Route definitions
├── public/                   # Web root
├── resources/views/          # Templates
├── storage/                  # Cache, logs, compiled views
├── tests/                    # Test files
├── database/migrations/      # Database migrations
└── bin/th                   # CLI command
```

## CLI Commands

TreeHouse includes a comprehensive CLI tool with the following commands:

### Development
```bash
# Development server
./bin/th serve --port=8000 --host=localhost

# Create new project
./bin/treehouse new my-app
```

### Cache Management
```bash
# Clear all cache
./bin/th cache:clear

# Clear specific cache patterns
./bin/th cache:clear --pattern="user:*"

# Show cache statistics
./bin/th cache:stats

# Show detailed cache information
./bin/th cache:info
```

### User Management
```bash
# Create new user
./bin/th user:create --name="John Doe" --email="john@example.com" --password="secret"

# Create user with role
./bin/th user:create --name="Admin User" --email="admin@example.com" --role="admin"

# Update user
./bin/th user:update --email="john@example.com" --name="John Smith"

# Delete user
./bin/th user:delete --email="john@example.com"

# List all users
./bin/th user:list
```

### Database
```bash
# Run migrations
./bin/th migrate:run

# Create migration
./bin/th migrate:create create_posts_table
```

### Testing
```bash
# Run tests
./bin/th test:run

# Run specific test suite
./bin/th test:run --suite=unit
```

## Template Engine

TreeHouse includes a powerful ThymeLeaf-inspired template engine with HTML-valid syntax:

**Layout Template (`layouts/app.th.html`):**
```html
<!DOCTYPE html>
<html>
<head>
    <title th:text="${title}">Default Title</title>
    <meta name="csrf-token" th:content="${csrf_token()}">
</head>
<body>
    <nav th:auth>
        <span>Welcome, <span th:text="${user.name}">User</span>!</span>
        
        <!-- Role-based navigation -->
        <a th:role="admin" href="/admin">Admin Panel</a>
        <a th:permission="manage-posts" href="/posts/manage">Manage Posts</a>
        
        <form action="/logout" method="POST">
            <button type="submit">Logout</button>
        </form>
    </nav>
    
    <nav th:guest>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
    </nav>
    
    <main th:fragment="content">
        <!-- Content will be inserted here -->
    </main>
</body>
</html>
```

**Page Template (`posts/index.th.html`):**
```html
<div th:extends="layouts/app" th:fragment="content">
    <h1 th:text="${title}">Posts</h1>
    
    <!-- Permission-based create button -->
    <a th:permission="create-posts" href="/posts/create" class="btn btn-primary">
        Create New Post
    </a>
    
    <!-- Loop through posts -->
    <div th:each="post : ${posts}" class="post-card">
        <article>
            <h2>
                <a th:href="@{/posts/{id}(id=${post.id})}" th:text="${post.title}">
                    Post Title
                </a>
            </h2>
            <p th:text="${post.excerpt}">Post excerpt</p>
            <small>
                By <span th:text="${post.author.name}">Author</span>
                on <span th:text="${post.created_at.format('M j, Y')}">Date</span>
            </small>
            
            <!-- Author or admin can edit -->
            <div th:if="${user.can('edit-post', post)}">
                <a th:href="@{/posts/{id}/edit(id=${post.id})}">Edit</a>
            </div>
        </article>
    </div>
    
    <!-- Pagination -->
    <div th:if="${posts.hasPages()}" class="pagination">
        <a th:if="${posts.previousPageUrl()}" 
           th:href="${posts.previousPageUrl()}">Previous</a>
        <span th:text="'Page ' + ${posts.currentPage()} + ' of ' + ${posts.lastPage()}">
            Page info
        </span>
        <a th:if="${posts.nextPageUrl()}" 
           th:href="${posts.nextPageUrl()}">Next</a>
    </div>
</div>
```

### Template Features:
- `th:text` - Set element text content with automatic escaping
- `th:if` / `th:unless` - Conditional rendering
- `th:each` - Loop over collections with automatic variable binding
- `th:auth` / `th:guest` - Authentication-based content
- `th:role` - Role-based content display
- `th:permission` - Permission-based content display
- `th:extends` - Layout inheritance
- `th:fragment` - Define and include reusable fragments
- `th:href` / `@{...}` - URL generation with parameters
- `${variable}` - Variable interpolation with Support class integration

## Authorization System

TreeHouse includes a comprehensive RBAC (Role-Based Access Control) system:

### Database Schema
```sql
-- Roles table
CREATE TABLE roles (
    id INTEGER PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Permissions table
CREATE TABLE permissions (
    id INTEGER PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- User roles (many-to-many)
CREATE TABLE user_roles (
    user_id INTEGER,
    role_id INTEGER,
    PRIMARY KEY (user_id, role_id)
);

-- Role permissions (many-to-many)
CREATE TABLE role_permissions (
    role_id INTEGER,
    permission_id INTEGER,
    PRIMARY KEY (role_id, permission_id)
);
```

### User Authorization Methods
```php
// Role checking
if ($user->hasRole('admin')) {
    // Admin functionality
}

if ($user->hasAnyRole(['admin', 'editor', 'moderator'])) {
    // Multiple role check (OR logic)
}

if ($user->hasAllRoles(['editor', 'verified'])) {
    // Multiple role check (AND logic)
}

// Permission checking
if ($user->can('manage-users')) {
    // User management functionality
}

if ($user->cannot('delete-posts')) {
    // Handle restriction
}

if ($user->canAny(['edit-posts', 'delete-posts'])) {
    // Multiple permission check (OR logic)
}

// Direct role/permission assignment
$user->assignRole('editor');
$user->removeRole('admin');
$user->syncRoles(['editor', 'author']);
```

### Route Protection
```php
// Role-based middleware
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

// Permission-based middleware
$router->get('/posts/create', [PostController::class, 'create'])
       ->middleware('permission:create-posts');

// Multiple roles/permissions (OR logic)
$router->group(['middleware' => 'role:admin,editor'], function($router) {
    $router->get('/content/manage', [ContentController::class, 'manage']);
});

// Multiple permissions (OR logic)
$router->group(['middleware' => 'permission:edit-posts,manage-content'], function($router) {
    $router->get('/posts/bulk-edit', [PostController::class, 'bulkEdit']);
});
```

### Gate System for Custom Authorization
```php
use LengthOfRope\TreeHouse\Auth\Gate;

// Define custom authorization logic
Gate::define('edit-post', function($user, $post) {
    return $user->id === $post->author_id || $user->hasRole('admin');
});

Gate::define('delete-comment', function($user, $comment) {
    return $user->id === $comment->user_id 
        || $user->can('moderate-comments')
        || $user->hasRole('admin');
});

// Use in controllers
if (Gate::allows('edit-post', $post)) {
    // Allow editing
} else {
    abort(403, 'Unauthorized');
}

// Use in templates
<button th:if="${gate.allows('edit-post', post)}">Edit Post</button>
```

## Validation System

Comprehensive input validation with 25+ built-in rules:

```php
use LengthOfRope\TreeHouse\Validation\Validator;

// Basic validation
$validator = new Validator($data, [
    'name' => 'required|string|min:2|max:50',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|string|min:8|confirmed',
    'age' => 'required|integer|min:18|max:120',
    'website' => 'nullable|url',
    'avatar' => 'nullable|file|image|max:2048', // 2MB max
]);

if ($validator->fails()) {
    return back()->withErrors($validator->errors())->withInput();
}

// Custom validation rules
$validator->addRule('phone', function($value) {
    return preg_match('/^\+?[1-9]\d{1,14}$/', $value);
}, 'The :attribute must be a valid phone number.');

// Available validation rules
$rules = [
    'required', 'nullable', 'string', 'integer', 'numeric', 'boolean',
    'email', 'url', 'ip', 'uuid', 'json', 'date', 'regex',
    'min', 'max', 'between', 'in', 'not_in', 'unique', 'exists',
    'confirmed', 'same', 'different', 'alpha', 'alpha_num',
    'file', 'image', 'mimes', 'dimensions'
];
```

## Security Features

### CSRF Protection
```php
// Automatic CSRF token generation
$token = csrf_token();

// Verify CSRF token
if (!csrf_verify($token)) {
    abort(419, 'CSRF token mismatch');
}

// In templates
<form method="POST" action="/posts">
    <input type="hidden" name="_token" th:value="${csrf_token()}">
    <!-- form fields -->
</form>
```

### Encryption
```php
use LengthOfRope\TreeHouse\Security\Encryption;

// Encrypt sensitive data
$encrypted = Encryption::encrypt('sensitive data');

// Decrypt data
$decrypted = Encryption::decrypt($encrypted);

// Encrypt with custom key
$encrypted = Encryption::encryptWithKey('data', 'custom-key');
```

### Password Hashing
```php
use LengthOfRope\TreeHouse\Security\Hash;

// Hash password
$hashed = Hash::make('password123');

// Verify password
if (Hash::check('password123', $hashed)) {
    // Password is correct
}

// Check if rehashing is needed
if (Hash::needsRehash($hashed)) {
    $newHash = Hash::make('password123');
}
```

## Testing

TreeHouse applications come with comprehensive testing support:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\DatabaseTestCase;
use App\Models\User;
use App\Models\Post;

class UserTest extends DatabaseTestCase
{
    public function test_user_creation()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);
        
        $this->assertEquals('Test User', $user->name);
        $this->assertTrue($user->exists);
    }
    
    public function test_user_roles()
    {
        $user = $this->createUser();
        $role = $this->createRole('editor');
        
        $user->assignRole('editor');
        
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('admin'));
    }
    
    public function test_user_permissions()
    {
        $user = $this->createUser();
        $role = $this->createRole('author');
        $permission = $this->createPermission('create-posts');
        
        $role->givePermissionTo('create-posts');
        $user->assignRole('author');
        
        $this->assertTrue($user->can('create-posts'));
        $this->assertFalse($user->can('delete-users'));
    }
}
```

Run tests:

```bash
./bin/th test:run
composer test
./bin/th test:run --filter=UserTest
```

## Performance & Optimization

### Caching
```php
// Cache data
cache()->put('user:' . $id, $user, 3600); // 1 hour

// Retrieve cached data
$user = cache()->get('user:' . $id, function() use ($id) {
    return User::find($id);
});

// Cache with tags
cache()->tags(['users', 'profiles'])->put('user:' . $id, $user);

// Invalidate tagged cache
cache()->tags(['users'])->flush();

// Pattern-based cache clearing
cache()->forget('user:*'); // Clear all user cache
```

### Query Optimization
```php
// Eager loading relationships
$posts = Post::with(['author', 'tags', 'comments.user'])->get();

// Lazy eager loading
$posts = Post::all();
$posts->load('author');

// Query scopes for reusable logic
class Post extends ActiveRecord
{
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
    
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

// Use scopes
$recentPosts = Post::published()->recent(30)->get();
```

## Configuration

Configuration files are stored in the `config/` directory:

- `app.php` - Application settings, timezone, locale
- `database.php` - Database connections and settings
- `cache.php` - Cache configuration and drivers
- `routes/web.php` - Web route definitions
- `routes/api.php` - API route definitions

### Environment Configuration
```php
// .env file
APP_NAME="TreeHouse App"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/app.db

CACHE_DRIVER=file
CACHE_PREFIX=treehouse_

SESSION_LIFETIME=120
SESSION_ENCRYPT=true

ENCRYPTION_KEY=base64:your-32-character-secret-key
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards
- Write comprehensive tests for new features
- Update documentation for any API changes
- Ensure backward compatibility when possible
- Add type declarations for all methods and properties

## License

The TreeHouse framework is open-sourced software licensed under the [MIT license](LICENSE).

## Author

**Bas de Kort**  
Email: bdekort@proton.me  
GitHub: [@lengthofrope](https://github.com/lengthofrope)

## Support

- Documentation: [https://treehouse-framework.dev](https://treehouse-framework.dev)
- Issues: [GitHub Issues](https://github.com/lengthofrope/treehouse/issues)
- Discussions: [GitHub Discussions](https://github.com/lengthofrope/treehouse/discussions)

## Layer Documentation

For detailed information about each framework layer, see the individual README files:

- [Foundation Layer](src/TreeHouse/Foundation/README.md) - Application bootstrap and dependency injection
- [Database Layer](src/TreeHouse/Database/README.md) - ORM, QueryBuilder, and database management
- [Router Layer](src/TreeHouse/Router/README.md) - HTTP routing and middleware system
- [Auth Layer](src/TreeHouse/Auth/README.md) - Authentication and authorization (RBAC)
- [Console Layer](src/TreeHouse/Console/README.md) - CLI commands and console application
- [Cache Layer](src/TreeHouse/Cache/README.md) - Caching system and performance optimization
- [Http Layer](src/TreeHouse/Http/README.md) - HTTP request/response handling
- [Security Layer](src/TreeHouse/Security/README.md) - Security utilities and protection
- [Support Layer](src/TreeHouse/Support/README.md) - Utility classes and helper functions
- [Validation Layer](src/TreeHouse/Validation/README.md) - Input validation system
- [View Layer](src/TreeHouse/View/README.md) - Template engine and view management
