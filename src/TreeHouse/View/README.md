# TreeHouse View Layer

Modern template engine with HTML-valid syntax, universal th: attributes, automatic text processing, and deep Support class integration. Built for developer experience with zero external dependencies.

## Revolutionary Features

### Universal th: Attributes
**Any HTML attribute can be prefixed with `th:` for dynamic content:**

```html
<!-- Clean universal attributes with brace syntax -->
<a th:href="/user/{user.id}" 
   th:title="View {user.name}" 
   th:data-role="{user.role}">
   
<input th:id="field-{user.id}" 
       th:value="{user.email}"
       th:disabled="{!user.canEdit}">
```

### Automatic Text Processing
**Brace expressions in text content are automatically processed:**

```html
<!-- Natural text interpolation - no th:text needed -->
<h1>Welcome, {user.name}!</h1>
<p>You have {notifications.count} unread messages</p>
<button>Delete {user.name}</button>
```

### Dot Notation Support
**Clean object property access:**

```html
<!-- Intuitive dot syntax instead of PHP array notation -->
<span th:text="user.profile.settings.theme">Theme</span>
<p>Current theme: {user.profile.settings.theme}</p>
```

## Core Features

- **HTML-Valid Syntax**: `th:` attributes work in any HTML editor/IDE with full validation
- **Universal th: Attributes**: ANY HTML attribute can be prefixed with `th:` for dynamic content
- **Automatic Text Processing**: `{variable}` syntax in text nodes processed automatically
- **Dot Notation**: Clean `user.name` syntax instead of `$user['name']`
- **Support Integration**: Collection, Arr, Str, Carbon, Uuid classes available everywhere
- **Component System**: Reusable components with props, state, and lifecycle hooks
- **Layout System**: Template inheritance with section management
- **Smart Compilation**: Efficient caching with DOM-based compilation
- **Zero Dependencies**: Pure PHP solution, no external template engines

## Quick Start

```php
// Create and render templates
$view = view('user.profile', ['user' => $user]);
$html = render('user.profile', ['user' => $user]);
$view = layout('app', 'user.profile', ['user' => $user]);
```

### Basic Template Example

```html
<!-- resources/views/user/profile.th.html -->
<div class="profile">
    <!-- Universal th: attributes with brace syntax -->
    <img th:src="/avatars/{user.id}.jpg" 
         th:alt="Avatar for {user.name}">
    
    <!-- Automatic text content processing -->
    <h1>Welcome back, {user.name}!</h1>
    <p>Status: {user.active ? 'Online' : 'Offline'}</p>
    
    <!-- Dot notation for clean property access -->
    <span th:text="user.profile.settings.theme">Theme</span>
    
    <!-- Boolean attributes work perfectly -->
    <input type="checkbox" th:checked="{user.active}"> Active User
    
    <!-- Collection operations with Support classes -->
    <div th:if="user.posts.isNotEmpty">
        <h2>Recent Posts ({user.posts.count} total)</h2>
        <article th:repeat="post user.posts.take(5)" class="post">
            <h3>{post.title}</h3>
            <time>{post.created_at.format('F j, Y')}</time>
            <p>{Str::limit(post.excerpt, 100)}</p>
            <a th:href="/posts/{post.id}" th:class="btn btn-{post.status}">
                Read More
            </a>
        </article>
    </div>
</div>
```

## Template Syntax

### Universal th: Attributes

Any HTML attribute can be prefixed with `th:` for dynamic content:

```html
<!-- Dynamic attributes with brace expressions -->
<a th:href="/posts/{post.id}" th:title="Edit {post.title}">Edit</a>
<input th:id="field-{user.id}" th:value="{user.email}" th:placeholder="Enter email for {user.name}">
<div th:class="card {user.role} {user.active ? 'active' : 'inactive'}" th:data-user-id="{user.id}">

<!-- Boolean attributes -->
<input type="checkbox" th:checked="{user.active}">
<button th:disabled="{user.isProcessing}">Save</button>
<option th:selected="{user.role == 'admin'}">Admin</option>
```

### Automatic Text Processing

Brace expressions in text are processed automatically:

```html
<!-- Simple interpolation -->
<h1>Welcome, {user.name}!</h1>
<p>You have {notifications.count} unread messages</p>

<!-- Complex expressions -->
<span>Status: {user.active ? 'Online' : 'Offline'}</span>
<p>Last seen: {user.lastSeen ? user.lastSeen.diffForHumans : 'Never'}</p>
```

