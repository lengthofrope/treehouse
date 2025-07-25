<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

/**
 * Permission Checker
 *
 * Utility class for evaluating permissions and roles.
 * Provides centralized logic for authorization decisions that can be used
 * by middleware, controllers, and other components.
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class PermissionChecker
{
    /**
     * Auth configuration
     */
    private array $config;

    /**
     * Create a new permission checker instance
     *
     * @param array $config Auth configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Check if a user has a specific permission
     *
     * @param Authorizable|null $user User to check
     * @param string $permission Permission name
     * @return bool
     */
    public function check(?Authorizable $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        return $user->can($permission);
    }

    /**
     * Check if a user has a specific role
     *
     * @param Authorizable|null $user User to check
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(?Authorizable $user, string $role): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole($role);
    }

    /**
     * Check if a user has any of the given roles
     *
     * @param Authorizable|null $user User to check
     * @param array $roles Array of role names
     * @return bool
     */
    public function hasAnyRole(?Authorizable $user, array $roles): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole($roles);
    }

    /**
     * Check if a user has all of the given roles
     *
     * @param Authorizable|null $user User to check
     * @param array $roles Array of role names
     * @return bool
     */
    public function hasAllRoles(?Authorizable $user, array $roles): bool
    {
        if (!$user) {
            return false;
        }

        foreach ($roles as $role) {
            if (!$user->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a user has any of the given permissions
     *
     * @param Authorizable|null $user User to check
     * @param array $permissions Array of permission names
     * @return bool
     */
    public function hasAnyPermission(?Authorizable $user, array $permissions): bool
    {
        if (!$user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has all of the given permissions
     *
     * @param Authorizable|null $user User to check
     * @param array $permissions Array of permission names
     * @return bool
     */
    public function hasAllPermissions(?Authorizable $user, array $permissions): bool
    {
        if (!$user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (!$user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse role/permission string for middleware
     *
     * Parses strings like "role:admin,editor" or "permission:manage-users"
     *
     * @param string $middleware Middleware string
     * @return array{type: string, values: array}
     */
    public function parseMiddlewareString(string $middleware): array
    {
        if (str_starts_with($middleware, 'role:')) {
            $roles = substr($middleware, 5);
            return [
                'type' => 'role',
                'values' => array_map('trim', explode(',', $roles))
            ];
        }

        if (str_starts_with($middleware, 'permission:')) {
            $permissions = substr($middleware, 11);
            return [
                'type' => 'permission',
                'values' => array_map('trim', explode(',', $permissions))
            ];
        }

        return [
            'type' => 'unknown',
            'values' => []
        ];
    }

    /**
     * Check middleware authorization
     *
     * @param Authorizable|null $user User to check
     * @param string $middleware Middleware string
     * @return bool
     */
    public function checkMiddleware(?Authorizable $user, string $middleware): bool
    {
        $parsed = $this->parseMiddlewareString($middleware);

        switch ($parsed['type']) {
            case 'role':
                return $this->hasAnyRole($user, $parsed['values']);
            case 'permission':
                return $this->hasAnyPermission($user, $parsed['values']);
            default:
                return false;
        }
    }

    /**
     * Get all permissions for a role
     *
     * @param string $role Role slug
     * @return array
     */
    public function getPermissionsForRole(string $role): array
    {
        // Try database approach first
        try {
            $connection = $this->getDatabaseConnection();
            
            $query = "
                SELECT p.slug
                FROM role_permissions rp
                JOIN roles r ON r.id = rp.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE r.slug = ?
            ";
            
            $results = $connection->select($query, [$role]);
            return array_column($results, 'slug');
        } catch (\Exception $e) {
            // Fallback to config-based approach
            return $this->getPermissionsForRoleFromConfig($role);
        }
    }

    /**
     * Get all available permissions
     *
     * @return array
     */
    public function getAllPermissions(): array
    {
        // Try database approach first
        try {
            $connection = $this->getDatabaseConnection();
            $results = $connection->select("SELECT slug FROM permissions ORDER BY slug");
            return array_column($results, 'slug');
        } catch (\Exception $e) {
            // Fallback to config-based approach
            $permissions = $this->config['permissions'] ?? [];
            return array_keys($permissions);
        }
    }

    /**
     * Get all available roles
     *
     * @return array
     */
    public function getAllRoles(): array
    {
        // Try database approach first
        try {
            $connection = $this->getDatabaseConnection();
            $results = $connection->select("SELECT slug FROM roles ORDER BY slug");
            return array_column($results, 'slug');
        } catch (\Exception $e) {
            // Fallback to config-based approach
            $roles = $this->config['roles'] ?? [];
            return array_keys($roles);
        }
    }

    /**
     * Check if a role exists
     *
     * @param string $role Role slug
     * @return bool
     */
    public function roleExists(string $role): bool
    {
        // Try database approach first
        try {
            $connection = $this->getDatabaseConnection();
            $result = $connection->selectOne("SELECT COUNT(*) as count FROM roles WHERE slug = ?", [$role]);
            return ($result['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Fallback to config-based approach
            $roles = $this->config['roles'] ?? [];
            return isset($roles[$role]);
        }
    }

    /**
     * Check if a permission exists
     *
     * @param string $permission Permission slug
     * @return bool
     */
    public function permissionExists(string $permission): bool
    {
        // Try database approach first
        try {
            $connection = $this->getDatabaseConnection();
            $result = $connection->selectOne("SELECT COUNT(*) as count FROM permissions WHERE slug = ?", [$permission]);
            return ($result['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Fallback to config-based approach
            $permissions = $this->config['permissions'] ?? [];
            return isset($permissions[$permission]);
        }
    }

    /**
     * Get roles that have a specific permission
     *
     * @param string $permission Permission slug
     * @return array
     */
    public function getRolesWithPermission(string $permission): array
    {
        // Try database approach first
        try {
            $connection = $this->getDatabaseConnection();
            
            $query = "
                SELECT r.slug
                FROM role_permissions rp
                JOIN roles r ON r.id = rp.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE p.slug = ?
            ";
            
            $results = $connection->select($query, [$permission]);
            return array_column($results, 'slug');
        } catch (\Exception $e) {
            // Fallback to config-based approach
            $permissions = $this->config['permissions'] ?? [];
            
            if (!isset($permissions[$permission])) {
                return [];
            }

            return $permissions[$permission];
        }
    }

    /**
     * Get permissions for role from config (fallback method)
     *
     * @param string $role Role name
     * @return array
     */
    protected function getPermissionsForRoleFromConfig(string $role): array
    {
        $roleConfig = $this->config['roles'] ?? [];
        
        if (!isset($roleConfig[$role])) {
            return [];
        }

        $rolePermissions = $roleConfig[$role];
        
        // Handle wildcard permissions
        if (in_array('*', $rolePermissions)) {
            $permissions = $this->config['permissions'] ?? [];
            return array_keys($permissions);
        }

        return $rolePermissions;
    }

    /**
     * Get database connection
     *
     * @return \LengthOfRope\TreeHouse\Database\Connection
     */
    protected function getDatabaseConnection(): \LengthOfRope\TreeHouse\Database\Connection
    {
        // Use the db() helper function which is available in the framework
        if (function_exists('db')) {
            return db();
        }
        
        throw new \RuntimeException('Database connection not available. Make sure the db() helper is loaded.');
    }

    /**
     * Set configuration
     *
     * @param array $config Auth configuration
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}