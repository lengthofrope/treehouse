# TreeHouse PHP Framework

A modern, lightweight PHP framework built from scratch with zero external dependencies.

## Features

- **Zero Dependencies**: Pure PHP implementation with no external libraries
- **Modern PHP 8.4+**: Built for the latest PHP features
- **MVC Architecture**: Clean separation of concerns
- **Dependency Injection**: Built-in container with automatic resolution
- **Routing**: Flexible HTTP routing with middleware support
- **Template Engine**: Custom template engine with components and layouts
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
treehouse new my-app
cd my-app
composer install
./bin/th serve
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

In `config/routes.php`:

```php
<?php

use App\Controllers\HomeController;

$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show'])
       ->where('id', '\d+');
       
$router->group(['middleware' => 'auth'], function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});
```

### 3. Create Models

```php
<?php

namespace App\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;

class User extends ActiveRecord
{
    protected array $fillable = ['name', 'email'];
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
treehouse new my-app
```

## Configuration

Configuration files are stored in the `config/` directory:

- `app.php` - Application settings
- `database.php` - Database connections
- `cache.php` - Cache configuration
- `routes.php` - Route definitions

## Template Engine

TreeHouse includes a powerful template engine:

```html
@extends('layouts.app')

@section('title', 'Home Page')

@section('content')
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    
    @if($user)
        <p>Welcome, {{ $user->name }}!</p>
    @endif
    
    @foreach($posts as $post)
        <article>
            <h2>{{ $post->title }}</h2>
            <p>{{ $post->excerpt }}</p>
        </article>
    @endforeach
@endsection
```

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

---

Built with ❤️ by Bas de Kort