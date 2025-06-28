# TreeHouse Framework Enhancement Plan
## Role-Based Authorization & Permission System Implementation

Based on the analysis of the current codebase and the TREEHOUSE_FRAMEWORK_RECOMMENDATIONS, here's a comprehensive implementation plan for the high-priority features.

### 🎯 **Implementation Overview**

We'll implement a complete role-based authorization system with 4 core components:
1. **Role-Based Middleware System** - `role:admin,editor` syntax
2. **Permission/Policy System** - `Gate` pattern for authorization
3. **User Model Extensions** - `hasRole()`, `can()` methods
4. **View Helper Integration** - `th:if="user.hasRole('admin')"` template syntax

### 📋 **Detailed Implementation Plan**

#### **Phase 1: Core Authorization Infrastructure**

##### 1.1 Create Permission/Policy System (Foundation)
```php
// New files to create:
src/TreeHouse/Auth/Gate.php              // Policy gate manager
src/TreeHouse/Auth/Policy.php            // Base policy class
src/TreeHouse/Auth/PermissionChecker.php // Permission evaluation
src/TreeHouse/Auth/Contracts/Authorizable.php // User authorization contract
```

**Features:**
- `Gate::define()` for permission registration
- `Gate::allows()` / `Gate::denies()` checks
- Policy class support for resource-based permissions
- Integration with existing `AuthManager`

**Gate Usage Examples:**
```php
// Define permissions
Gate::define('manage-users', function($user) {
    return $user->role === 'admin';
});

Gate::define('edit-post', function($user, $post) {
    return $user->id === $post->author_id || $user->role === 'admin';
});

// Check permissions
if (Gate::allows('manage-users')) {
    // User can manage users
}

if (Gate::denies('edit-post', $post)) {
    // Access denied
}
```

##### 1.2 Enhanced Auth Configuration
```php
// Enhanced config/auth.php structure:
return [
    'guards' => [...], // existing
    'providers' => [...], // existing
    
    // NEW: Role and permission configuration
    'roles' => [
        'admin' => ['*'],  // All permissions
        'editor' => ['edit-posts', 'delete-posts'],
        'viewer' => ['view-posts'],
    ],
    
    'permissions' => [
        'manage-users' => ['admin'],
        'edit-posts' => ['admin', 'editor'],
        'view-posts' => ['admin', 'editor', 'viewer'],
    ],
    
    'default_role' => 'viewer',
];
```

#### **Phase 2: User Model Extensions**

##### 2.1 Create Base User Class with Role Methods
```php
// New base class:
src/TreeHouse/Auth/AuthorizableUser.php
```

**Methods to implement:**
- `hasRole(string $role): bool`
- `hasAnyRole(array $roles): bool`
- `can(string $permission): bool`
- `cannot(string $permission): bool`
- `assignRole(string $role): void`
- `removeRole(string $role): void`

**Usage Examples:**
```php
// In controllers
if ($user->hasRole('admin')) {
    // Admin functionality
}

if ($user->can('edit-posts')) {
    // User can edit posts
}

if ($user->hasAnyRole(['admin', 'editor'])) {
    // User has at least one of these roles
}
```

##### 2.2 Database Schema Updates
```sql
-- Option 1: Single role per user
ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'viewer';

-- Option 2: Multiple roles per user (future-proof)
CREATE TABLE user_roles (
    user_id INT,
    role VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

##### 2.3 Migration File
```php
// database/migrations/003_add_user_roles.php
class AddUserRoles extends Migration
{
    public function up(): void
    {
        $this->schema->table('users', function($table) {
            $table->string('role', 50)->default('viewer');
        });
    }
    
    public function down(): void
    {
        $this->schema->table('users', function($table) {
            $table->dropColumn('role');
        });
    }
}
```

#### **Phase 3: Role-Based Middleware System**

##### 3.1 Create Role Middleware
```php
// New middleware classes:
src/TreeHouse/Router/Middleware/RoleMiddleware.php
src/TreeHouse/Router/Middleware/PermissionMiddleware.php
```

**Features:**
- `role:admin,editor` syntax support
- `permission:manage-users` syntax support
- Integration with existing `MiddlewareStack`
- Automatic middleware registration in `Router`

**Router Integration Examples:**
```php
// Route groups with role protection
$router->group(['middleware' => 'role:admin'], function($router) {
    $router->get('/admin/users', 'AdminController@users');
    $router->post('/admin/users', 'AdminController@createUser');
});

// Multiple roles
$router->group(['middleware' => 'role:admin,editor'], function($router) {
    $router->get('/posts/manage', 'PostController@manage');
});

// Permission-based protection
$router->get('/posts', 'PostController@index')
       ->middleware('permission:view-posts');

// Individual route protection
$router->delete('/posts/{id}', 'PostController@delete')
       ->middleware('permission:delete-posts');