### Dot Notation

Clean object property access:

```html
<!-- Nested property access -->
<span th:text="user.profile.settings.theme">Theme</span>
<p>Company: {company.info.details.name}</p>
<img th:src="/uploads/{user.avatar.filename}" th:alt="{user.profile.displayName}">
```

### Conditionals

```html
<div th:if="user.isActive">User is active</div>
<div th:unless="errors.isEmpty">Show errors</div>
<div th:if="{user.role == 'admin'}">Admin content</div>
```

### Authorization Directives

TreeHouse includes built-in authorization directives for controlling content based on user authentication and permissions:

```html
<!-- Authentication-based content -->
<div th:auth>
    Welcome back, {user.name}!
</div>

<div th:guest>
    Please <a href="/login">log in</a> to continue.
</div>

<!-- Role-based content -->
<div th:role="admin">
    <a href="/admin">Administrator Panel</a>
</div>

<div th:role="admin,editor">
    <a href="/posts/manage">Manage Posts</a>
</div>

<!-- Permission-based content -->
<div th:permission="manage-users">
    <button>Add User</button>
</div>

<div th:permission="edit-posts,delete-posts">
    <button>Manage Posts</button>
</div>

<!-- Mixed authorization -->
<div th:auth>
    <h2>User Panel</h2>
    
    <div th:role="admin">
        <h3>Admin Tools</h3>
        <a href="/admin/settings">Settings</a>
    </div>
    
    <div th:permission="view-analytics">
        <h3>Analytics</h3>
        <a href="/analytics">View Stats</a>
    </div>
    
    <div th:unless="user.hasRole('admin')">
        <p>Some features require administrator privileges.</p>
    </div>
</div>
```

### Loops

```html
<!-- Simple iteration -->
<li th:repeat="item items" th:text="item.name">Item</li>

<!-- Key-value iteration -->
<li th:repeat="key,item items">{key}: {item}</li>

<!-- With repeat status -->
<li th:repeat="item items" th:class="th_repeat.first ? 'first' : ''">
    <span th:text="th_repeat.index + 1">1</span>. {item.name}
</li>
```

### Content

```html
<!-- Escaped text (explicit) -->
<h1 th:text="user.name">User Name</h1>

<!-- Escaped text (automatic) -->
<h1>Welcome, {user.name}!</h1>

<!-- Raw HTML -->
<div th:html="content">Content</div>
```

## Support Class Integration

All Support classes are automatically available in templates:

```html
<!-- Collection methods -->
<p>{users.count} users found</p>
<div th:if="products.where('featured', true).isNotEmpty()">Featured products</div>

<!-- String utilities -->
<h1 th:text="Str::title(category.name)">Category</h1>
<p>{Str::limit(description, 150, '...')}</p>

<!-- Array utilities -->
<span th:text="Arr::join(tags.pluck('name').toArray(), ', ')">Tags</span>

<!-- Date handling -->
<time>{post.created_at.format('F j, Y')}</time>
<span>{post.created_at.diffForHumans()}</span>

<!-- UUID generation -->
<input th:id="field-{Uuid::generate()}" type="text">
```

## Layout System

### Parent Layout

```html
<!-- resources/views/layouts/app.th.html -->
<!DOCTYPE html>
<html>
<head>
    <title th:text="title ?? 'My App'">My App</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <nav><!-- Navigation --></nav>
    <main><?php echo $this->yieldSection('content'); ?></main>
    <footer><!-- Footer --></footer>
</body>
</html>
```

### Child Template

```html
<!-- resources/views/user/profile.th.html -->
<div th:extend="layouts.app">
    <div th:section="content">
        <h1>{user.name}</h1>
        <!-- Profile content -->
    </div>
</div>
```

## Components

### Component Class

```php
<?php
namespace App\View\Components;

use LengthOfRope\TreeHouse\View\Components\Component;

class UserCard extends Component
{
    public function template(): string
    {
        return 'components.user-card';
    }
    
    protected function getInitialState(): array
    {
        return [
            'showDetails' => $this->prop('expanded', false),
        ];
    }
    
    public function getIsActiveProperty(): bool
    {
        return $this->prop('user')->isActive();
    }
}
```

### Component Template

