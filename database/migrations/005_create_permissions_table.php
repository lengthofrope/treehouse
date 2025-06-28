<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create Permissions Table Migration
 * 
 * Creates the permissions table for the database-driven role-permission system.
 * This table stores permission definitions with names, slugs, descriptions, and categories.
 */
class CreatePermissionsTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('permissions', function ($table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->timestamps();
        });

        // Seed default permissions
        $this->seedDefaultPermissions();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('permissions');
    }

    /**
     * Seed default permissions
     */
    private function seedDefaultPermissions(): void
    {
        $permissions = [
            // User Management
            ['name' => 'View Users', 'slug' => 'users.view', 'description' => 'View user lists and profiles', 'category' => 'users'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'description' => 'Create new users', 'category' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'description' => 'Edit user information', 'category' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'description' => 'Delete users', 'category' => 'users'],
            ['name' => 'Ban Users', 'slug' => 'users.ban', 'description' => 'Ban/suspend users', 'category' => 'users'],
            ['name' => 'Manage User Roles', 'slug' => 'users.roles', 'description' => 'Manage user roles', 'category' => 'users'],

            // Content Management
            ['name' => 'View Content', 'slug' => 'content.view', 'description' => 'View content (posts, pages, etc.)', 'category' => 'content'],
            ['name' => 'Create Content', 'slug' => 'content.create', 'description' => 'Create new content', 'category' => 'content'],
            ['name' => 'Edit Content', 'slug' => 'content.edit', 'description' => 'Edit existing content', 'category' => 'content'],
            ['name' => 'Delete Content', 'slug' => 'content.delete', 'description' => 'Delete content', 'category' => 'content'],
            ['name' => 'Publish Content', 'slug' => 'content.publish', 'description' => 'Publish/unpublish content', 'category' => 'content'],
            ['name' => 'Moderate Content', 'slug' => 'content.moderate', 'description' => 'Moderate user-generated content', 'category' => 'content'],

            // System Administration
            ['name' => 'System Settings', 'slug' => 'system.settings', 'description' => 'Manage system settings', 'category' => 'system'],
            ['name' => 'System Maintenance', 'slug' => 'system.maintenance', 'description' => 'Perform maintenance tasks', 'category' => 'system'],
            ['name' => 'View System Logs', 'slug' => 'system.logs', 'description' => 'View system logs', 'category' => 'system'],
            ['name' => 'Manage Backups', 'slug' => 'system.backups', 'description' => 'Manage backups', 'category' => 'system'],
            ['name' => 'Manage Cache', 'slug' => 'system.cache', 'description' => 'Manage cache', 'category' => 'system'],

            // Profile Management
            ['name' => 'View Profile', 'slug' => 'profile.view', 'description' => 'View own profile', 'category' => 'profile'],
            ['name' => 'Edit Profile', 'slug' => 'profile.edit', 'description' => 'Edit own profile', 'category' => 'profile'],
            ['name' => 'Delete Profile', 'slug' => 'profile.delete', 'description' => 'Delete own account', 'category' => 'profile'],
            ['name' => 'Export Profile Data', 'slug' => 'profile.export', 'description' => 'Export own data', 'category' => 'profile'],

            // Comments/Reviews
            ['name' => 'View Comments', 'slug' => 'comments.view', 'description' => 'View comments', 'category' => 'comments'],
            ['name' => 'Create Comments', 'slug' => 'comments.create', 'description' => 'Create comments', 'category' => 'comments'],
            ['name' => 'Edit Comments', 'slug' => 'comments.edit', 'description' => 'Edit own comments', 'category' => 'comments'],
            ['name' => 'Delete Comments', 'slug' => 'comments.delete', 'description' => 'Delete own comments', 'category' => 'comments'],
            ['name' => 'Moderate Comments', 'slug' => 'comments.moderate', 'description' => 'Moderate all comments', 'category' => 'comments'],

            // File Management
            ['name' => 'Upload Files', 'slug' => 'files.upload', 'description' => 'Upload files', 'category' => 'files'],
            ['name' => 'View Files', 'slug' => 'files.view', 'description' => 'View/download files', 'category' => 'files'],
            ['name' => 'Delete Files', 'slug' => 'files.delete', 'description' => 'Delete own files', 'category' => 'files'],
            ['name' => 'Manage Files', 'slug' => 'files.manage', 'description' => 'Manage all files', 'category' => 'files'],

            // Reports & Analytics
            ['name' => 'View Reports', 'slug' => 'reports.view', 'description' => 'View reports', 'category' => 'reports'],
            ['name' => 'Create Reports', 'slug' => 'reports.create', 'description' => 'Create custom reports', 'category' => 'reports'],
            ['name' => 'View Analytics', 'slug' => 'analytics.view', 'description' => 'View analytics data', 'category' => 'analytics'],
        ];

        foreach ($permissions as $permission) {
            $this->statement(
                "INSERT INTO permissions (name, slug, description, category, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
                [$permission['name'], $permission['slug'], $permission['description'], $permission['category']]
            );
        }
    }
}