```

##### 3.2 Middleware Registration
```php
// Enhanced Router::__construct()
public function __construct()
{
    $this->routes = new RouteCollection();
    $this->middleware = new MiddlewareStack();
    $this->groupStack = new Collection();
    
    // Register built-in middleware aliases
    $this->middlewareAliases([
        'role' => RoleMiddleware::class,
        'permission' => PermissionMiddleware::class,
    ]);
}
```

#### **Phase 4: View Helper System**

##### 4.1 Template Engine Extensions
```php
// Enhanced classes:
src/TreeHouse/View/ViewEngine.php        // Add auth context
src/TreeHouse/View/Compilers/TreeHouseCompiler.php // Add auth directives
```

**New Template Syntax:**
```html
<!-- Role-based rendering -->
<div th:if="user.hasRole('admin')">
    <a href="/admin/users">Manage Users</a>
</div>

<!-- Permission-based rendering -->
<button th:if="user.can('delete-projects')">Delete Project</button>

<!-- Authentication check -->
<div th:if="auth.check">
    <span th:text="user.name">Welcome, User</span>
</div>

<!-- Multiple role check -->
<nav th:if="user.hasAnyRole('admin', 'auditor')">
    <a href="/dashboard/projects">Projects</a>
</nav>

<!-- Inverse checks -->
<div th:if="user.cannot('manage-users')">
    <p>You don't have permission to manage users.</p>
</div>

<!-- Guest check -->
<div th:if="auth.guest">
    <a href="/login">Please log in</a>
</div>
```

##### 4.2 Global View Variables
```php
// Auto-inject auth context into all templates
$viewEngine->share([
    'auth' => $authManager,
    'user' => $authManager->user(),
    'gate' => $gate
]);
```

**Template Compiler Extensions:**
```php
// Add new directives to TreeHouseCompiler
protected function compileAuthDirectives(string $expression): string
{
    // th:if="user.hasRole('admin')" -> <?php if($user && $user->hasRole('admin')): ?>
    // th:if="user.can('edit-posts')" -> <?php if($user && $user->can('edit-posts')): ?>
    // th:if="auth.check" -> <?php if($auth->check()): ?>
}
```

#### **Phase 5: CLI User Management Commands**

##### 5.1 User Management Commands
```php
// New command classes:
src/TreeHouse/Console/Commands/UserCommands/UserCreateCommand.php
src/TreeHouse/Console/Commands/UserCommands/UserListCommand.php
src/TreeHouse/Console/Commands/UserCommands/UserActivateCommand.php
src/TreeHouse/Console/Commands/UserCommands/UserDeactivateCommand.php
src/TreeHouse/Console/Commands/UserCommands/UserRoleCommand.php
```

**Command Usage Examples:**
```bash
# Create new user
./bin/th user:create --name="John Doe" --email="john@example.com" --role="admin"
./bin/th user:create --name="Jane Editor" --email="jane@example.com" --role="editor"

# List users
./bin/th user:list
./bin/th user:list --role="admin"

# Manage user status
./bin/th user:activate john@example.com
./bin/th user:deactivate jane@example.com

# Manage roles
./bin/th user:role john@example.com admin
./bin/th user:role jane@example.com editor viewer  # Multiple roles

# User information
./bin/th user:show john@example.com
```

##### 5.2 Command Registration
```php
// Enhanced Console/Application.php
private function registerCommands(): void
{
    // Existing commands...
    $this->register(new NewProjectCommand());
    $this->register(new CacheClearCommand());
    // ... other existing commands
    
    // NEW: User management commands
    $this->register(new UserCreateCommand());
    $this->register(new UserListCommand());
    $this->register(new UserActivateCommand());
    $this->register(new UserDeactivateCommand());
    $this->register(new UserRoleCommand());
}
```

### 🔄 **Integration Flow Diagram**

```mermaid
graph TB
    A[HTTP Request] --> B[Router]
    B --> C{Middleware Check}
    C -->|role:admin| D[RoleMiddleware]
    C -->|permission:manage-users| E[PermissionMiddleware]
    D --> F[Gate::allows]
    E --> F
    F --> G{Authorized?}
    G -->|Yes| H[Controller]
    G -->|No| I[403 Forbidden]
    H --> J[View Rendering]
    J --> K{Template Directives}
    K -->|th:if="user.hasRole"| L[Role Check]
    K -->|th:if="user.can"| M[Permission Check]
    L --> N[AuthorizableUser]
    M --> N
    N --> O[Rendered View]
```

### 📁 **File Structure Overview**

```
src/TreeHouse/Auth/
├── Gate.php                 ← NEW: Permission gate manager
├── Policy.php               ← NEW: Base policy class
├── PermissionChecker.php    ← NEW: Permission evaluation logic
├── AuthorizableUser.php     ← NEW: Base user with role methods
└── Contracts/
    └── Authorizable.php     ← NEW: Authorization contract

src/TreeHouse/Router/Middleware/
├── RoleMiddleware.php       ← NEW: Role-based route protection
└── PermissionMiddleware.php ← NEW: Permission-based route protection

