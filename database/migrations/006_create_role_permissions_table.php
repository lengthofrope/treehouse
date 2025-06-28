<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Database\Migration;

/**
 * Create Role Permissions Table Migration
 * 
 * Creates the pivot table for the many-to-many relationship between roles and permissions.
 * This table defines which permissions are assigned to each role.
 */
class CreateRolePermissionsTable extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->createTable('role_permissions', function ($table) {
            $table->integer('role_id');
            $table->integer('permission_id');
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id', 'roles', 'id', 'CASCADE');
            $table->foreign('permission_id', 'permissions', 'id', 'CASCADE');
        });

        // Seed default role-permission mappings
        $this->seedRolePermissions();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->dropTable('role_permissions');
    }

    /**
     * Seed default role-permission mappings
     */
    private function seedRolePermissions(): void
    {
        // Get role IDs
        $adminRoleId = $this->getRoleId('administrator');
        $editorRoleId = $this->getRoleId('editor');
        $authorRoleId = $this->getRoleId('author');
        $memberRoleId = $this->getRoleId('member');

        // Administrator gets all permissions
        $allPermissions = $this->getAllPermissionIds();
        foreach ($allPermissions as $permissionId) {
            $this->statement(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$adminRoleId, $permissionId]
            );
        }

        // Editor permissions
        $editorPermissions = [
            'users.view', 'users.edit', 'users.ban',
            'content.view', 'content.create', 'content.edit', 'content.delete', 'content.publish', 'content.moderate',
            'comments.view', 'comments.moderate',
            'files.upload', 'files.view', 'files.manage',
            'reports.view'
        ];
        $this->assignPermissionsToRole($editorRoleId, $editorPermissions);

        // Author permissions
        $authorPermissions = [
            'profile.view', 'profile.edit',
            'content.view', 'content.create', 'content.edit', 'content.publish',
            'comments.view', 'comments.create', 'comments.edit', 'comments.delete',
            'files.upload', 'files.view', 'files.delete'
        ];
        $this->assignPermissionsToRole($authorRoleId, $authorPermissions);

        // Member permissions
        $memberPermissions = [
            'profile.view', 'profile.edit', 'profile.delete', 'profile.export',
            'content.view',
            'comments.view', 'comments.create', 'comments.edit', 'comments.delete',
            'files.upload', 'files.view', 'files.delete'
        ];
        $this->assignPermissionsToRole($memberRoleId, $memberPermissions);
    }

    /**
     * Get role ID by slug
     */
    private function getRoleId(string $slug): int
    {
        $result = $this->connection->selectOne("SELECT id FROM roles WHERE slug = ?", [$slug]);
        return (int) $result['id'];
    }

    /**
     * Get all permission IDs
     */
    private function getAllPermissionIds(): array
    {
        $results = $this->connection->select("SELECT id FROM permissions");
        return array_column($results, 'id');
    }

    /**
     * Get permission ID by slug
     */
    private function getPermissionId(string $slug): int
    {
        $result = $this->connection->selectOne("SELECT id FROM permissions WHERE slug = ?", [$slug]);
        return (int) $result['id'];
    }

    /**
     * Assign permissions to role
     */
    private function assignPermissionsToRole(int $roleId, array $permissionSlugs): void
    {
        foreach ($permissionSlugs as $slug) {
            $permissionId = $this->getPermissionId($slug);
            $this->statement(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$roleId, $permissionId]
            );
        }
    }
}