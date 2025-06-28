<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

/**
 * Authorizable User Trait
 *
 * Provides role and permission functionality for user models.
 * This trait implements the Authorizable contract and can be used
 * by any user model to add authorization capabilities.
 *
 * Usage:
 * ```php
 * class User extends ActiveRecord implements Authorizable
 * {
 *     use AuthorizableUser;
 *     
 *     // Your model implementation...
 * }
 * ```
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
trait AuthorizableUser
{
    /**
     * Check if the user has a specific role
     *
     * @param string $role Role name to check
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        try {
            $connection = $this->getDatabaseConnection();
            
            $query = "
                SELECT COUNT(*) as count
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = ? AND r.slug = ?
            ";
            
            $result = $connection->selectOne($query, [$this->getAuthIdentifier(), $role]);
            return ($result['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Fallback to attribute-based checking
            $userRole = $this->getRole();
            
            if (is_array($userRole)) {
                return in_array($role, $userRole);
            }
            
            return $userRole === $role;
        }
    }

    /**
     * Check if the user has any of the given roles
     *
     * @param array $roles Array of role names to check
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if the user can perform a specific permission
     *
     * This method checks permissions based on the database role-permission system
     * with fallback to config-based system for testing/compatibility
     *
     * @param string $permission Permission name to check
     * @return bool
     */
    public function can(string $permission): bool
    {
        $userRoles = $this->getRoles();
        
        // Check if any of the user's roles have this permission
        foreach ($userRoles as $role) {
            if ($this->roleHasPermission($role, $permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check permission using config-based approach (fallback)
     *
     * @param string $permission Permission name to check
     * @return bool
     */
    protected function canUsingConfig(string $permission): bool
    {
        // Get permission configuration
        $permissions = $this->getPermissionConfig();
        
        if (!isset($permissions[$permission])) {
            return false;
        }
        
        $allowedRoles = $permissions[$permission];
        
        // Handle wildcard permission (everyone can access)
        if (in_array('*', $allowedRoles)) {
            return true;
        }
        
        // Check if user's role is in the allowed roles
        $userRole = $this->getRole();
        
        if (is_array($userRole)) {
            return !empty(array_intersect($userRole, $allowedRoles));
        }
        
        return in_array($userRole, $allowedRoles);
    }

    /**
     * Check if database is available for queries
     *
     * @return bool
     */
    protected function isDatabaseAvailable(): bool
    {
        try {
            $this->getDatabaseConnection();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the permission configuration (for config-based fallback)
     *
     * @return array
     */
    protected function getPermissionConfig(): array
    {
        $config = $this->getAuthConfigFromFile();
        return $config['permissions'] ?? [];
    }

    /**
     * Check if the user cannot perform a specific permission
     *
     * @param string $permission Permission name to check
     * @return bool
     */
    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }

    /**
     * Assign a role to the user
     *
     * @param string $role Role name to assign
     * @return void
     */
    public function assignRole(string $role): void
    {
        try {
            $connection = $this->getDatabaseConnection();
            
            // First, get the role ID
            $roleQuery = "SELECT id FROM roles WHERE slug = ?";
            $roleResult = $connection->selectOne($roleQuery, [$role]);
            
            if (!$roleResult) {
                throw new \InvalidArgumentException("Role '{$role}' does not exist");
            }
            
            $roleId = $roleResult['id'];
            $userId = $this->getAuthIdentifier();
            
            // Check if user already has this role
            $existingQuery = "SELECT COUNT(*) as count FROM user_roles WHERE user_id = ? AND role_id = ?";
            $existingResult = $connection->selectOne($existingQuery, [$userId, $roleId]);
            
            if (($existingResult['count'] ?? 0) == 0) {
                // Insert the user-role relationship
                $connection->insert("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
            }
        } catch (\Exception $e) {
            // Fallback to attribute-based assignment
            $currentRole = $this->getRole();
            
            if (is_array($currentRole)) {
                if (!in_array($role, $currentRole)) {
                    $currentRole[] = $role;
                    $this->setRole($currentRole);
                }
            } else {
                // For single role systems, replace the current role
                $this->setRole($role);
            }
            
            // Save the changes if the model supports it
            if (method_exists($this, 'save')) {
                $this->save();
            }
        }
    }

    /**
     * Remove a role from the user
     *
     * @param string $role Role name to remove
     * @return void
     */
    public function removeRole(string $role): void
    {
        try {
            $connection = $this->getDatabaseConnection();
            
            // Get the role ID
            $roleQuery = "SELECT id FROM roles WHERE slug = ?";
            $roleResult = $connection->selectOne($roleQuery, [$role]);
            
            if ($roleResult) {
                $roleId = $roleResult['id'];
                $userId = $this->getAuthIdentifier();
                
                // Remove the user-role relationship
                $connection->delete("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?", [$userId, $roleId]);
                
                // Check if user has any roles left, if not assign default role
                $remainingRolesQuery = "SELECT COUNT(*) as count FROM user_roles WHERE user_id = ?";
                $remainingResult = $connection->selectOne($remainingRolesQuery, [$userId]);
                
                if (($remainingResult['count'] ?? 0) == 0) {
                    $this->assignRole($this->getDefaultRole());
                }
            }
        } catch (\Exception $e) {
            // Fallback to attribute-based removal
            $currentRole = $this->getRole();
            
            if (is_array($currentRole)) {
                $newRoles = array_filter($currentRole, fn($r) => $r !== $role);
                $this->setRole(array_values($newRoles));
            } else {
                // For single role systems, set to default role
                $defaultRole = $this->getDefaultRole();
                $this->setRole($defaultRole);
            }
            
            // Save the changes if the model supports it
            if (method_exists($this, 'save')) {
                $this->save();
            }
        }
    }

    /**
     * Get the user's current role(s)
     *
     * @return string|array
     */
    public function getRole(): string|array
    {
        // Default implementation assumes a 'role' attribute
        return $this->role ?? $this->getDefaultRole();
    }

    /**
     * Get the unique identifier for the user
     *
     * @return mixed
     */
    public function getAuthIdentifier(): mixed
    {
        // Default implementation assumes an 'id' attribute
        return $this->id ?? null;
    }

    /**
     * Set the user's role(s)
     *
     * @param string|array $role Role(s) to set
     * @return void
     */
    protected function setRole(string|array $role): void
    {
        $this->role = $role;
    }

    /**
     * Get the default role for users
     *
     * @return string
     */
    protected function getDefaultRole(): string
    {
        $config = $this->getAuthConfigFromFile();
        return $config['default_role'] ?? 'member';
    }

    /**
     * Check if a role has a specific permission (database query)
     *
     * @param string $role Role slug
     * @param string $permission Permission slug
     * @return bool
     */
    protected function roleHasPermission(string $role, string $permission): bool
    {
        try {
            $connection = $this->getDatabaseConnection();
            
            $query = "
                SELECT COUNT(*) as count
                FROM role_permissions rp
                JOIN roles r ON r.id = rp.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE r.slug = ? AND p.slug = ?
            ";
            
            $result = $connection->selectOne($query, [$role, $permission]);
            return ($result['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Fallback to false if database query fails
            return false;
        }
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
     * Get the auth configuration
     *
     * This method attempts to get configuration using various methods
     * depending on the framework setup.
     *
     * @return array
     */
    protected function getAuthConfigFromFile(): array
    {
        // Try to get config through static file inclusion
        $configPath = getcwd() . '/config/auth.php';
        if (file_exists($configPath)) {
            $config = include $configPath;
            return is_array($config) ? $config : [];
        }
        
        // Try alternative config path (relative to framework)
        $altConfigPath = __DIR__ . '/../../../config/auth.php';
        if (file_exists($altConfigPath)) {
            $config = include $altConfigPath;
            return is_array($config) ? $config : [];
        }
        
        // Fallback to empty config
        return [];
    }

    /**
     * Check if the user has all the given roles
     *
     * @param array $roles Array of role names to check
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get all roles assigned to the user
     *
     * @return array
     */
    public function getRoles(): array
    {
        try {
            $connection = $this->getDatabaseConnection();
            
            $query = "
                SELECT r.slug
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ";
            
            $results = $connection->select($query, [$this->getAuthIdentifier()]);
            return array_column($results, 'slug');
        } catch (\Exception $e) {
            // Fallback to attribute-based roles
            $role = $this->getRole();
            
            if (is_array($role)) {
                return $role;
            }
            
            return [$role];
        }
    }

    /**
     * Check if the user has a role that inherits from the given role
     *
     * This method checks role hierarchy if configured.
     *
     * @param string $role Role name to check
     * @return bool
     */
    public function hasRoleOrHigher(string $role): bool
    {
        if ($this->hasRole($role)) {
            return true;
        }
        
        $hierarchy = $this->getRoleHierarchy();
        $userRoles = $this->getRoles();
        
        foreach ($userRoles as $userRole) {
            if (isset($hierarchy[$userRole]) && in_array($role, $hierarchy[$userRole])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the role hierarchy configuration
     *
     * @return array
     */
    protected function getRoleHierarchy(): array
    {
        $config = $this->getAuthConfigFromFile();
        return $config['role_hierarchy'] ?? [];
    }
}