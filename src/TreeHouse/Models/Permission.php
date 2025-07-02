<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Models;

use LengthOfRope\TreeHouse\Database\ActiveRecord;
use LengthOfRope\TreeHouse\Support\Collection;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * Permission Model
 *
 * Represents a system permission that can be assigned to roles.
 * Provides methods for managing permission relationships.
 */
class Permission extends ActiveRecord
{
    protected string $table = 'permissions';
    
    protected array $fillable = [
        'name',
        'slug',
        'description',
        'category',
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

    public ?string $category {
        get => $this->getAttribute('category');
        set(?string $value) {
            $this->setAttribute('category', $value);
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
     * Get roles that have this permission
     * 
     * @return Collection
     */
    public function roles(): Collection
    {
        $sql = "
            SELECT r.*
            FROM roles r
            INNER JOIN role_permissions rp ON r.id = rp.role_id
            WHERE rp.permission_id = ?
        ";
        
        $results = static::getConnection()->select($sql, [$this->getKey()]);
        
        $roles = array_map(function($row) {
            return Role::createFromData($row);
        }, $results);
        
        return new Collection($roles, Role::class);
    }

    /**
     * Get permissions grouped by category
     * 
     * @return array
     */
    public static function categorized(): array
    {
        $permissions = static::all();
        $categorized = [];

        foreach ($permissions as $permission) {
            $category = $permission->category ?: 'uncategorized';
            if (!isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][] = $permission;
        }

        return $categorized;
    }

    /**
     * Get permission by slug
     * 
     * @param string $slug Permission slug
     * @return static|null
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get permissions by category
     * 
     * @param string $category Category name
     * @return Collection
     */
    public static function byCategory(string $category): Collection
    {
        return static::where('category', $category);
    }

    /**
     * Get all available categories
     * 
     * @return array
     */
    public static function getCategories(): array
    {
        $sql = "SELECT DISTINCT category FROM permissions WHERE category IS NOT NULL ORDER BY category";
        $results = static::getConnection()->select($sql);
        
        return array_column($results, 'category');
    }
}