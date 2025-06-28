<?php

declare(strict_types=1);

/**
 * Permission System Configuration
 * 
 * Configuration for the database-driven role-permission system.
 * This file defines caching settings, default roles, and permission categories.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Permission Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache user permissions and role mappings.
    | Set to 0 to disable caching.
    |
    */
    'cache_duration' => 3600, // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    |
    | The role that has all permissions automatically.
    | Users with this role bypass all permission checks.
    |
    */
    'super_admin_role' => 'administrator',

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | The default role assigned to new users.
    | This role should exist in the roles table.
    |
    */
    'default_role' => 'member',

    /*
    |--------------------------------------------------------------------------
    | Permission Categories
    |--------------------------------------------------------------------------
    |
    | Categories for organizing permissions in the admin interface.
    | These are used for grouping permissions logically.
    |
    */
    'categories' => [
        'users' => 'User Management',
        'content' => 'Content Management',
        'system' => 'System Administration',
        'profile' => 'Profile Management',
        'comments' => 'Comments & Reviews',
        'files' => 'File Management',
        'reports' => 'Reports & Analytics',
        'analytics' => 'Analytics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for permission cache keys to avoid conflicts.
    |
    */
    'cache_prefix' => 'permissions:',

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Table names for the permission system.
    | Change these if you need to use different table names.
    |
    */
    'tables' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        'user_roles' => 'user_roles',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for permission middleware behavior.
    |
    */
    'middleware' => [
        // Redirect guests to login page
        'guest_redirect' => '/login',
        
        // Show 403 page for authenticated users without permission
        'forbidden_view' => 'errors.403',
        
        // Allow multiple roles/permissions (OR logic)
        'allow_multiple' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Hierarchy
    |--------------------------------------------------------------------------
    |
    | Define role hierarchy for inheritance.
    | Higher roles inherit permissions from lower roles.
    |
    */
    'hierarchy' => [
        'administrator' => ['editor', 'author', 'member'],
        'editor' => ['author', 'member'],
        'author' => ['member'],
        'member' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Wildcards
    |--------------------------------------------------------------------------
    |
    | Enable wildcard permissions like 'users.*' to grant all user permissions.
    |
    */
    'wildcards' => true,

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging of permission changes for audit trails.
    |
    */
    'audit_logging' => false,
];