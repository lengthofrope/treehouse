<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Remove User Role Column Migration
 * 
 * Removes the legacy role column from the users table as the new
 * role-based access control (RBAC) system is now in place.
 * This migration should only be run after ensuring all users
 * have been migrated to the new role system.
 */
class RemoveUserRoleColumn extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        // First, migrate any remaining users with legacy roles to the new system
        $this->migrateRemainingLegacyRoles();
        
        // Remove the role column and its index
        $this->table('users', function($table) {
            $table->dropIndex('idx_users_role');
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        // Re-add the role column
        $this->table('users', function($table) {
            $table->string('role', 50)->default('member');
            $table->index(['role'], 'idx_users_role');
        });
        
        // Populate the role column from the new role system
        $this->populateRoleColumnFromNewSystem();
    }
    
    /**
     * Migrate any remaining users with legacy roles to the new system
     */
    private function migrateRemainingLegacyRoles(): void
    {
        // Get all users who have a role in the legacy column but no roles in the new system
        $usersToMigrate = $this->connection->select("
            SELECT u.id, u.role as legacy_role
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            WHERE u.role IS NOT NULL
            AND u.role != ''
            AND ur.user_id IS NULL
        ");
        
        foreach ($usersToMigrate as $user) {
            $roleSlug = $this->mapLegacyRoleToSlug($user['legacy_role']);
            
            // Find the role in the new system
            $role = $this->connection->selectOne("SELECT id FROM roles WHERE slug = ?", [$roleSlug]);
            
            if ($role) {
                // Assign the role in the new system
                $this->connection->statement(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$user['id'], $role['id']]
                );
            }
        }
    }
    
    /**
     * Populate the role column from the new role system (for rollback)
     */
    private function populateRoleColumnFromNewSystem(): void
    {
        // Get users with their primary role from the new system
        $usersWithRoles = $this->connection->select("
            SELECT u.id, r.slug
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            ORDER BY u.id, r.id
        ");
        
        $processedUsers = [];
        foreach ($usersWithRoles as $userRole) {
            // Only set the first role found for each user
            if (!in_array($userRole['id'], $processedUsers)) {
                $legacyRole = $this->mapSlugToLegacyRole($userRole['slug']);
                $this->connection->statement(
                    "UPDATE users SET role = ? WHERE id = ?",
                    [$legacyRole, $userRole['id']]
                );
                $processedUsers[] = $userRole['id'];
            }
        }
    }
    
    /**
     * Map legacy role names to new role slugs
     */
    private function mapLegacyRoleToSlug(string $legacyRole): string
    {
        $mapping = [
            'admin' => 'admin',
            'editor' => 'editor',
            'viewer' => 'author',  // viewer maps to author in new system
            'author' => 'author',
            'member' => 'member',
        ];
        
        return $mapping[$legacyRole] ?? 'member';
    }
    
    /**
     * Map new role slugs to legacy role names
     */
    private function mapSlugToLegacyRole(string $slug): string
    {
        $mapping = [
            'admin' => 'admin',
            'editor' => 'editor',
            'author' => 'viewer',  // author maps back to viewer for legacy
            'member' => 'viewer',  // member maps to viewer for legacy
        ];
        
        return $mapping[$slug] ?? 'viewer';
    }
}