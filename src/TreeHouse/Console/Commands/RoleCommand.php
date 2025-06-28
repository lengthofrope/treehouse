<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Console\Commands;

use LengthOfRope\TreeHouse\Console\Command;
use LengthOfRope\TreeHouse\Console\Input\InputInterface;
use LengthOfRope\TreeHouse\Console\Output\OutputInterface;
use LengthOfRope\TreeHouse\Console\InputArgument;
use LengthOfRope\TreeHouse\Console\InputOption;
use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Support\Env;

/**
 * Role Management Command
 *
 * Console command for managing roles in the database-driven permission system.
 * Provides functionality to create, list, delete, and manage role permissions.
 *
 * @package LengthOfRope\TreeHouse\Console\Commands
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RoleCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('role')
            ->setDescription('Manage roles and their permissions')
            ->setHelp('This command allows you to manage roles and their permissions.')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (list, create, delete, show, assign, revoke)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Role name')
            ->addOption('permissions', null, InputOption::VALUE_OPTIONAL, 'Comma-separated permission names');
    }

    /**
     * Execute the command
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');

        return match ($action) {
            'list' => $this->listRoles($input, $output),
            'create' => $this->createRole($input, $output),
            'delete' => $this->deleteRole($input, $output),
            'show' => $this->showRole($input, $output),
            'assign' => $this->assignPermissions($input, $output),
            'revoke' => $this->revokePermissions($input, $output),
            default => $this->showHelp($output),
        };
    }

    /**
     * List all roles
     */
    private function listRoles(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        $roles = $connection->select('SELECT * FROM roles ORDER BY name');

        if (empty($roles)) {
            $this->info($output, 'No roles found.');
            return 0;
        }

        $this->info($output, 'Available roles:');
        $this->line($output, '');

        foreach ($roles as $role) {
            $permissionCount = $connection->selectOne(
                'SELECT COUNT(*) as count FROM role_permissions WHERE role_id = ?',
                [$role['id']]
            )['count'];

            $this->line($output, sprintf(
                '  <comment>%s</comment> - %s (%d permissions)',
                $role['name'],
                $role['description'] ?? 'No description',
                $permissionCount
            ));
        }

        return 0;
    }

    /**
     * Create a new role
     */
    private function createRole(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        $name = $input->getArgument('name');
        if (!$name) {
            $name = $this->ask($output, 'Role name');
        }

        if (!$name) {
            $this->error($output, 'Role name is required.');
            return 1;
        }

        // Check if role already exists
        $existing = $connection->selectOne(
            'SELECT id FROM roles WHERE name = ?',
            [$name]
        );

        if ($existing) {
            $this->error($output, "Role '{$name}' already exists.");
            return 1;
        }

        $description = $this->ask($output, 'Role description (optional)');

        // Generate slug from name
        $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));

        // Create the role
        $connection->insert(
            'INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$name, $slug, $description, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        $this->info($output, "Role '{$name}' created successfully.");

        // Ask if they want to assign permissions
        if ($this->confirm($output, 'Would you like to assign permissions to this role?')) {
            return $this->assignPermissionsToRole($name, $input, $output);
        }

        return 0;
    }


    /**
     * Delete a role
     */
    private function deleteRole(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        $name = $input->getArgument('name');
        if (!$name) {
            $name = $this->ask($output, 'Role name to delete');
        }

        if (!$name) {
            $this->error($output, 'Role name is required.');
            return 1;
        }

        // Check if role exists
        $role = $connection->selectOne(
            'SELECT id FROM roles WHERE name = ?',
            [$name]
        );

        if (!$role) {
            $this->error($output, "Role '{$name}' not found.");
            return 1;
        }

        // Check if role is assigned to users
        $userCount = $connection->selectOne(
            'SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?',
            [$role['id']]
        )['count'];

        if ($userCount > 0) {
            if (!$this->confirm($output, "Role '{$name}' is assigned to {$userCount} user(s). Continue?")) {
                $this->info($output, 'Operation cancelled.');
                return 0;
            }
        }

        // Delete role permissions first
        $connection->delete(
            'DELETE FROM role_permissions WHERE role_id = ?',
            [$role['id']]
        );

        // Delete user role assignments
        $connection->delete(
            'DELETE FROM user_roles WHERE role_id = ?',
            [$role['id']]
        );

        // Delete the role
        $connection->delete(
            'DELETE FROM roles WHERE id = ?',
            [$role['id']]
        );

        $this->info($output, "Role '{$name}' deleted successfully.");
        return 0;
    }

    /**
     * Show role details
     */
    private function showRole(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        $name = $input->getArgument('name');
        if (!$name) {
            $name = $this->ask($output, 'Role name');
        }

        if (!$name) {
            $this->error($output, 'Role name is required.');
            return 1;
        }

        // Get role details
        $role = $connection->selectOne(
            'SELECT * FROM roles WHERE name = ?',
            [$name]
        );

        if (!$role) {
            $this->error($output, "Role '{$name}' not found.");
            return 1;
        }

        // Get role permissions
        $permissions = $connection->select(
            'SELECT p.name, p.description, p.category
             FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.category, p.name',
            [$role['id']]
        );

        // Get user count
        $userCount = $connection->selectOne(
            'SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?',
            [$role['id']]
        )['count'];

        $this->info($output, "Role: {$role['name']}");
        $this->line($output, "Description: " . ($role['description'] ?? 'No description'));
        $this->line($output, "Users: {$userCount}");
        $this->line($output, "Created: {$role['created_at']}");
        $this->line($output, '');

        if (empty($permissions)) {
            $this->line($output, 'No permissions assigned.');
        } else {
            $this->info($output, 'Permissions:');
            $groupedPermissions = [];
            foreach ($permissions as $permission) {
                $groupedPermissions[$permission['category']][] = $permission;
            }

            foreach ($groupedPermissions as $category => $categoryPermissions) {
                $this->line($output, "  <comment>{$category}:</comment>");
                foreach ($categoryPermissions as $permission) {
                    $this->line($output, "    - {$permission['name']} ({$permission['description']})");
                }
            }
        }

        return 0;
    }

    /**
     * Assign permissions to a role
     */
    private function assignPermissions(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (!$name) {
            $name = $this->ask($output, 'Role name');
        }

        return $this->assignPermissionsToRole($name, $input, $output);
    }

    /**
     * Assign permissions to a specific role
     */
    private function assignPermissionsToRole(string $roleName, InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        
        // Get role
        $role = $connection->selectOne(
            'SELECT id FROM roles WHERE name = ?',
            [$roleName]
        );

        if (!$role) {
            $this->error($output, "Role '{$roleName}' not found.");
            return 1;
        }

        // Get permissions from option or ask
        $permissionsInput = $input->getOption('permissions');
        if (!$permissionsInput) {
            $this->info($output, 'Available permissions:');
            $permissions = $connection->select('SELECT name, description, category FROM permissions ORDER BY category, name');
            
            $groupedPermissions = [];
            foreach ($permissions as $permission) {
                $groupedPermissions[$permission['category']][] = $permission;
            }

            foreach ($groupedPermissions as $category => $categoryPermissions) {
                $this->line($output, "  <comment>{$category}:</comment>");
                foreach ($categoryPermissions as $permission) {
                    $this->line($output, "    - {$permission['name']} ({$permission['description']})");
                }
            }

            $permissionsInput = $this->ask($output, 'Enter permission names (comma-separated)');
        }

        if (!$permissionsInput) {
            $this->error($output, 'No permissions specified.');
            return 1;
        }

        $permissionNames = array_map('trim', explode(',', $permissionsInput));
        $assigned = 0;

        foreach ($permissionNames as $permissionName) {
            // Get permission ID
            $permission = $connection->selectOne(
                'SELECT id FROM permissions WHERE name = ?',
                [$permissionName]
            );

            if (!$permission) {
                $this->warn($output, "Permission '{$permissionName}' not found. Skipping.");
                continue;
            }

            // Check if already assigned
            $existing = $connection->selectOne(
                'SELECT role_id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                [$role['id'], $permission['id']]
            );

            if ($existing) {
                $this->warn($output, "Permission '{$permissionName}' already assigned to role '{$roleName}'. Skipping.");
                continue;
            }

            // Assign permission
            $connection->insert(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$role['id'], $permission['id']]
            );

            $assigned++;
        }

        $this->info($output, "Assigned {$assigned} permission(s) to role '{$roleName}'.");
        return 0;
    }

    /**
     * Revoke permissions from a role
     */
    private function revokePermissions(InputInterface $input, OutputInterface $output): int
    {
        $connection = db();
        $name = $input->getArgument('name');
        if (!$name) {
            $name = $this->ask($output, 'Role name');
        }

        // Get role
        $role = $connection->selectOne(
            'SELECT id FROM roles WHERE name = ?',
            [$name]
        );

        if (!$role) {
            $this->error($output, "Role '{$name}' not found.");
            return 1;
        }

        // Get permissions from option or ask
        $permissionsInput = $input->getOption('permissions');
        if (!$permissionsInput) {
            // Show current permissions
            $currentPermissions = $connection->select(
                'SELECT p.name FROM permissions p
                 JOIN role_permissions rp ON p.id = rp.permission_id
                 WHERE rp.role_id = ?
                 ORDER BY p.name',
                [$role['id']]
            );

            if (empty($currentPermissions)) {
                $this->info($output, "Role '{$name}' has no permissions to revoke.");
                return 0;
            }

            $this->info($output, "Current permissions for role '{$name}':");
            foreach ($currentPermissions as $permission) {
                $this->line($output, "  - {$permission['name']}");
            }

            $permissionsInput = $this->ask($output, 'Enter permission names to revoke (comma-separated)');
        }

        if (!$permissionsInput) {
            $this->error($output, 'No permissions specified.');
            return 1;
        }

        $permissionNames = array_map('trim', explode(',', $permissionsInput));
        $revoked = 0;

        foreach ($permissionNames as $permissionName) {
            // Get permission ID
            $permission = $connection->selectOne(
                'SELECT id FROM permissions WHERE name = ?',
                [$permissionName]
            );

            if (!$permission) {
                $this->warn($output, "Permission '{$permissionName}' not found. Skipping.");
                continue;
            }

            // Revoke permission
            $deleted = $connection->delete(
                'DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                [$role['id'], $permission['id']]
            );

            if ($deleted > 0) {
                $revoked++;
            } else {
                $this->warn($output, "Permission '{$permissionName}' was not assigned to role '{$name}'. Skipping.");
            }
        }

        $this->info($output, "Revoked {$revoked} permission(s) from role '{$name}'.");
        return 0;
    }

    /**
     * Show command help
     */
    private function showHelp(OutputInterface $output): int
    {
        $this->info($output, 'Role Management Commands:');
        $this->line($output, '');
        $this->line($output, '  <comment>php console role list</comment>                    List all roles');
        $this->line($output, '  <comment>php console role create [name]</comment>          Create a new role');
        $this->line($output, '  <comment>php console role delete [name]</comment>          Delete a role');
        $this->line($output, '  <comment>php console role show [name]</comment>            Show role details');
        $this->line($output, '  <comment>php console role assign [name]</comment>          Assign permissions to role');
        $this->line($output, '  <comment>php console role revoke [name]</comment>          Revoke permissions from role');
        $this->line($output, '');
        $this->line($output, 'Options:');
        $this->line($output, '  <comment>--permissions=perm1,perm2</comment>              Comma-separated permission names');
        $this->line($output, '');
        $this->line($output, 'Examples:');
        $this->line($output, '  <comment>php console role create editor</comment>');
        $this->line($output, '  <comment>php console role assign editor --permissions=edit-posts,delete-posts</comment>');
        $this->line($output, '  <comment>php console role show administrator</comment>');

        return 0;
    }
}