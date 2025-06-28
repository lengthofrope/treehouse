<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

/**
 * Base Policy Class
 *
 * Base class for creating resource-specific authorization policies.
 * Policies provide a way to organize authorization logic around specific models or resources.
 *
 * Example usage:
 * ```php
 * class PostPolicy extends Policy
 * {
 *     public function view(Authorizable $user, Post $post): bool
 *     {
 *         return $user->can('view-posts') || $post->author_id === $user->id;
 *     }
 *     
 *     public function update(Authorizable $user, Post $post): bool
 *     {
 *         return $user->hasRole('admin') || $post->author_id === $user->id;
 *     }
 * }
 * ```
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
abstract class Policy
{
    /**
     * Determine if the given ability should be granted for the current user
     *
     * This method can be overridden in child classes to provide
     * common authorization logic that applies to all abilities.
     *
     * @param Authorizable $user The user being authorized
     * @param string $ability The ability being checked
     * @return bool|null Return true to allow, false to deny, null to continue checking
     */
    public function before(Authorizable $user, string $ability): ?bool
    {
        // Check if user is admin and has all permissions
        if ($user->hasRole('admin')) {
            return true;
        }

        return null; // Continue to specific ability checks
    }

    /**
     * Handle dynamic calls to policy methods
     *
     * This allows policies to handle abilities that don't have explicit methods
     * by falling back to role/permission checks.
     *
     * @param string $method Method name (ability)
     * @param array $arguments Method arguments
     * @return bool
     */
    public function __call(string $method, array $arguments): bool
    {
        // First argument should be the user
        $user = $arguments[0] ?? null;

        if (!$user instanceof Authorizable) {
            return false;
        }

        // Check if user can perform this ability through role-based permissions
        return $user->can($method);
    }

    /**
     * Deny access with a specific message
     *
     * Helper method for creating explicit denials in policies.
     *
     * @param string|null $message Optional denial message
     * @return bool Always returns false
     */
    protected function deny(?string $message = null): bool
    {
        // In future versions, we could store the message for display
        return false;
    }

    /**
     * Allow access
     *
     * Helper method for creating explicit approvals in policies.
     *
     * @return bool Always returns true
     */
    protected function allow(): bool
    {
        return true;
    }

    /**
     * Check if the user owns the resource
     *
     * Helper method for common ownership checks.
     *
     * @param Authorizable $user The user to check
     * @param mixed $resource The resource being accessed
     * @param string $ownerField The field that contains the owner ID (default: 'user_id')
     * @return bool
     */
    protected function isOwner(Authorizable $user, mixed $resource, string $ownerField = 'user_id'): bool
    {
        if (!is_object($resource)) {
            return false;
        }

        // Try different ways to get the owner ID
        $ownerId = null;

        if (isset($resource->{$ownerField})) {
            $ownerId = $resource->{$ownerField};
        } elseif (method_exists($resource, 'getOwnerId')) {
            $ownerId = $resource->getOwnerId();
        } elseif (isset($resource->author_id)) {
            $ownerId = $resource->author_id;
        }

        return $ownerId && $user->getAuthIdentifier() == $ownerId;
    }

    /**
     * Check if the user has any of the given roles
     *
     * Helper method for role-based authorization in policies.
     *
     * @param Authorizable $user The user to check
     * @param array $roles Array of role names
     * @return bool
     */
    protected function hasAnyRole(Authorizable $user, array $roles): bool
    {
        return $user->hasAnyRole($roles);
    }

    /**
     * Check if the user has a specific role
     *
     * Helper method for role-based authorization in policies.
     *
     * @param Authorizable $user The user to check
     * @param string $role Role name
     * @return bool
     */
    protected function hasRole(Authorizable $user, string $role): bool
    {
        return $user->hasRole($role);
    }

    /**
     * Check if the user can perform a permission
     *
     * Helper method for permission-based authorization in policies.
     *
     * @param Authorizable $user The user to check
     * @param string $permission Permission name
     * @return bool
     */
    protected function can(Authorizable $user, string $permission): bool
    {
        return $user->can($permission);
    }
}