```html
<!-- resources/views/components/user-card.th.html -->
<div class="user-card" th:attr="attributes">
    <img th:src="{user.avatar}" th:alt="{user.name}" class="avatar">
    <h3>{user.name}</h3>
    <p th:if="showDetails">{user.bio}</p>
    <span th:if="computed('isActive')" class="badge active">Active</span>
    <div th:html="slot">Default content</div>
</div>
```

### Using Components

```html
<!-- In templates -->
<div th:component="user-card" th:props-user="user" th:props-expanded="true">
    <p>Additional content</p>
</div>

<!-- In PHP -->
<?php echo UserCard::make(['user' => $user, 'expanded' => true]); ?>
```

## Configuration

### Basic Setup

```php
use LengthOfRope\TreeHouse\View\ViewFactory;

$factory = new ViewFactory([
    'paths' => [
        __DIR__ . '/resources/views',
        __DIR__ . '/templates',
    ],
    'cache_path' => __DIR__ . '/storage/framework/views',
    'cache_enabled' => true,
    'extensions' => ['.th.html', '.th.php', '.php', '.html'],
]);

// Use the factory
$view = $factory->make('welcome', ['name' => 'World']);
echo $view->render();
```

### View Composers

```php
// Register a view composer for automatic data injection
view()->composer('user.*', function ($data) {
    return array_merge($data, [
        'currentUser' => getCurrentUser(),
        'notifications' => getNotifications(),
    ]);
});
```

### Helper Functions

Global helper functions available everywhere:

```php
// Template creation and rendering
view('template', $data)          // Create template instance
render('template', $data)        // Render template directly  
partial('name', $data)           // Render partial template
component('name', $props)        // Render component

// Security helpers
csrfToken()                      // Get CSRF token
csrfField()                      // Generate CSRF hidden field
methodField('PUT')               // Generate method override field

// URL and asset helpers
asset('css/app.css')             // Generate asset URL
url('/dashboard')                // Generate application URL  
route('user.show', ['id' => 1])  // Generate route URL
```

## Architecture

### Core Classes

#### ViewEngine
Main template engine coordinator:
- Template path resolution across multiple directories
- Template compilation and caching with CacheInterface integration
- Global shared data management across all templates  
- Component and layout registration
- View composer registration for automatic data injection

#### Template  
Individual template handler:
- Template rendering with layout inheritance
- Section management (startSection, endSection, yieldSection)
- Recursive rendering depth protection (max 10 levels)
- Rich template helper functions
- Component rendering within templates

#### TreeHouseCompiler
Advanced template compiler:
- Universal th: attribute processing (ANY HTML attribute can be prefixed)
- Intelligent brace expression compilation `{variable}`
- Dot notation conversion (`user.name` → `$user['name']`)
- Automatic text node processing for brace expressions
- Boolean attribute handling (checked, selected, disabled, etc.)
- Support class integration (Collection, Arr, Str, Carbon, Uuid)

#### ViewFactory
Multi-engine factory:
- Multiple view engine instances
- Configuration management (paths, caching, extensions)
- Template existence checking across engines
- Cache management across all engines

### File Extensions

- `.th.html` - TreeHouse HTML templates with th: attributes (preferred)
- `.th.php` - TreeHouse PHP templates with full PHP support
- `.php` - Raw PHP templates (no compilation, direct inclusion)
- `.html` - Static HTML files

## Best Practices

### Template Organization
1. **Use meaningful template paths** - Organize templates by feature/controller
2. **Leverage universal th: attributes** with brace syntax for cleaner, more maintainable code
3. **Prefer automatic text processing** - Use `{variable}` in text instead of `th:text` when possible
4. **Use dot notation** for cleaner object property access (`user.name` vs `$user['name']`)
5. **Create reusable components** for common UI patterns (cards, forms, buttons)
6. **Use layouts consistently** for page structure and maintain design consistency
7. **Organize partials** in dedicated directories (`components/`, `partials/`, `widgets/`)

### Performance Optimization
8. **Enable template caching** in production environments for optimal performance
9. **Use view composers** for shared template data instead of passing same data everywhere
10. **Minimize complex logic** in templates - move business logic to controllers or components
11. **Cache expensive operations** - Don't perform database queries in templates
12. **Use lazy loading** for components that may not always render

