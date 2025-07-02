# TreeHouse Models

## Overview

The Models directory contains ActiveRecord model classes that represent database entities and business logic. Each model extends the base `ActiveRecord` class and provides property hooks for perfect IDE autocompletion and type safety.

## Table of Contents

- [User Model - User Management](#user-model---user-management)
- [Role Model - Role-Based Access Control](#role-model---role-based-access-control)
- [Permission Model - Permission Management](#permission-model---permission-management)
- [Property Hooks - IDE Autocompletion](#property-hooks---ide-autocompletion)

## User Model - User Management

The [`User`](User.php) model handles user authentication, role assignment, and permission checking.

### Key Features:
- **Authentication**: Login credentials and password management
- **Role Management**: Assign and manage user roles
- **Permission Checking**: Check user permissions through roles
- **Relationship Management**: Roles and permissions through pivot tables

### Core Methods:

```php
// Creating users
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Role management
$user->assignRole('admin');
$user->assignRole($roleModel);
$user->removeRole('admin');
$user->syncRoles(['admin', 'editor']);

// Permission checking
$canEdit = $user->hasPermission('edit-posts');
$canDelete = $user->can('delete-posts');
$cannotDelete = $user->cannot('delete-posts');

// Role checking
$isAdmin = $user->hasRole('admin');
$hasAnyRole = $user->hasAnyRole(['admin', 'editor']);
$hasAllRoles = $user->hasAllRoles(['admin', 'editor']);

// Relationships
$roles = $user->roles();           // Collection of Role objects
$roleNames = $user->getRole();     // String or array of role slugs
```

### Property Hooks:

```php
// Perfect IDE autocompletion with type safety
$user->id;              // int
$user->name;            // string
$user->email;           // string
$user->password;        // string
$user->remember_token;  // ?string
$user->created_at;      // ?Carbon
$user->updated_at;      // ?Carbon
```

## Role Model - Role-Based Access Control

The [`Role`](Role.php) model manages system roles and their associated permissions.

### Key Features:
- **Permission Assignment**: Give and revoke permissions to roles
- **User Assignment**: Track which users have which roles
- **Slug-Based Lookup**: Find roles by unique slug identifiers
- **Permission Synchronization**: Sync role permissions in batch

### Core Methods:

```php
// Creating roles
$role = Role::create([
    'name' => 'Administrator',
    'slug' => 'admin',
    'description' => 'Full system access'
]);

// Permission management
$role->givePermission('edit-posts');
$role->givePermission($permissionModel);
$role->revokePermission('delete-posts');
$role->syncPermissions(['edit-posts', 'create-posts']);

// Permission checking
$canEdit = $role->hasPermission('edit-posts');

// Relationships
$permissions = $role->permissions(); // Collection of Permission objects
$users = $role->users();             // Collection of User objects

// Lookup
$adminRole = Role::findBySlug('admin');
```

### Property Hooks:

```php
// Perfect IDE autocompletion with type safety
$role->id;            // int
$role->name;          // string
$role->slug;          // string
$role->description;   // ?string
$role->created_at;    // ?Carbon
$role->updated_at;    // ?Carbon
```

## Permission Model - Permission Management

The [`Permission`](Permission.php) model represents individual system permissions that can be assigned to roles.

### Key Features:
- **Categorization**: Group permissions by category
- **Role Assignment**: Track which roles have which permissions
- **Slug-Based Lookup**: Find permissions by unique slug identifiers
- **Category Management**: Organize permissions into logical groups

### Core Methods:

```php
// Creating permissions
$permission = Permission::create([
    'name' => 'Edit Posts',
    'slug' => 'edit-posts',
    'description' => 'Can create and edit blog posts',
    'category' => 'content'
]);

// Relationships
$roles = $permission->roles();         // Collection of Role objects

// Category management
$contentPerms = Permission::byCategory('content');
$categories = Permission::getCategories();
$categorized = Permission::categorized();

// Lookup
$editPerm = Permission::findBySlug('edit-posts');
```

### Property Hooks:

```php
// Perfect IDE autocompletion with type safety
$permission->id;            // int
$permission->name;          // string
$permission->slug;          // string
$permission->description;   // ?string
$permission->category;      // ?string
$permission->created_at;    // ?Carbon
$permission->updated_at;    // ?Carbon
```

## Property Hooks - IDE Autocompletion

All models use PHP 8.4 Property Hooks to provide perfect IDE autocompletion and type safety.

### Benefits:
- **Perfect autocompletion**: Type `$user->` and see all available properties
- **Type safety**: PHP enforces property types at runtime
- **Carbon integration**: Date properties return Carbon instances
- **Better performance**: No magic method overhead
- **Static analysis**: PHPStan, Psalm, etc. understand them perfectly

### Implementation Pattern:

```php
class User extends ActiveRecord
{
    // Property hooks for all database columns
    public int $id {
        get => (int) $this->getAttribute('id');
    }

    public string $name {
        get => (string) $this->getAttribute('name');
        set(string $value) {
            $this->setAttribute('name', $value);
        }
    }

    public ?Carbon $created_at {
        get => $this->getAttribute('created_at') ? Carbon::parse($this->getAttribute('created_at')) : null;
        set(?Carbon $value) {
            $this->setAttribute('created_at', $value?->format('Y-m-d H:i:s'));
        }
    }
}
```

### Usage Examples:

```php
// Type-safe property access
$user = User::find(1);
$user->name = 'John Doe';           // ✅ String enforced
$user->created_at = Carbon::now();  // ✅ Carbon enforced
$user->save();

// IDE autocompletion works perfectly
$users = User::all();
foreach ($users as $user) {
    echo $user->name;               // ✅ Perfect autocompletion
    echo $user->created_at->format('Y-m-d'); // ✅ Carbon methods available
}

// Type errors caught at runtime
$user->name = 123;                  // ❌ TypeError: Cannot assign int to string property
$user->created_at = 'invalid';     // ❌ TypeError: Cannot assign string to Carbon property
```

## Complete RBAC Example

```php
// 1. Create permissions
$editPosts = Permission::create([
    'name' => 'Edit Posts',
    'slug' => 'edit-posts',
    'category' => 'content'
]);

$deletePosts = Permission::create([
    'name' => 'Delete Posts', 
    'slug' => 'delete-posts',
    'category' => 'content'
]);

// 2. Create roles with permissions
$editor = Role::create([
    'name' => 'Editor',
    'slug' => 'editor',
    'description' => 'Can edit content'
]);

$admin = Role::create([
    'name' => 'Administrator',
    'slug' => 'admin', 
    'description' => 'Full system access'
]);

// 3. Assign permissions to roles
$editor->givePermission('edit-posts');
$admin->syncPermissions(['edit-posts', 'delete-posts']);

// 4. Create user and assign roles
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

$user->assignRole('editor');

// 5. Check permissions
$canEdit = $user->can('edit-posts');     // true
$canDelete = $user->can('delete-posts'); // false
$isEditor = $user->hasRole('editor');    // true
```

## Integration with Authentication

The models integrate seamlessly with the TreeHouse Auth system:

```php
// In your controllers
$user = auth()->user();                  // Current authenticated user
$canEdit = $user->can('edit-posts');     // Permission check
$roles = $user->roles();                 // User's roles

// In middleware
if (!$user->hasRole('admin')) {
    throw new UnauthorizedException();
}
```

## Best Practices

1. **Use property hooks** for all database columns to get perfect IDE support
2. **Use slug-based lookups** for roles and permissions instead of IDs
3. **Group permissions by category** for better organization
4. **Sync permissions in batches** when updating role permissions
5. **Check permissions, not roles** in your application logic
6. **Use descriptive permission names** that clearly indicate what they allow