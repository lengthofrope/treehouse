<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create Roles Table Migration
 * 
 * Creates the roles table for the database-driven role-permission system.
 * This table stores role definitions with names, slugs, and descriptions.
 */
class CreateRolesTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('roles', function ($table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Seed default roles
        $this->seedDefaultRoles();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('roles');
    }

    /**
     * Seed default roles
     */
    private function seedDefaultRoles(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Full system access with all permissions'
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Content management and user interaction'
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Content creator with publishing abilities'
            ],
            [
                'name' => 'Member',
                'slug' => 'member',
                'description' => 'Standard registered user'
            ]
        ];

        foreach ($roles as $role) {
            $this->statement(
                "INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
                [$role['name'], $role['slug'], $role['description']]
            );
        }
    }
}