# Role & Permission System Implementation Plan

## Overview

This plan addresses fixing the middleware parameter handling AND implementing a complete database-driven role-permission system with sensible defaults for web applications.

## Phase 1: Database Schema Design

### Tables Required

#### 1. Roles Table
```sql
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. Permissions Table
```sql
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 3. Role-Permission Pivot Table
```sql
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

#### 4. User-Role Pivot Table
```sql
CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

### Default Roles

#### Administrator
- **Slug**: `administrator`
- **Description**: Full system access with all permissions
- **Permissions**: All permissions (`*`)

#### Editor
- **Slug**: `editor`
- **Description**: Content management and user interaction
- **Permissions**: Content management, user moderation, reporting

#### Author
- **Slug**: `author`
- **Description**: Content creator with publishing abilities
- **Permissions**: Create, edit, and publish own content, manage own files

#### Member/User
- **Slug**: `member`
- **Description**: Standard registered user
- **Permissions**: Basic user actions, profile management

### Default Permissions (Common Web Application)

#### User Management
- `users.view` - View user lists and profiles
- `users.create` - Create new users
- `users.edit` - Edit user information
- `users.delete` - Delete users
- `users.ban` - Ban/suspend users
- `users.roles` - Manage user roles

#### Content Management
- `content.view` - View content (posts, pages, etc.)
- `content.create` - Create new content
- `content.edit` - Edit existing content
- `content.delete` - Delete content
- `content.publish` - Publish/unpublish content
- `content.moderate` - Moderate user-generated content

#### System Administration
- `system.settings` - Manage system settings
- `system.maintenance` - Perform maintenance tasks
- `system.logs` - View system logs
- `system.backups` - Manage backups
- `system.cache` - Manage cache

#### Profile Management
- `profile.view` - View own profile
- `profile.edit` - Edit own profile
- `profile.delete` - Delete own account
- `profile.export` - Export own data

#### Comments/Reviews
- `comments.view` - View comments
- `comments.create` - Create comments
- `comments.edit` - Edit own comments
- `comments.delete` - Delete own comments
- `comments.moderate` - Moderate all comments

#### File Management
- `files.upload` - Upload files
- `files.view` - View/download files
- `files.delete` - Delete own files
- `files.manage` - Manage all files

#### Reports & Analytics
- `reports.view` - View reports
- `reports.create` - Create custom reports
- `analytics.view` - View analytics data

## Phase 2: Migration Files

### Migration Structure
1. `004_create_roles_table.php`
2. `005_create_permissions_table.php`
3. `006_create_role_permissions_table.php`
4. `007_create_user_roles_table.php`
5. `008_seed_default_roles_and_permissions.php`

## Phase 3: Updated Middleware System

### Fixed Parameter Handling
```php
// Router usage examples that will work:
$router->middleware('role:administrator,editor');
$router->middleware(['role:administrator,editor', 'permission:users.edit,content.moderate']);

// Router groups:
$router->group(['middleware' => 'permission:content.create,content.edit'], function($router) {
    // Content management routes
});

$router->group(['middleware' => ['permission:users.view', 'role:administrator']], function($router) {
    // Admin routes
});
```

### Database Integration
- PermissionChecker will query database instead of using config arrays
- Caching layer for performance
- Role and permission lookups optimized

## Phase 4: Model Classes

### Role Model
```php
class Role extends Model
{
    public function permissions() // Many-to-many relationship
    public function users() // Many-to-many relationship
    public function hasPermission(string $permission): bool
    public function givePermission(string|Permission $permission): void
    public function revokePermission(string|Permission $permission): void
}
```

### Permission Model
```php
class Permission extends Model
{
    public function roles() // Many-to-many relationship
    public static function categorized(): array // Group by category
}
```

### Updated User Model
```php
class User extends AuthorizableUser
{
    public function roles() // Many-to-many relationship
    public function hasRole(string $role): bool
    public function hasPermission(string $permission): bool
    public function hasAnyRole(array $roles): bool
    public function hasAnyPermission(array $permissions): bool
    public function assignRole(string|Role $role): void
    public function removeRole(string|Role $role): void
}
```

## Phase 5: Console Commands

### Role Management Commands
- `role:create {name} {description?}` - Create new role
- `role:delete {name}` - Delete role
- `role:list` - List all roles
- `role:permissions {role}` - Show role permissions

### Permission Management Commands
- `permission:create {name} {description?} {category?}` - Create permission
- `permission:delete {name}` - Delete permission
- `permission:list` - List all permissions
- `permission:assign {role} {permission}` - Assign permission to role