src/TreeHouse/Console/Commands/UserCommands/
├── UserCreateCommand.php    ← NEW: Create users via CLI
├── UserListCommand.php      ← NEW: List users via CLI
├── UserActivateCommand.php  ← NEW: Activate/deactivate users
├── UserRoleCommand.php      ← NEW: Manage user roles
└── UserShowCommand.php      ← NEW: Show user details

Enhanced Files:
├── src/TreeHouse/View/ViewEngine.php           ← Add auth context
├── src/TreeHouse/View/Compilers/TreeHouseCompiler.php ← Add directives
├── src/TreeHouse/Router/Router.php             ← Register middleware
├── src/TreeHouse/Console/Application.php       ← Register commands
├── config/auth.php                             ← Add roles/permissions
├── src/App/Models/User.php                     ← Extend AuthorizableUser
└── database/migrations/003_add_user_roles.php  ← Add role column
```

### 🎛️ **Configuration Strategy**

**Enhanced `config/auth.php`:**
```php
return [
    // Existing configuration...
    'default' => env('AUTH_GUARD', 'web'),
    'guards' => [...],
    'providers' => [...],
    'passwords' => [...],
    
    // NEW: Role definitions
    'roles' => [
        'admin' => ['*'],  // All permissions
        'editor' => ['edit-posts', 'delete-posts', 'view-posts'],
        'auditor' => ['view-posts', 'view-users'],
        'viewer' => ['view-posts'],
    ],
    
    // NEW: Permission to role mapping
    'permissions' => [
        'manage-users' => ['admin'],
        'edit-posts' => ['admin', 'editor'],
        'delete-posts' => ['admin', 'editor'],
        'view-posts' => ['admin', 'editor', 'auditor', 'viewer'],
        'view-users' => ['admin', 'auditor'],
        'view-analytics' => ['admin', 'auditor'],
    ],
    
    // NEW: Default role for new users
    'default_role' => env('AUTH_DEFAULT_ROLE', 'viewer'),
    
    // NEW: Role hierarchy (optional)
    'role_hierarchy' => [
        'admin' => ['editor', 'auditor', 'viewer'],
        'editor' => ['viewer'],
        'auditor' => ['viewer'],
    ],
];
```

### 🔧 **Implementation Details**

#### **Gate Implementation Strategy**
```php
class Gate
{
    private static array $policies = [];
    private static array $callbacks = [];
    
    public static function define(string $ability, callable $callback): void
    {
        static::$callbacks[$ability] = $callback;
    }
    
    public static function allows(string $ability, mixed $arguments = []): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Check role-based permissions first
        if ($user->can($ability)) {
            return true;
        }
        
        // Check custom policies
        if (isset(static::$callbacks[$ability])) {
            return static::$callbacks[$ability]($user, $arguments);
        }
        
        return false;
    }
}
```

#### **AuthorizableUser Implementation Strategy**
```php
trait AuthorizableUser
{
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
    
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }
    
    public function can(string $permission): bool
    {
        $config = config('auth.permissions', []);
        
        if (!isset($config[$permission])) {
            return false;
        }
        
        $allowedRoles = $config[$permission];
        
        // Check for wildcard permission
        if (in_array('*', $allowedRoles)) {
            return true;
        }
        
        return in_array($this->role, $allowedRoles);
    }
}
```

### 🚀 **Migration Strategy**

1. **Backwards Compatibility**: All existing auth functionality remains unchanged
2. **Opt-in Features**: New role/permission features are opt-in via configuration
3. **Gradual Adoption**: Applications can migrate incrementally
4. **Fallback Support**: If no roles configured, system works as before

### 💡 **Benefits Delivered**

1. **Reduced Boilerplate**: No more manual role checking in every controller
2. **Consistent API**: Same `hasRole()`/`can()` pattern everywhere
3. **Template Integration**: Clean, readable authorization in views
4. **CLI Management**: Easy user/role management without admin interface
5. **Framework-Level Security**: Authorization handled at router level
6. **Laravel-like DX**: Familiar patterns for developers

### 📋 **Implementation Priority**

1. **Phase 1**: Core authorization infrastructure (Foundation)
2. **Phase 2**: User model extensions (Building blocks)
3. **Phase 3**: Middleware system (Route protection)
4. **Phase 4**: View helpers (Template integration)
5. **Phase 5**: CLI commands (Management tools)

### 🧪 **Testing Strategy**

Each component will include comprehensive tests:

```php
// Example test structure
tests/Unit/Auth/
├── GateTest.php
├── AuthorizableUserTest.php
├── RoleMiddlewareTest.php
└── PermissionMiddlewareTest.php

tests/Feature/Auth/
├── RoleBasedRoutingTest.php
├── PermissionCheckingTest.php
└── ViewAuthorizationTest.php
```

### 📈 **Performance Considerations**

1. **Configuration Caching**: Role/permission config will be cached
2. **Lazy Loading**: User roles loaded only when needed
3. **Middleware Efficiency**: Short-circuit on permission failures
4. **Template Compilation**: Auth directives compiled to PHP for speed

This comprehensive plan transforms TreeHouse into a modern framework with robust, Laravel-inspired authorization while maintaining its lightweight philosophy and existing architecture patterns.