# TreeHouse PHP Framework

A modern, lightweight PHP framework built from scratch with zero external dependencies.

## WORK IN PROGRESS

Please note that this framework is in WIP state. It is nowhere near production ready.

## Features

- **Zero Dependencies**: Pure PHP implementation with no external libraries
- **Modern PHP 8.4+**: Built for the latest PHP features
- **MVC Architecture**: Clean separation of concerns
- **Dependency Injection**: Built-in container with automatic resolution
- **Routing**: Flexible HTTP routing with middleware support
- **Authorization**: Role-based access control with middleware and template integration
- **Template Engine**: ThymeLeaf-inspired template engine with components and layouts
- **Database ORM**: Active Record pattern with relationships
- **Console Commands**: CLI interface for development and maintenance
- **Caching**: Multi-driver caching system
- **Validation**: Comprehensive form and data validation
- **Security**: Built-in CSRF protection, encryption, and sanitization

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
            'message' => 'Your application is running!'
        ]);
    }
}
```

### 2. Define Routes

In `config/routes/web.php`:

```php
<?php

use App\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show'])
       ->where('id', '\d+');
       
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

// Authorization middleware
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', [AdminController::class, 'users']);
});

$router->get('/posts/create', [PostController::class, 'create'])
       ->middleware('permission:edit-posts');
```

### 3. Create Models

```php
<?php

namespace App\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthorizableUser;

class User extends ActiveRecord implements Authorizable
{
    use AuthorizableUser;
    
    protected array $fillable = ['name', 'email', 'role'];
    protected array $hidden = ['password'];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

### 4. Use the Database

```php
// Create
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Query
$users = User::query()
    ->where('active', true)
    ->orderBy('name')
    ->get();

// Relations
$posts = $user->posts()->where('published', true)->get();
```

## Directory Structure

```
my-app/
├── src/App/              # Application code
│   ├── Controllers/      # HTTP controllers
│   ├── Models/          # Database models
│   ├── Services/        # Business logic
│   └── Middleware/      # HTTP middleware
├── config/              # Configuration files
├── public/              # Web root
├── resources/views/     # Templates
├── storage/             # Cache, logs, compiled views
├── tests/               # Test files
├── database/migrations/ # Database migrations
└── bin/th              # CLI command
```

## CLI Commands

TreeHouse includes a comprehensive CLI tool:

```bash
# Development server
./bin/th serve --port=8000

# Cache management
./bin/th cache:clear
./bin/th cache:stats

# Database
./bin/th migrate:run

# Testing
./bin/th test:run

# Create new project
./bin/treehouse new my-app
```

## Configuration

Configuration files are stored in the `config/` directory:

- `app.php` - Application settings
- `database.php` - Database connections
- `cache.php` - Cache configuration
- `routes/web.php` - Web route definitions
- `routes/api.php` - API route definitions

## Template Engine

TreeHouse includes a powerful ThymeLeaf-inspired template engine:

**Layout Template (`layouts/app.th.html`):**
```html
<!DOCTYPE html>
<html>
<head>
    <title th:text="${title}">Default Title</title>
</head>
<body>
    <main th:fragment="content">
        <!-- Content will be inserted here -->
    </main>
</body>
</html>
```

**Page Template (`home.th.html`):**
```html
<div th:extends="layouts/app" th:fragment="content">
    <h1 th:text="${title}">Welcome</h1>
    <p th:text="${message}">Default message</p>
    
    <div th:auth>
        <p>Welcome back, {user.name}!</p>
        
        <!-- Role-based content -->
        <div th:role="admin">
            <a href="/admin">Admin Panel</a>
        </div>
        
        <!-- Permission-based content -->
        <button th:permission="edit-posts">Create Post</button>
    </div>
    
    <div th:guest>
        <p>Please <a href="/login">log in</a> to continue.</p>
    </div>
    
    <div th:each="post : ${posts}">
        <article>
            <h2 th:text="${post.title}">Post Title</h2>
            <p th:text="${post.excerpt}">Post excerpt</p>
        </article>
    </div>
</div>
```

### Template Features:
- `th:text` - Set element text content
- `th:if` - Conditional rendering
- `th:each` - Loop over collections
- `th:auth` - Show content to authenticated users
- `th:guest` - Show content to guests
- `th:role` - Show content based on user roles
- `th:permission` - Show content based on permissions
- `th:extends` - Extend layout templates
- `th:fragment` - Define reusable fragments
- `${variable}` - Variable interpolation

## Middleware

Create and register middleware for request processing:

```php
<?php

namespace App\Middleware;

use LengthOfRope\TreeHouse\Router\Middleware\MiddlewareInterface;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }
        
        return $next($request);
    }
}
```

## Authorization

TreeHouse includes a comprehensive role-based authorization system:

### User Roles and Permissions

```php
// Check user roles
if ($user->hasRole('admin')) {
    // Admin functionality
}

if ($user->hasAnyRole(['admin', 'editor'])) {
    // Admin or editor functionality
}

// Check permissions
if ($user->can('manage-users')) {
    // User management
}

if ($user->cannot('delete-posts')) {
    // Handle restriction
}
```

### Route Protection

```php
// Role-based middleware
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', [AdminController::class, 'users']);
});

// Permission-based middleware
$router->get('/posts/create', [PostController::class, 'create'])
       ->middleware('permission:edit-posts');

// Multiple roles/permissions (OR logic)
$router->group(['middleware' => 'role:admin,editor'], function($router) {
    $router->get('/posts/manage', [PostController::class, 'manage']);
});
```

### Template Authorization

```html
<!-- Authentication-based -->
<div th:auth>Welcome, {user.name}!</div>
<div th:guest>Please log in.</div>

<!-- Role-based -->
<div th:role="admin">Admin Panel</div>
<div th:role="admin,editor">Content Management</div>

<!-- Permission-based -->
<button th:permission="manage-users">Add User</button>
<div th:permission="edit-posts,delete-posts">Post Tools</div>
```

### Gate System

```php
use LengthOfRope\TreeHouse\Auth\Gate;

// Define custom permissions
Gate::define('edit-post', function($user, $post) {
    return $user->id === $post->author_id || $user->hasRole('admin');
});

// Check permissions
if (Gate::allows('edit-post', $post)) {
    // Edit post
}
```

## Testing

TreeHouse applications come with PHPUnit configured:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;

class UserTest extends TestCase
{
    public function test_user_creation()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        
        $this->assertEquals('Test User', $user->name);
        $this->assertTrue($user->exists);
    }
}
```

Run tests:

```bash
./bin/th test:run
composer test
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

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