### Code Quality
13. **Validate template data** before passing to views to prevent undefined variable errors
14. **Use type hints** in component classes for better IDE support and error detection
15. **Test your templates** - Create unit tests for complex components and view composers
16. **Document complex templates** - Add comments for intricate template logic
17. **Use consistent naming** - Follow naming conventions for variables, templates, and components

### Security Practices
18. **Always escape user input** - The `{variable}` syntax automatically escapes, use `th:html` only when needed
19. **Use CSRF protection** - Include `csrfField()` in all forms
20. **Sanitize file paths** - Never trust user input for template paths
21. **Validate component props** - Check and sanitize component properties
22. **Use proper HTTP methods** - Include `methodField()` for PUT/DELETE operations

### Development Workflow
23. **Disable caching in development** for immediate template updates
24. **Use HTML-valid syntax** to leverage IDE features (autocomplete, validation, formatting)
25. **Test in multiple browsers** - Ensure th: attributes don't cause issues
26. **Use version control** - Track template changes and maintain template history
27. **Code reviews** - Review template changes for security and performance issues

### Template Structure
```html
<!-- ✅ Good template structure -->
<div th:extend="layouts.app">
    <div th:section="content">
        <!-- Main content with clean syntax -->
        <h1>{page.title}</h1>
        
        <!-- Component usage -->
        <div th:component="user-card" th:props-user="user">
            Additional content
        </div>
        
        <!-- Collection operations -->
        <div th:if="items.isNotEmpty()">
            <article th:repeat="item items" class="item">
                <h2>{item.title}</h2>
                <p>{Str::limit(item.description, 100)}</p>
            </article>
        </div>
    </div>
    
    <div th:section="scripts">
        <script src="/js/page-specific.js"></script>
    </div>
</div>
```

### Component Design
```php
// ✅ Good component structure
class UserCard extends Component
{
    public function template(): string
    {
        return 'components.user-card';
    }
    
    protected function getInitialState(): array
    {
        return [
            'showDetails' => $this->prop('expanded', false),
            'avatar' => $this->prop('user')->avatar ?? '/images/default-avatar.svg',
        ];
    }
    
    // Computed properties for complex logic
    public function getIsActiveProperty(): bool
    {
        $user = $this->prop('user');
        return $user && $user->active && !$user->suspended;
    }
    
    public function shouldUpdate(array $newProps): bool
    {
        return $newProps['user']->id !== $this->prop('user')->id;
    }
}
```

## Migration Guide

### Upgrading to Universal th: Attributes

The new features are **100% backwards compatible**. You can upgrade gradually:

```html
<!-- OLD: Complex th:attr syntax -->
<a th:attr="href='/user/' . $user['id'], title='View ' . $user['name']">
    Link
</a>

<!-- NEW: Clean universal attributes -->
<a th:href="/user/{user.id}" th:title="View {user.name}">
    Link  
</a>

<!-- OLD: PHP syntax in text -->
<h1 th:text="'Welcome, ' . $user['name'] . '!'">Welcome</h1>

<!-- NEW: Automatic text processing -->
<h1>Welcome, {user.name}!</h1>

<!-- OLD: Complex array access -->
<span th:text="$user['profile']['settings']['theme']">Theme</span>

<!-- NEW: Dot notation -->
<span th:text="user.profile.settings.theme">Theme</span>
<p>Current theme: {user.profile.settings.theme}</p>
```

## Migration Guide

### Upgrading to Universal th: Attributes

The revolutionary new features are **100% backwards compatible**. You can upgrade gradually:

```html
<!-- PHASE 1: Keep existing templates working -->
<a th:attr="href='/user/' . $user['id'], title='View ' . $user['name']">Link</a>
<h1 th:text="'Welcome, ' . $user['name'] . '!'">Welcome</h1>
<span th:text="$user['profile']['settings']['theme']">Theme</span>

<!-- PHASE 2: Start using new syntax for new templates -->
<a th:href="/user/{user.id}" th:title="View {user.name}">Link</a>
<h1>Welcome, {user.name}!</h1>
<span th:text="user.profile.settings.theme">Theme</span>

<!-- PHASE 3: Gradually refactor existing templates -->
<div th:class="card {user.role} {user.active ? 'active' : 'inactive'}">
    <img th:src="/avatars/{user.id}.jpg" th:alt="Avatar for {user.name}">
    <h2>{user.name}</h2>
    <p>Status: {user.active ? 'Online' : 'Offline'}</p>
    <button th:data-user-id="{user.id}" th:disabled="{user.isProcessing}">
        Save Changes
    </button>
</div>
```