### User Management Commands
- `user:role {user} {role}` - Assign role to user
- `user:permissions {user}` - Show user permissions

## Phase 6: Configuration & Cache

### Permission Configuration
```php
// config/permissions.php
return [
    'cache_duration' => 3600, // 1 hour
    'super_admin_role' => 'administrator',
    'default_role' => 'member',
    'categories' => [
        'users' => 'User Management',
        'content' => 'Content Management',
        'system' => 'System Administration',
        'profile' => 'Profile Management',
        'comments' => 'Comments & Reviews',
        'files' => 'File Management',
        'reports' => 'Reports & Analytics',
    ],
];
```

### Caching Strategy
- Cache user roles and permissions
- Cache role-permission mappings
- Invalidate cache on role/permission changes

## Phase 7: Helper Functions

### Authorization Helpers
```php
function hasRole(string $role): bool
function hasPermission(string $permission): bool
function hasAnyRole(array $roles): bool
function hasAnyPermission(array $permissions): bool
function authorize(string $permission): void // Throws exception if not authorized
```

## Phase 8: Testing Strategy

### Unit Tests
- Middleware parameter parsing
- Database queries and relationships
- Permission checking logic

### Integration Tests
- Router middleware integration
- Database migrations
- Role-permission assignments

### Feature Tests
- Complete authorization flows
- Cache performance
- Command line tools

## Phase 9: Documentation Updates

### Router README Updates
- Database-driven permission examples
- Migration instructions
- Configuration options

### New Documentation Files
- `PERMISSIONS.md` - Complete permission system guide
- `MIGRATIONS.md` - Database setup instructions
- `COMMANDS.md` - Console command reference

## Detailed Role-Permission Mappings

### Administrator Role Permissions
- All permissions (`*` wildcard or all individual permissions)

### Editor Role Permissions
- `users.view`, `users.edit`, `users.ban`
- `content.view`, `content.create`, `content.edit`, `content.delete`, `content.publish`, `content.moderate`
- `comments.view`, `comments.moderate`
- `files.upload`, `files.view`, `files.manage`
- `reports.view`

### Author Role Permissions  
- `profile.view`, `profile.edit`
- `content.view`, `content.create`, `content.edit` (own content), `content.publish` (own content)
- `comments.view`, `comments.create`, `comments.edit` (own comments), `comments.delete` (own comments)
- `files.upload`, `files.view`, `files.delete` (own files)

### Member Role Permissions
- `profile.view`, `profile.edit`, `profile.delete`, `profile.export`
- `content.view`
- `comments.view`, `comments.create`, `comments.edit` (own comments), `comments.delete` (own comments)
- `files.upload` (limited), `files.view` (own files), `files.delete` (own files)

## Database Migration Files Structure

### 004_create_roles_table.php
```php
<?php
// Creates roles table with: id, name, slug, description, timestamps
// Seeds: administrator, editor, author, member
```

### 005_create_permissions_table.php  
```php
<?php
// Creates permissions table with: id, name, slug, description, category, timestamps
// Seeds: All 25+ permissions listed above categorized properly
```

### 006_create_role_permissions_table.php
```php
<?php
// Creates pivot table: role_id, permission_id
// Seeds: Complete role-permission mappings as defined above
```

### 007_create_user_roles_table.php
```php
<?php
// Creates pivot table: user_id, role_id
// Default: Assigns 'member' role to any existing users
```

### 008_seed_default_roles_and_permissions.php
```php
<?php
// Comprehensive seeder for all default data
// Includes all roles, permissions, and their relationships
```

## Implementation Order

1. **Database Migrations** - Create tables and seed data with 4 roles (administrator, editor, author, member)
2. **Model Classes** - Role, Permission, updated User model with relationship methods
3. **Updated PermissionChecker** - Database queries instead of config arrays
4. **Fixed Middleware** - Parameter handling and database integration  
5. **Console Commands** - Management tools for roles/permissions
6. **Caching Layer** - Performance optimization
7. **Tests** - Comprehensive test suite
8. **Documentation** - Updated guides and examples

## Benefits

### For Developers
- Intuitive middleware syntax that actually works
- Database-driven permissions for flexibility
- Comprehensive default permissions for common use cases
- Console commands for easy management

### For Applications
- Scalable permission system
- Performance optimized with caching
- Industry-standard role-permission patterns
- Easy to extend and customize

### For Security
- Database-driven authorization (not config files)
- Granular permission control
- Audit trail capabilities
- Secure by default

This implementation provides a complete, production-ready role-permission system that addresses the original middleware issues while adding a robust database-driven foundation for authorization.