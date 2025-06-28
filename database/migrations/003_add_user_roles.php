<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Add user roles migration
 *
 * Adds role support to the users table for the authorization system.
 */
class AddUserRoles extends Migration
{
    /**
     * Run the migration
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('users', function($table) {
            $table->string('role', 50)->default('viewer');
            $table->index(['role'], 'idx_users_role');
        });

        // Update existing users to have the default role
        $this->statement("UPDATE users SET role = 'viewer' WHERE role IS NULL OR role = ''");
    }

    /**
     * Reverse the migration
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('users', function($table) {
            $table->dropIndex('idx_users_role');
            $table->dropColumn('role');
        });
    }
}