### Migration Strategy

**Immediate Benefits (No Changes Required):**
- All existing templates continue to work exactly as before
- No breaking changes to existing `th:attr`, `th:text`, or other attributes
- Existing Support class usage remains unchanged
- Component system backwards compatible

**Gradual Adoption Approach:**
1. **New Templates First** - Use new syntax for all new template development
2. **High-Traffic Templates** - Refactor templates that are frequently modified
3. **Component Templates** - Update component templates for better maintainability
4. **Layout Templates** - Modernize layouts for consistent new syntax usage

**Feature-by-Feature Migration:**

```html
<!-- Attributes: Old → New -->
<!-- OLD -->
<input th:attr="id='user-' + $user['id'], name='user[' + $user['id'] + '][email]', value=$user['email']">

<!-- NEW -->
<input th:id="user-{user.id}" th:name="user[{user.id}][email]" th:value="{user.email}">

<!-- Text Content: Old → New -->
<!-- OLD -->
<h1 th:text="'Welcome back, ' . $user['name'] . '!'">Welcome</h1>

<!-- NEW -->
<h1>Welcome back, {user.name}!</h1>

<!-- Object Access: Old → New -->
<!-- OLD -->
<span th:text="$user['profile']['settings']['theme']">Theme</span>

<!-- NEW -->
<span>{user.profile.settings.theme}</span>

<!-- Boolean Attributes: Old → New -->
<!-- OLD -->
<option th:attr="selected=$user['role'] == 'admin' ? 'selected' : null">Admin</option>

<!-- NEW -->
<option th:selected="{user.role == 'admin'}">Admin</option>
```

### Testing During Migration

**Verify Template Compatibility:**
```php
// Test that old and new syntax work together
$html = render('mixed-syntax-test', [
    'user' => $user,
    'items' => $collection,
]);

// Check for compilation errors
try {
    view()->clearCache();
    $compiled = view()->getCompiler()->compile($templateContent);
} catch (\Exception $e) {
    echo "Compilation error: " . $e->getMessage();
}
```

**Template Validation:**
```php
// Validate that templates render correctly
$oldTemplate = render('old-syntax-template', $data);
$newTemplate = render('new-syntax-template', $data);

// Compare essential content (ignoring whitespace differences)
assert(trim($oldTemplate) === trim($newTemplate));
```

## Troubleshooting

### Common Issues with Universal th: Attributes

**Attribute not rendering:**
```html
<!-- ❌ Wrong: Missing quotes -->
<div th:data-id={user.id}>Content</div>

<!-- ✅ Correct: Proper quotes -->
<div th:data-id="{user.id}">Content</div>
```

**Brace expressions in code blocks:**
```html
<!-- ❌ Problem: Braces processed in code -->
<code>Use {user.name} syntax</code>

<!-- ✅ Solution: Use HTML entities or escape -->
<code>Use &#123;user.name&#125; syntax</code>
```

**Complex expressions:**
```html
<!-- ❌ Wrong: Complex logic in template -->
<div th:class="{user.roles.contains('admin') && user.active && !user.suspended ? 'admin-active' : 'regular'}">

<!-- ✅ Better: Move logic to controller/component -->
<div th:class="{user.cssClass}">
```

**Variable not found:**
```html
<!-- ❌ Common mistake: Typo in variable name -->
<span>{usr.name}</span>

<!-- ✅ Correct: Check variable name -->
<span>{user.name}</span>
```

**Boolean attributes not working:**
```html
<!-- ❌ Wrong: Using ternary with string values -->
<option th:selected="{user.role == 'admin' ? 'selected' : null}">

<!-- ✅ Correct: Use simple boolean condition -->
<option th:selected="{user.role == 'admin'}">

<!-- ❌ Wrong: Setting boolean to string -->
<input th:disabled="'disabled'">

<!-- ✅ Correct: Use boolean expression -->
<input th:disabled="{!user.canEdit}">
```

### Debugging and Troubleshooting

#### Template Errors
```php
// Enable debugging in development
$factory = new ViewFactory([
    'cache_enabled' => false,  // Disable cache for immediate updates
    'debug' => true,
]);

// Check template existence
if (!view()->exists('user.profile')) {
    throw new \Exception('Template not found');
}

// Get template paths for debugging
$paths = view()->getPaths();
var_dump($paths);
```

