<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * Role Model
 *
 * Represents a user role with associated permissions.
 * Provides methods for managing role-permission relationships.
 */
class Role extends ActiveRecord
{
    protected string $table = 'roles';
    
    protected array $fillable = [
        'name',
        'slug',
        'description',
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

    public string $slug {
        get => (string) $this->getAttribute('slug');
        set(string $value) {
            $this->setAttribute('slug', $value);
        }
    }

    public ?string $description {
        get => $this->getAttribute('description');
        set(?string $value) {
            $this->setAttribute('description', $value);
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
     * Get permissions associated with this role
     * 
     * @return Collection
     */
    public function permissions(): Collection
    {
        $sql = "
            SELECT p.*
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ";
        
        $results = static::getConnection()->select($sql, [$this->getKey()]);
        
        $permissions = array_map(function($row) {
            return Permission::createFromData($row);
        }, $results);
        
        return new Collection($permissions, Permission::class);
    }

    /**
     * Get users with this role
     *
     * @return Collection
     */
    public function users(): Collection
    {
        $sql = "
            SELECT u.*
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
        ";
        
        $results = static::getConnection()->select($sql, [$this->getKey()]);
        
        $users = array_map(function($row) {
            return User::createFromData($row);
        }, $results);
        
        return new Collection($users, User::class);
    }

    /**
     * Check if role has a specific permission
     * 
     * @param string $permission Permission slug
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.slug = ?
        ";
        
        $result = static::getConnection()->selectOne($sql, [$this->getKey(), $permission]);
        
        return (int) $result['count'] > 0;
    }

    /**
     * Give permission to this role
     * 
     * @param string|Permission $permission Permission slug or instance
     * @return void
     */
    public function givePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('slug', $permission)->first();
            if (!$permissionModel) {
                throw new \InvalidArgumentException("Permission '{$permission}' not found");
            }
            $permission = $permissionModel;
        }

        // Check if permission is already assigned
        if ($this->hasPermission($permission->slug)) {
            return;
        }

        $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
        static::getConnection()->statement($sql, [$this->getKey(), $permission->getKey()]);
    }

    /**
     * Revoke permission from this role
     * 
     * @param string|Permission $permission Permission slug or instance
     * @return void
     */
    public function revokePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('slug', $permission)->first();
            if (!$permissionModel) {
                return; // Permission doesn't exist, nothing to revoke
            }
            $permission = $permissionModel;
        }

        $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
        static::getConnection()->statement($sql, [$this->getKey(), $permission->getKey()]);
    }

    /**
     * Sync permissions for this role
     * 
     * @param array $permissions Array of permission slugs
     * @return void
     */
    public function syncPermissions(array $permissions): void
    {
        // Remove all current permissions
        $sql = "DELETE FROM role_permissions WHERE role_id = ?";
        static::getConnection()->statement($sql, [$this->getKey()]);

        // Add new permissions
        foreach ($permissions as $permission) {
            $this->givePermission($permission);
        }
    }

    /**
     * Get role by slug
     * 
     * @param string $slug Role slug
     * @return static|null
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }
}