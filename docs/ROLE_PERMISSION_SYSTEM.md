# Role-Permission System Documentation

The TreeHouse Framework includes a comprehensive database-driven role-permission system that provides fine-grained access control for your applications.

## Overview

The system implements Role-Based Access Control (RBAC) with the following features:

- **Database-driven**: All roles and permissions are stored in the database
- **Flexible**: Support for many-to-many relationships between users/roles and roles/permissions
- **Backward compatible**: Works alongside existing simple role column
- **Middleware integration**: Easy route protection with middleware
- **Helper functions**: Convenient functions for checking permissions
- **Console commands**: Management tools for roles and permissions
- **Caching support**: Optional caching for performance

## Database Schema

The system uses four main tables:

### `roles`
- `id` - Primary key
- `name` - Unique role name (e.g., 'administrator', 'editor')
- `description` - Human-readable description
- `created_at`, `updated_at` - Timestamps

### `permissions`
- `id` - Primary key
- `name` - Unique permission name (e.g., 'manage-users', 'edit-posts')
- `description` - Human-readable description
- `category` - Permission category for organization
- `created_at`, `updated_at` - Timestamps

### `role_permissions`
- `id` - Primary key
- `role_id` - Foreign key to roles table
- `permission_id` - Foreign key to permissions table
- `created_at` - Timestamp

### `user_roles`
- `id` - Primary key
- `user_id` - Foreign key to users table
- `role_id` - Foreign key to roles table
- `created_at` - Timestamp

## Installation

### 1. Run Migrations

```bash
php console migrate:run
```

This will create the required tables and populate them with default roles and permissions.

### 2. Default Roles

The system comes with four default roles:

- **administrator** - Full system access (all permissions)
- **editor** - Content management permissions
- **author** - Content creation permissions
- **member** - Basic user permissions

### 3. Default Permissions

31 default permissions across 7 categories:

**Users**: manage-users, create-users, edit-users, delete-users, view-users
**Content**: create-posts, edit-posts, delete-posts, publish-posts, view-posts
**System**: manage-system, access-admin, manage-settings, view-logs
**Profile**: edit-profile, change-password, upload-avatar, view-profile
**Comments**: create-comments, edit-comments, delete-comments, moderate-comments
**Files**: upload-files, delete-files, manage-files
**Reports**: view-reports, export-reports, create-reports

## Usage

### User Model Integration

Update your User model to implement the `Authorizable` contract:

```php
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

class User extends Model implements Authorizable
{
    // The User model already includes the necessary methods
    // when you extend the framework's base User model
}
```

### Middleware Usage

Protect routes with role or permission middleware:

```php
// Require admin role
$router->get('/admin', [AdminController::class, 'index'])
    ->middleware('role:administrator');

// Require any of multiple roles
$router->get('/editor', [EditorController::class, 'index'])
    ->middleware('role:administrator,editor');

// Require specific permission
$router->get('/users', [UserController::class, 'index'])
    ->middleware('permission:manage-users');

// Require any of multiple permissions
$router->post('/posts', [PostController::class, 'store'])
    ->middleware('permission:create-posts,edit-posts');
```

### Helper Functions

Use convenient helper functions in your code:

```php
// Check roles
if (hasRole('administrator')) {
    // User is admin
}

if (hasAnyRole(['administrator', 'editor'])) {
    // User has admin or editor role
}

// Check permissions
if (hasPermission('manage-users')) {
    // User can manage users
}

if (hasAnyPermission(['create-posts', 'edit-posts'])) {
    // User can create or edit posts
}

// Convenience functions
if (isAdmin()) {
    // User is administrator
}

if (canManageUsers()) {
    // User has any user management permission
}

// Require functions (throw exception if not authorized)
requireRole('administrator');
requirePermission('manage-users');
requireAnyRole(['administrator', 'editor']);
```

### Model Methods

Use methods directly on user instances:

```php
$user = User::find(1);

// Check roles
$user->hasRole('administrator');
$user->hasAnyRole(['admin', 'editor']);

// Check permissions
$user->can('manage-users');
$user->cannot('delete-posts');

// Get user's roles and permissions
$roles = $user->roles();
$permissions = $user->permissions();
```