#### Common Issues with Universal th: Attributes

**Attribute not rendering:**
```html
<!-- ❌ Wrong: Missing quotes around brace expression -->
<div th:data-id={user.id}>Content</div>

<!-- ✅ Correct: Proper quotes -->
<div th:data-id="{user.id}">Content</div>
```

**Brace expressions in code blocks:**
```html
<!-- ❌ Problem: Braces processed in code examples -->
<code>Use {user.name} syntax</code>

<!-- ✅ Solution: Use HTML entities -->
<code>Use &#123;user.name&#125; syntax</code>

<!-- ✅ Alternative: Use <pre> tags (automatically excluded) -->
<pre>function example() { return {data: 'preserved'}; }</pre>
```

**Boolean attributes not working:**
```html
<!-- ❌ Wrong: Using ternary with string values -->
<option th:selected="{user.role == 'admin' ? 'selected' : null}">

<!-- ✅ Correct: Use simple boolean condition -->
<option th:selected="{user.role == 'admin'}">

<!-- ❌ Wrong: Setting boolean to string -->
<input th:disabled="'disabled'">

<!-- ✅ Correct: Use boolean expression -->
<input th:disabled="{!user.canEdit}">
```

**Variable not found:**
```html
<!-- ❌ Common mistake: Typo in variable name -->
<span>{usr.name}</span>

<!-- ✅ Correct: Check variable name -->
<span>{user.name}</span>

<!-- ✅ Debug: Check available variables -->
<pre th:text="var_export(get_defined_vars(), true)"></pre>
```

**Complex expressions in templates:**
```html
<!-- ❌ Wrong: Too complex for template -->
<div th:class="{user.roles.contains('admin') && user.active && !user.suspended ? 'admin-active' : 'regular'}">

<!-- ✅ Better: Move logic to controller/component -->
<div th:class="{user.cssClass}">
```

#### Performance Issues

**Template cache not working:**
```php
// Check cache configuration
$cache = view()->getCache();
if (!$cache) {
    echo "Cache not configured\n";
}

// Manual cache clear
view()->clearCache();

// Check cache directory permissions
$cachePath = '/path/to/cache';
if (!is_writable($cachePath)) {
    throw new \Exception("Cache directory not writable: {$cachePath}");
}
```

**Template compilation errors:**
```html
<!-- Check template syntax with simple test -->
<div th:if="true">Test content</div>

<!-- Validate HTML structure -->
<!-- Templates must be valid HTML for DOM parsing -->
<div>
    <p>Valid structure</p>
</div>

<!-- ❌ Invalid: Unclosed tags -->
<div><p>Invalid structure</div>
```

#### Template Cache Issues

If template changes aren't appearing:

```php
// Clear cache programmatically
view()->clearCache();

// Or delete cache files manually
// rm -rf storage/views/*.cache

// Check file modification times
$templatePath = '/path/to/template.th.html';
$cacheKey = 'view_' . md5($templatePath);
$cacheTime = cache()->get($cacheKey . '_time');
$fileTime = filemtime($templatePath);

if ($cacheTime < $fileTime) {
    echo "Template newer than cache - should recompile\n";
}
```

## Performance & Deployment

### Template Compilation
- Templates are compiled once and cached for optimal performance
- Compilation checks file modification time for automatic cache invalidation
- Support for both file-based and memory-based caching systems
- Minimal overhead for raw PHP templates (no compilation needed)

### Optimization Features
- Efficient DOM parsing using libxml for th: attribute processing
- Smart attribute processing order to minimize compilation passes
- Universal th: attributes add minimal runtime overhead
- Dot notation compilation happens once at template compile time
- Support class method calls are optimized for performance

### Production Deployment
- Enable template caching in production for best performance
- Pre-compile templates during deployment for zero cold-start time
- Cache compiled templates with proper file permissions
- Monitor cache directory disk space usage

```php
// Production configuration
$factory = new ViewFactory([
    'cache_enabled' => true,
    'cache_path' => '/var/cache/views',
    'paths' => ['/app/resources/views'],
]);

// Clear cache during deployment
$factory->clearCache();
```

### Development vs Production
- Development: Cache disabled for immediate template changes
- Production: Cache enabled with proper invalidation
- Staging: Cache enabled with shorter TTL for testing
