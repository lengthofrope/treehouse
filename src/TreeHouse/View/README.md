# TreeHouse Framework - View Layer

The View Layer provides a powerful template engine with HTML-valid syntax, component support, layout management, and deep integration with the TreeHouse Support classes. This layer enables clean separation of presentation logic from business logic while maintaining developer productivity.

## Table of Contents

- [Overview](#overview)
- [Components](#components)
- [Basic Usage](#basic-usage)
- [Template Syntax](#template-syntax)
- [Layout System](#layout-system)
- [Components](#components-1)
- [Template Compilation](#template-compilation)
- [Helper Functions](#helper-functions)
- [Advanced Features](#advanced-features)
- [Integration](#integration)

## Overview

The View Layer consists of five main components:

- **ViewEngine**: Main template engine coordinator
- **Template**: Individual template handler with rendering capabilities
- **ViewFactory**: Factory for creating view instances with configuration
- **TreeHouseCompiler**: Template compiler for `.th.html` and `.th.php` files
- **Helper Functions**: Global utility functions for templates

### Key Features

- **HTML-Valid Syntax**: Use `th:` attributes that validate as HTML
- **Template Compilation**: Compile templates to optimized PHP code
- **Layout System**: Master layouts with sections and inheritance
- **Component System**: Reusable template components
- **Caching**: Compiled template caching for performance
- **Support Integration**: Deep integration with Support classes
- **Authentication Integration**: Built-in auth context and helpers

## Components

### Core Classes

```php
// Main view engine
LengthOfRope\TreeHouse\View\ViewEngine

// Individual template
LengthOfRope\TreeHouse\View\Template

// View factory
LengthOfRope\TreeHouse\View\ViewFactory

// Template compiler
LengthOfRope\TreeHouse\View\Compilers\TreeHouseCompiler
```

## Basic Usage

### Creating Views

```php
use LengthOfRope\TreeHouse\View\ViewEngine;

// Create view engine
$viewEngine = new ViewEngine([
    '/path/to/templates',
    '/path/to/views'
]);

// Create template
$template = $viewEngine->make('welcome', ['name' => 'John']);

// Render template
$html = $template->render();

// Or render directly
$html = $viewEngine->render('welcome', ['name' => 'John']);
```

### Using Helper Functions

```php
// Global view helper
$template = view('welcome', ['name' => 'John']);
$html = $template->render();

// Direct rendering
$html = render('welcome', ['name' => 'John']);

// Partial templates
$html = partial('header', ['title' => 'Welcome']);

// Components
$html = component('button', ['text' => 'Click me', 'type' => 'primary']);
```

### Template File Extensions

The view system supports multiple file extensions in order of preference:

1. `.th.html` - TreeHouse HTML templates (compiled)
2. `.th.php` - TreeHouse PHP templates (compiled)
3. `.php` - Raw PHP templates (included directly)
4. `.html` - Static HTML templates

## Template Syntax

### Basic Output

```html
<!-- Escaped output -->
<h1>{title}</h1>
<p>Welcome, {user.name}!</p>

<!-- Raw HTML output -->
<div th:html="content"></div>

<!-- Text content (escaped) -->
<span th:text="user.email"></span>
```

### Conditionals

```html
<!-- Simple conditionals -->
<div th:if="user.isActive">User is active</div>
<div th:unless="user.isActive">User is inactive</div>

<!-- Switch/case conditionals -->
<div th:switch="user.role">
    <div th:case="admin">Administrator Dashboard</div>
    <div th:case="editor">Content Editor Panel</div>
    <div th:case="user">User Profile</div>
    <div th:default>Guest Access</div>
</div>

<!-- Authentication conditionals -->
<nav th:auth>
    <a href="/dashboard">Dashboard</a>
</nav>

<div th:guest>
    <a href="/login">Login</a>
</div>

<!-- Role-based conditionals -->
<button th:role="admin">Admin Panel</button>
<button th:role="admin,editor">Content Management</button>

<!-- Permission-based conditionals -->
<button th:permission="manage-users">Manage Users</button>
<button th:permission="edit-posts,publish-posts">Edit Posts</button>
```

### Loops

```html
<!-- Simple loop -->
<ul>
    <li th:repeat="item items" th:text="item.name"></li>
</ul>

<!-- Loop with key -->
<ul>
    <li th:repeat="key,item items">
        <strong>{key}</strong>: {item.value}
    </li>
</ul>

<!-- Loop with array variable -->
<div th:repeat="user $users">
    <h3>{user.name}</h3>
    <p>{user.email}</p>
</div>
```

### Local Variables

```html
<!-- Create local variables with th:with -->
<div th:with="fullName=user.firstName + ' ' + user.lastName">
    <h1>Welcome, {fullName}!</h1>
    <p>User ID: {user.id}</p>
</div>

<!-- Multiple variables -->
<div th:with="total=price * quantity, tax=total * 0.08">
    <p>Subtotal: ${total}</p>
    <p>Tax: ${tax}</p>
    <p>Total: ${total + tax}</p>
</div>
```

### Attributes

```html
<!-- Dynamic attributes -->
<input th:value="user.name" th:placeholder="Enter your name">
<img th:src="user.avatar" th:alt="user.name">

<!-- Class attributes -->
<div th:class="user.status">Content</div>
<div th:class="'btn btn-' + button.type">Button</div>

<!-- Multiple attributes -->
<input th:attr="id='user-' + user.id, class=user.role">

<!-- Boolean attributes -->
<input th:checked="user.isActive" type="checkbox">
<button th:disabled="!user.canEdit">Edit</button>
```

### Universal th: Attributes

Any HTML attribute can be prefixed with `th:` for dynamic values:

```html
<!-- Standard attributes -->
<a th:href="'/users/' + user.id">View User</a>
<input th:name="'field_' + index" th:id="'input_' + index">

<!-- Data attributes -->
<div th:data-user-id="user.id" th:data-role="user.role">Content</div>

<!-- Custom attributes -->
<element th:custom-attr="someValue" th:another-attr="anotherValue">
```

### Form Handling

```html
<!-- CSRF protection -->
<form method="POST" action="/contact" th:csrf>
    <!-- Form fields here -->
</form>

<!-- HTTP method spoofing -->
<form method="POST" action="/users/1" th:method="PUT" th:csrf>
    <!-- Update form -->
</form>

<!-- Dynamic method from variable -->
<form method="POST" action="/contact" th:method="form.contact.method" th:csrf>
    <!-- Method determined at runtime -->
</form>

<!-- Form field binding -->
<form th:csrf>
    <input th:field="user.name" type="text" placeholder="Name">
    <div th:errors="user.name" class="error-text"></div>
    
    <input th:field="user.email" type="email" placeholder="Email">
    <div th:errors="user.email" class="error-text"></div>
    
    <button type="submit">Save</button>
</form>
```

### Expressions

```html
<!-- Variable access -->
{title}
{user.name}
{config.app.name}

<!-- Array access -->
{users[0].name}
{data['key']}

<!-- Method calls -->
{user.getFullName()}
{Str::title(user.name)}

<!-- Arithmetic -->
{price * quantity}
{total + tax}

<!-- Comparisons -->
{user.age >= 18}
{status == 'active'}

<!-- Mixed expressions -->
{'Hello ' + user.name + '!'}
{user.isActive ? 'Active' : 'Inactive'}
```

## Layout System

### Master Layout

```html
<!-- layouts/app.th.html -->
<!DOCTYPE html>
<html>
<head>
    <title th:text="title">Default Title</title>
</head>
<body>
    <header th:yield="header">
        <h1>Default Header</h1>
    </header>
    
    <main th:yield="content">
        Default content
    </main>
    
    <footer th:yield="footer, 'Default Footer'">
    </footer>
</body>
</html>
```

### Child Template

```html
<!-- pages/welcome.th.html -->
<div th:extend="layouts.app">
    <div th:section="header">
        <h1>Welcome Page</h1>
        <nav>Navigation here</nav>
    </div>
    
    <div th:section="content">
        <h2>Welcome, {user.name}!</h2>
        <p>This is the main content.</p>
    </div>
</div>
```

### Template Fragments

```html
<!-- Define reusable fragments -->
<div th:fragment="userCard(user)">
    <div class="card">
        <h3>{user.name}</h3>
        <p>{user.email}</p>
        <span class="status">{user.status}</span>
    </div>
</div>

<!-- Include fragment content -->
<div th:include="fragments::userCard(currentUser)">
    <!-- Content will be inserted here -->
</div>

<!-- Replace entire element with fragment -->
<placeholder th:replace="fragments::userCard(currentUser)">
    <!-- This element will be completely replaced -->
</placeholder>
```

### Programmatic Layout Usage

```php
// In PHP code
$template = view('welcome', ['user' => $user]);
$template->extend('layouts.app');
$template->startSection('content');
echo '<h1>Dynamic Content</h1>';
$template->endSection();

$html = $template->render();
```

## Components

### Defining Components

```html
<!-- components/button.th.html -->
<button 
    th:class="'btn btn-' + (type ?: 'default')" 
    th:disabled="disabled"
    th:onclick="onclick">
    {text ?: 'Button'}
</button>
```

### Using Components

```html
<!-- In templates -->
<div th:component="button" 
     th:props-text="'Save Changes'" 
     th:props-type="'primary'"
     th:props-onclick="'saveForm()'">
</div>

<!-- Or with helper function -->
<?php echo component('button', [
    'text' => 'Save Changes',
    'type' => 'primary',
    'onclick' => 'saveForm()'
]); ?>
```

### Registering Components

```php
// Register component
$viewEngine->component('alert', 'components.alert');
$viewEngine->component('card', 'components.card');

// Use registered component
$html = $viewEngine->renderComponent('alert', [
    'type' => 'success',
    'message' => 'Operation completed!'
]);
```

## Template Compilation

### Compilation Process

The TreeHouseCompiler transforms `.th.html` and `.th.php` templates:

```html
<!-- Input: welcome.th.html -->
<div th:if="user.isActive">
    <h1>Welcome, {user.name}!</h1>
    <p th:text="user.email"></p>
</div>

<!-- Compiled Output: -->
<?php if ($user['isActive']): ?>
<div>
    <h1>Welcome, <?php echo thEscape($user['name']); ?>!</h1>
    <p><?php echo thEscape($user['email']); ?></p>
</div>
<?php endif; ?>
```

### Supported Attributes

The compiler processes these `th:` attributes in order:

1. **Conditionals**: `th:if`, `th:unless`, `th:auth`, `th:guest`
2. **Switch/Case**: `th:switch`, `th:case`, `th:default`
3. **Authorization**: `th:role`, `th:permission`
4. **Local Variables**: `th:with`
5. **Loops**: `th:repeat`
6. **Content**: `th:text`, `th:html`, `th:raw`
7. **Fragments**: `th:fragment`, `th:include`, `th:replace`
8. **Form Handling**: `th:field`, `th:errors`, `th:csrf`, `th:method`
9. **Attributes**: `th:attr`, `th:class`, `th:style`
10. **Layout**: `th:extend`, `th:section`, `th:yield`
11. **Components**: `th:component`
12. **Removal**: `th:remove`
13. **Universal**: Any `th:*` attribute

### Caching

```php
// Templates are automatically cached
$viewEngine = new ViewEngine($paths, $cache);

// Clear cache
$viewEngine->clearCache();

// Check if template exists
if ($viewEngine->exists('welcome')) {
    $html = $viewEngine->render('welcome', $data);
}
```

## Helper Functions

### Template Helpers

```php
// Available in all templates as $th object
$th->e($value)              // Escape HTML
$th->collect($array)        // Convert to Collection
$th->money($amount)         // Format money
$th->number($number)        // Format number
$th->date($date, $format)   // Format date
$th->get($array, $key)      // Array access with dot notation
$th->isEmpty($value)        // Check if empty
$th->old($key, $default)    // Get old input
$th->url($path)             // Generate URL
$th->asset($path)           // Generate asset URL
$th->route($name, $params)  // Generate route URL
$th->csrfToken()            // Get CSRF token
$th->csrfField()            // Generate CSRF field
$th->limit($string, $limit) // Limit string length
$th->title($string)         // Convert to title case
```

### Global Helper Functions

```php
// View creation
view($template, $data)      // Create view
render($template, $data)    // Render template
partial($template, $data)   // Render partial
component($name, $props)    // Render component

// Template helpers
thEscape($value)            // Escape HTML
thRaw($value)               // Raw HTML output
thCollect($array)           // Convert to Collection

// URL helpers
asset($path)                // Asset URL
url($path)                  // Generate URL
route($name, $params)       // Route URL

// Form helpers
old($key, $default)         // Old input value
csrfToken()                 // CSRF token
csrfField()                 // CSRF field HTML
methodField($method)        // HTTP method field
formMethod($method, $csrf)  // Method + CSRF fields
```

### TreeHouse Asset Helpers

```php
// TreeHouse framework assets
treehouseAsset($path)           // TreeHouse asset URL
treehouseConfig($config)        // TreeHouse JS configuration
treehouseJs($modules)           // TreeHouse JavaScript
treehouseCss($minified)         // TreeHouse CSS
treehouseSetup($options)        // Complete TreeHouse setup
treehouseLogo($alt, $attrs)     // TreeHouse logo
treehouseFavicons()             // TreeHouse favicons

// Vite integration
vite($path)                     // Vite asset URL
viteAssets($entry)              // Vite script and CSS tags
```

### Security Helpers

```php
// CSRF protection
csrfToken()                     // Get CSRF token
csrfField($dynamic)             // CSRF field HTML
csrfMeta($dynamic)              // CSRF meta tag
csrfSetup($options)             // Complete CSRF setup

// HTTP method spoofing
methodField($method)            // Method spoofing field
formMethod($method, $csrf)      // Method + CSRF fields
```

## Advanced Features

### View Composers

```php
// Register view composer
$viewEngine->composer('layouts.app', function($data) {
    return [
        'currentYear' => date('Y'),
        'siteName' => 'My Application'
    ];
});

// Multiple views
$viewEngine->composer(['pages.*', 'admin.*'], function($data) {
    return ['user' => auth()->user()];
});
```

### Shared Data

```php
// Share data with all views
$viewEngine->share('appName', 'TreeHouse Framework');
$viewEngine->share([
    'version' => '1.0.0',
    'environment' => 'production'
]);

// Access in templates
<title>{appName} - {title}</title>
```

### Template Aliases

```php
// Register aliases
$viewEngine->alias('home', 'pages.welcome');
$viewEngine->alias('login', 'auth.login');

// Use aliases
$html = $viewEngine->render('home', $data);
```

### Multiple View Engines

```php
$factory = new ViewFactory();

// Register custom engine
$customEngine = new ViewEngine(['/custom/templates']);
$factory->extend('custom', $customEngine);

// Use specific engine
$template = $factory->make('template', $data, 'custom');
```

### Error Handling

```php
try {
    $html = view('template', $data)->render();
} catch (InvalidArgumentException $e) {
    // Template not found
    $html = view('errors.404')->render();
} catch (RuntimeException $e) {
    // Rendering error
    $html = view('errors.500', ['error' => $e->getMessage()])->render();
}
```

## Integration

### HTTP Response Integration

```php
use LengthOfRope\TreeHouse\Http\Response;

class PageController
{
    public function welcome()
    {
        $template = view('welcome', ['user' => auth()->user()]);
        return $template->toResponse();
        
        // Or directly
        return new Response($template->render());
    }
}
```

### Router Integration

```php
use LengthOfRope\TreeHouse\Router\Router;

$router = new Router();

$router->get('/welcome', function() {
    return view('welcome', ['title' => 'Welcome']);
});

$router->get('/users/{id}', function($id) {
    $user = User::find($id);
    return view('users.show', compact('user'));
});
```

### Authentication Integration

```php
// Auth context is automatically shared
// Available in all templates:
// - $auth: AuthManager instance
// - $user: Current user (lazy-loaded)
// - $gate: Gate instance
// - can(): Permission check function
// - cannot(): Inverse permission check

// In templates:
<div th:auth>
    Welcome, {user.name}!
    <div th:role="admin">Admin Panel</div>
    <div th:permission="edit-posts">Edit Posts</div>
</div>
```

### Cache Integration

```php
use LengthOfRope\TreeHouse\Cache\FileCache;

$cache = new FileCache('/path/to/cache');
$viewEngine = new ViewEngine($paths, $cache);

// Templates are automatically cached
// Cache keys: 'view_' + md5(templatePath)
```

### Configuration

```php
// config/view.php
return [
    'paths' => [
        __DIR__ . '/../resources/views',
        __DIR__ . '/../templates',
    ],
    'cache_path' => __DIR__ . '/../storage/views',
    'cache_enabled' => env('VIEW_CACHE', true),
    'extensions' => ['.th.html', '.th.php', '.php', '.html'],
];
```

### Testing Views

```php
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    public function testWelcomeView()
    {
        $html = render('welcome', ['name' => 'John']);
        
        $this->assertStringContains('Welcome, John!', $html);
        $this->assertStringContains('<h1>', $html);
    }
    
    public function testConditionalRendering()
    {
        $html = render('profile', ['user' => ['isActive' => true]]);
        $this->assertStringContains('User is active', $html);
        
        $html = render('profile', ['user' => ['isActive' => false]]);
        $this->assertStringNotContains('User is active', $html);
    }
    
    public function testComponentRendering()
    {
        $html = component('button', ['text' => 'Test', 'type' => 'primary']);
        $this->assertStringContains('btn-primary', $html);
        $this->assertStringContains('Test', $html);
    }
}
```

### Advanced Template Features

```php
// Test switch/case functionality
public function testSwitchCaseRendering()
{
    $html = render('profile', ['user' => ['role' => 'admin']]);
    $this->assertStringContains('Administrator Dashboard', $html);
    
    $html = render('profile', ['user' => ['role' => 'unknown']]);
    $this->assertStringContains('Guest Access', $html);
}

// Test form functionality
public function testFormRendering()
{
    $html = render('contact-form', ['form' => ['method' => 'POST']]);
    $this->assertStringContains('method="POST"', $html);
    $this->assertStringContains('name="_token"', $html);
}

// Test fragment functionality
public function testFragmentRendering()
{
    $html = render('user-list', ['users' => [['name' => 'John']]]);
    $this->assertStringContains('John', $html);
    $this->assertStringContains('class="card"', $html);
}
```

### Performance Optimization

```php
// Enable template caching
$viewEngine->setCache($cache);

// Precompile templates
$templates = ['welcome', 'layout.app', 'components.button'];
foreach ($templates as $template) {
    if ($viewEngine->exists($template)) {
        $viewEngine->compile($viewEngine->findTemplate($template));
    }
}

// Clear cache when needed
$viewEngine->clearCache();
```

## Key Methods

### ViewEngine Class

- [`make(string $template, array $data = []): Template`](src/TreeHouse/View/ViewEngine.php:119) - Create template instance
- [`render(string $template, array $data = []): string`](src/TreeHouse/View/ViewEngine.php:151) - Render template
- [`addPath(string $path): self`](src/TreeHouse/View/ViewEngine.php:85) - Add template search path
- [`share(string|array $key, mixed $value = null): self`](src/TreeHouse/View/ViewEngine.php:97) - Share global data
- [`alias(string $alias, string $template): self`](src/TreeHouse/View/ViewEngine.php:110) - Register template alias
- [`exists(string $template): bool`](src/TreeHouse/View/ViewEngine.php:276) - Check if template exists
- [`compile(string $templatePath): string`](src/TreeHouse/View/ViewEngine.php:179) - Compile template
- [`composer(string|array $views, callable $callback): self`](src/TreeHouse/View/ViewEngine.php:297) - Register view composer

### Template Class

- [`render(): string`](src/TreeHouse/View/Template.php:71) - Render template
- [`with(string|array $key, mixed $value = null): self`](src/TreeHouse/View/Template.php:398) - Add template data
- [`extend(string $layout): void`](src/TreeHouse/View/Template.php:192) - Extend layout
- [`startSection(string $name): void`](src/TreeHouse/View/Template.php:161) - Start section capture
- [`endSection(): void`](src/TreeHouse/View/Template.php:170) - End section capture
- [`yieldSection(string $name, string $default = ''): string`](src/TreeHouse/View/Template.php:184) - Yield section content
- [`include(string $template, array $data = []): string`](src/TreeHouse/View/Template.php:200) - Include partial
- [`toResponse(): Response`](src/TreeHouse/View/Template.php:382) - Convert to HTTP response

### ViewFactory Class

- [`make(string $template, array $data = [], ?string $engine = null): Template`](src/TreeHouse/View/ViewFactory.php:95) - Create view
- [`render(string $template, array $data = [], ?string $engine = null): string`](src/TreeHouse/View/ViewFactory.php:103) - Render view
- [`engine(?string $name = null): ViewEngine`](src/TreeHouse/View/ViewFactory.php:72) - Get view engine
- [`extend(string $name, ViewEngine $engine): self`](src/TreeHouse/View/ViewFactory.php:86) - Register engine
- [`clearCache(?string $engine = null): self`](src/TreeHouse/View/ViewFactory.php:164) - Clear cache

### TreeHouseCompiler Class

- [`compile(string $template): string`](src/TreeHouse/View/Compilers/TreeHouseCompiler.php:66) - Compile template
- [`getSupportedAttributes(): array`](src/TreeHouse/View/Compilers/TreeHouseCompiler.php:834) - Get supported attributes
- [`isAttributeSupported(string $attribute): bool`](src/TreeHouse/View/Compilers/TreeHouseCompiler.php:842) - Check attribute support

The View Layer provides a powerful, flexible template system that maintains HTML validity while offering advanced features like compilation, caching, components, and deep framework integration.
