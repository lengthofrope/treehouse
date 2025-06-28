<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use Closure;

/**
 * Authorization Gate
 *
 * Central authorization system for defining and checking permissions.
 * Provides a Laravel-inspired Gate pattern for authorization logic.
 *
 * Features:
 * - Define custom authorization callbacks
 * - Check permissions against authenticated users
 * - Policy-based authorization for resources
 * - Role-based permission checking
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Gate
{
    /**
     * Static storage for authorization callbacks
     *
     * @var array<string, Closure>
     */
    private static array $callbacks = [];

    /**
     * Static storage for policy classes
     *
     * @var array<string, string>
     */
    private static array $policies = [];

    /**
     * Auth manager instance for retrieving current user
     */
    private static ?AuthManager $authManager = null;

    /**
     * Set the auth manager instance
     *
     * @param AuthManager $authManager
     * @return void
     */
    public static function setAuthManager(AuthManager $authManager): void
    {
        static::$authManager = $authManager;
    }

    /**
     * Define a new authorization callback
     *
     * @param string $ability The permission name
     * @param Closure $callback Authorization callback
     * @return void
     */
    public static function define(string $ability, Closure $callback): void
    {
        static::$callbacks[$ability] = $callback;
    }

    /**
     * Define a policy class for a resource
     *
     * @param string $class Resource class name
     * @param string $policy Policy class name
     * @return void
     */
    public static function policy(string $class, string $policy): void
    {
        static::$policies[$class] = $policy;
    }

    /**
     * Check if the current user is allowed to perform an ability
     *
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    public static function allows(string $ability, mixed $arguments = []): bool
    {
        return static::check($ability, $arguments);
    }

    /**
     * Check if the current user is denied from performing an ability
     *
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    public static function denies(string $ability, mixed $arguments = []): bool
    {
        return !static::allows($ability, $arguments);
    }

    /**
     * Check authorization for a specific user
     *
     * @param Authorizable|null $user User to check (null for current user)
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    public static function forUser(?Authorizable $user, string $ability, mixed $arguments = []): bool
    {
        if (!$user) {
            return false;
        }

        return static::checkUser($user, $ability, $arguments);
    }

    /**
     * Check if any of the given abilities are allowed
     *
     * @param array $abilities Array of abilities to check
     * @param mixed $arguments Additional arguments for the callbacks
     * @return bool
     */
    public static function any(array $abilities, mixed $arguments = []): bool
    {
        foreach ($abilities as $ability) {
            if (static::allows($ability, $arguments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if none of the given abilities are allowed
     *
     * @param array $abilities Array of abilities to check
     * @param mixed $arguments Additional arguments for the callbacks
     * @return bool
     */
    public static function none(array $abilities, mixed $arguments = []): bool
    {
        return !static::any($abilities, $arguments);
    }

    /**
     * Get the currently authenticated user
     *
     * @return Authorizable|null
     */
    public static function user(): ?Authorizable
    {
        if (!static::$authManager) {
            return null;
        }

        $user = static::$authManager->user();

        return $user instanceof Authorizable ? $user : null;
    }

    /**
     * Internal method to check authorization
     *
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    private static function check(string $ability, mixed $arguments = []): bool
    {
        $user = static::user();

        if (!$user) {
            return false;
        }

        return static::checkUser($user, $ability, $arguments);
    }

    /**
     * Check authorization for a specific user
     *
     * @param Authorizable $user User to check
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    private static function checkUser(Authorizable $user, string $ability, mixed $arguments = []): bool
    {
        // First, check if the user has the permission through role-based config
        if ($user->can($ability)) {
            return true;
        }

        // Check if there's a custom callback defined
        if (isset(static::$callbacks[$ability])) {
            $callback = static::$callbacks[$ability];
            
            // Ensure arguments is always an array
            $args = is_array($arguments) ? $arguments : [$arguments];
            
            return (bool) $callback($user, ...$args);
        }

        // Check policy-based authorization if arguments contain a resource
        if (!empty($arguments) && is_object($arguments[0] ?? null)) {
            $resource = $arguments[0];
            $resourceClass = get_class($resource);

            if (isset(static::$policies[$resourceClass])) {
                return static::checkPolicy($user, $ability, $resource);
            }
        }

        return false;
    }

    /**
     * Check policy-based authorization
     *
     * @param Authorizable $user User to check
     * @param string $ability The permission to check
     * @param mixed $resource The resource being accessed
     * @return bool
     */
    private static function checkPolicy(Authorizable $user, string $ability, mixed $resource): bool
    {
        $resourceClass = get_class($resource);
        $policyClass = static::$policies[$resourceClass] ?? null;

        if (!$policyClass || !class_exists($policyClass)) {
            return false;
        }

        $policy = new $policyClass();

        if (!method_exists($policy, $ability)) {
            return false;
        }

        return (bool) $policy->{$ability}($user, $resource);
    }

    /**
     * Clear all defined callbacks and policies
     *
     * @return void
     */
    public static function flush(): void
    {
        static::$callbacks = [];
        static::$policies = [];
    }

    /**
     * Get all defined callbacks
     *
     * @return array<string, Closure>
     */
    public static function getCallbacks(): array
    {
        return static::$callbacks;
    }

    /**
     * Get all defined policies
     *
     * @return array<string, string>
     */
    public static function getPolicies(): array
    {
        return static::$policies;
    }
}