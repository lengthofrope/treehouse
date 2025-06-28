<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create User Roles Table Migration
 * 
 * Creates the pivot table for the many-to-many relationship between users and roles.
 * This table defines which roles are assigned to each user.
 */
class CreateUserRolesTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('user_roles', function ($table) {
            $table->integer('user_id');
            $table->integer('role_id');
            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id', 'users', 'id', 'CASCADE');
            $table->foreign('role_id', 'roles', 'id', 'CASCADE');
        });

        // Assign default 'member' role to existing users
        $this->assignDefaultRoleToExistingUsers();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('user_roles');
    }

    /**
     * Assign default 'member' role to existing users
     */
    private function assignDefaultRoleToExistingUsers(): void
    {
        // Get member role ID
        $memberRole = $this->connection->selectOne("SELECT id FROM roles WHERE slug = ?", ['member']);
        
        if (!$memberRole) {
            return; // No member role found, skip
        }

        $memberRoleId = (int) $memberRole['id'];

        // Get all existing users
        $users = $this->connection->select("SELECT id FROM users");

        // Assign member role to each user
        foreach ($users as $user) {
            $this->statement(
                "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                [$user['id'], $memberRoleId]
            );
        }
    }
}