### Console Commands

Manage roles and permissions via console:

```bash
# List all roles
php console role list

# Create a new role
php console role create editor

# Show role details
php console role show administrator

# Assign permissions to role
php console role assign editor --permissions=edit-posts,delete-posts

# Revoke permissions from role
php console role revoke editor --permissions=delete-posts

# Delete a role
php console role delete old-role
```

## Configuration

Configure the system in `config/permissions.php`:

```php
return [
    // Cache duration in seconds (0 to disable)
    'cache_duration' => 3600,
    
    // Super admin role (bypasses all checks)
    'super_admin_role' => 'administrator',
    
    // Default role for new users
    'default_role' => 'member',
    
    // Permission categories
    'categories' => [
        'users' => 'User Management',
        'content' => 'Content Management',
        // ...
    ],
    
    // Middleware settings
    'middleware' => [
        'guest_redirect' => '/login',
        'forbidden_view' => 'errors.403',
    ],
];
```

## Advanced Usage

### Custom Permissions

Add custom permissions via database:

```php
// Create custom permission
$connection = app('database');
$connection->insert(
    'INSERT INTO permissions (name, description, category, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
    ['custom-action', 'Perform custom action', 'custom']
);

// Assign to role
$roleId = $connection->selectOne('SELECT id FROM roles WHERE name = ?', ['editor'])['id'];
$permissionId = $connection->selectOne('SELECT id FROM permissions WHERE name = ?', ['custom-action'])['id'];

$connection->insert(
    'INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())',
    [$roleId, $permissionId]
);
```

### User Role Assignment

Assign roles to users:

```php
// Assign role to user
$userId = 1;
$roleId = $connection->selectOne('SELECT id FROM roles WHERE name = ?', ['editor'])['id'];

$connection->insert(
    'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())',
    [$userId, $roleId]
);
```

### Caching

The system supports caching for better performance. User permissions are cached based on the `cache_duration` setting.

### Backward Compatibility

The system maintains backward compatibility with the existing `role` column in the users table. Users will have permissions from both their database roles and their legacy role column.

## Security Considerations

1. **Super Admin**: The configured super admin role bypasses all permission checks
2. **Database Security**: Ensure proper database access controls
3. **Cache Invalidation**: User permissions are cached - consider cache invalidation on role changes
4. **Input Validation**: Always validate role/permission names from user input

## Testing

Run the included test to verify your setup:

```bash
php tests/RolePermissionTest.php
```

This will check:
- Database schema
- Default data
- Model functionality
- Configuration

## Troubleshooting

### Common Issues

1. **Middleware not working**: Ensure middleware is properly registered in your router
2. **Permissions not found**: Check that migrations have been run
3. **User not authenticated**: Ensure user authentication is working
4. **Cache issues**: Clear cache or disable caching during development

### Debug Mode

Enable verbose output in middleware by checking request headers or adding debug logging.

## Examples

### Blog System

```php
// Routes
$router->group(['middleware' => 'role:administrator,editor'], function($router) {
    $router->get('/admin/posts', [PostController::class, 'index']);
    $router->post('/admin/posts', [PostController::class, 'store'])
        ->middleware('permission:create-posts');
    $router->delete('/admin/posts/{id}', [PostController::class, 'destroy'])
        ->middleware('permission:delete-posts');
});

// Controller
class PostController 
{
    public function store(Request $request)
    {
        requirePermission('create-posts');
        
        // Create post logic
    }
    
    public function destroy($id)
    {
        $post = Post::find($id);
        
        // Authors can only delete their own posts
        if (!hasRole('administrator') && $post->author_id !== auth()->id()) {
            throw new Exception('Cannot delete other users posts');
        }
        
        $post->delete();
    }
}
```

### E-commerce System

```php
// Different permission levels
if (hasPermission('manage-orders')) {
    // Can view all orders
} elseif (hasPermission('view-orders')) {
    // Can view own orders only
}

// Product management
if (canManageContent()) {
    // Show product management interface
}
```

This role-permission system provides a robust foundation for access control in your TreeHouse applications while remaining flexible and easy to use.