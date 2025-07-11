<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * User Model
 */
class User extends ActiveRecord implements Authorizable
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];
    
    protected array $hidden = [
        'password',
    ];

    // Property hooks for perfect IDE autocompletion
    public int $id {
        get => (int) $this->getAttribute('id');
    }

    public string $name {
        get => (string) $this->getAttribute('name');
        set(string $value) {
            $this->setAttribute('name', $value);
        }
    }

    public string $email {
        get => (string) $this->getAttribute('email');
        set(string $value) {
            $this->setAttribute('email', $value);
        }
    }

    public string $password {
        get => (string) $this->getAttribute('password');
        set(string $value) {
            $this->setAttribute('password', $value);
        }
    }

    public ?string $remember_token {
        get => $this->getAttribute('remember_token');
        set(?string $value) {
            $this->setAttribute('remember_token', $value);
        }
    }

    public ?string $email_verified_at {
        get => $this->getAttribute('email_verified_at');
        set(?string $value) {
            $this->setAttribute('email_verified_at', $value);
        }
    }

    public ?Carbon $created_at {
        get => $this->getAttribute('created_at') ? Carbon::parse($this->getAttribute('created_at')) : null;
        set(?Carbon $value) {
            $this->setAttribute('created_at', $value?->format('Y-m-d H:i:s'));
        }
    }

    public ?Carbon $updated_at {
        get => $this->getAttribute('updated_at') ? Carbon::parse($this->getAttribute('updated_at')) : null;
        set(?Carbon $value) {
            $this->setAttribute('updated_at', $value?->format('Y-m-d H:i:s'));
        }
    }

    /**
     * Get roles associated with this user
     *
     * @return Collection
     */
    public function roles(): Collection
    {
        $sql = "
            SELECT r.*
            FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ";
        
        $results = static::getConnection()->select($sql, [$this->getKey()]);
        
        $roles = array_map(function($row) {
            return Role::createFromData($row);
        }, $results);
        
        return new Collection($roles);
    }

    /**
     * Check if user has a specific role
     *
     * @param string $role Role slug
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        // Check database-driven roles first
        $sql = "
            SELECT COUNT(*) as count
            FROM user_roles ur
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.slug = ?
        ";
        
        $result = static::getConnection()->selectOne($sql, [$this->getKey(), $role]);
        
        if ((int) $result['count'] > 0) {
            return true;
        }

        // No fallback needed - all users should use the new role system
        return false;
    }

    /**
     * Check if user has any of the given roles
     *
     * @param array $roles Array of role slugs
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
     * Check if user has all of the given roles
     *
     * @param array $roles Array of role slugs
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
     * Check if user has a specific permission
     *
     * @param string $permission Permission slug
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM user_roles ur
            INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.slug = ?
        ";
        
        $result = static::getConnection()->selectOne($sql, [$this->getKey(), $permission]);
        
        return (int) $result['count'] > 0;
    }

    /**
     * Check if user has any of the given permissions
     *
     * @param array $permissions Array of permission slugs
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     *
     * @param array $permissions Array of permission slugs
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Assign role to user
     *
     * @param string|Role $role Role slug or instance
     * @return void
     */
    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $roleModel = Role::findBySlug($role);
            if (!$roleModel) {
                throw new \InvalidArgumentException("Role '{$role}' not found");
            }
            $role = $roleModel;
        }

        // Check if role is already assigned
        if ($this->hasRole($role->slug)) {
            return;
        }

        $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
        static::getConnection()->statement($sql, [$this->getKey(), $role->getKey()]);
    }

    /**
     * Remove role from user
     *
     * @param string|Role $role Role slug or instance
     * @return void
     */
    public function removeRole(string|Role $role): void
    {
        if (is_string($role)) {
            $roleModel = Role::findBySlug($role);
            if (!$roleModel) {
                return; // Role doesn't exist, nothing to remove
            }
            $role = $roleModel;
        }

        $sql = "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?";
        static::getConnection()->statement($sql, [$this->getKey(), $role->getKey()]);
    }

    /**
     * Sync roles for this user
     *
     * @param array $roles Array of role slugs
     * @return void
     */
    public function syncRoles(array $roles): void
    {
        // Remove all current roles
        $sql = "DELETE FROM user_roles WHERE user_id = ?";
        static::getConnection()->statement($sql, [$this->getKey()]);

        // Add new roles
        foreach ($roles as $role) {
            $this->assignRole($role);
        }
    }

    /**
     * Get user's role (for backward compatibility)
     *
     * @return string|array
     */
    public function getRole(): string|array
    {
        $roles = $this->roles();
        
        if ($roles->isEmpty()) {
            // Default to member role if no roles assigned
            return 'member';
        }

        if ($roles->count() === 1) {
            return $roles->first()->slug;
        }

        return $roles->map(function($role) {
            return $role->slug;
        })->all();
    }

    /**
     * Get auth identifier
     *
     * @return mixed
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get auth password
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return $this->getAttribute('password');
    }

    /**
     * Get remember token
     *
     * @return string|null
     */
    public function getRememberToken(): ?string
    {
        return $this->getAttribute('remember_token');
    }

    /**
     * Set remember token
     *
     * @param string $token
     * @return void
     */
    public function setRememberToken(string $token): void
    {
        $this->setAttribute('remember_token', $token);
    }

    /**
     * Check if user can perform an action (alias for hasPermission)
     *
     * @param string $permission Permission slug
     * @return bool
     */
    public function can(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    /**
     * Check if user cannot perform an action
     *
     * @param string $permission Permission slug
     * @return bool
     */
    public function cannot(string $permission): bool
    {
        return !$this->can($permission);
    }
}