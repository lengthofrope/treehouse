<?php

declare(strict_types=1);

/**
 * TreeHouse Auth Helper Functions
 *
 * Global helper functions for the authentication and authorization system.
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */

use LengthOfRope\TreeHouse\Auth\Gate;

if (!function_exists('auth')) {
    /**
     * Get the authentication manager instance
     *
     * @param string|null $guard Guard name
     * @return \LengthOfRope\TreeHouse\Auth\AuthManager|\LengthOfRope\TreeHouse\Auth\Guard|null
     */
    function auth(?string $guard = null)
    {
        static $authManager = null;
        
        if ($authManager === null) {
            // Try to get from global variable or registry if available
            if (isset($GLOBALS['auth_manager'])) {
                $authManager = $GLOBALS['auth_manager'];
            }
        }
        
        if ($authManager === null) {
            return null;
        }
        
        return $guard ? $authManager->guard($guard) : $authManager;
    }
}

if (!function_exists('gate')) {
    /**
     * Get the Gate instance
     *
     * @return \LengthOfRope\TreeHouse\Auth\Gate
     */
    function gate(): Gate
    {
        return new Gate();
    }
}

if (!function_exists('can')) {
    /**
     * Check if the current user can perform an ability
     *
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    function can(string $ability, mixed $arguments = []): bool
    {
        return Gate::allows($ability, $arguments);
    }
}

if (!function_exists('cannot')) {
    /**
     * Check if the current user cannot perform an ability
     *
     * @param string $ability The permission to check
     * @param mixed $arguments Additional arguments for the callback
     * @return bool
     */
    function cannot(string $ability, mixed $arguments = []): bool
    {
        return Gate::denies($ability, $arguments);
    }
}