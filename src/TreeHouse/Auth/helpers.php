<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Auth\AuthManager;

if (!function_exists('auth')) {
    /**
     * Get the auth manager instance or a specific guard
     * 
     * @param string|null $guard Guard name
     * @return AuthManager|\LengthOfRope\TreeHouse\Auth\Guard
     */
    function auth(?string $guard = null): mixed
    {
        // Get the global application instance
        global $app;
        
        if (!$app) {
            throw new RuntimeException('Application instance not available. Make sure to set global $app variable.');
        }
        
        $authManager = $app->make('auth');
        
        if ($guard !== null) {
            return $authManager->guard($guard);
        }
        
        return $authManager;
    }
}

if (!function_exists('user')) {
    /**
     * Get the currently authenticated user
     * 
     * @param string|null $guard Guard name
     * @return mixed
     */
    function user(?string $guard = null): mixed
    {
        return auth($guard)->user();
    }
}

if (!function_exists('check')) {
    /**
     * Determine if the current user is authenticated
     * 
     * @param string|null $guard Guard name
     * @return bool
     */
    function check(?string $guard = null): bool
    {
        return auth($guard)->check();
    }
}

if (!function_exists('guest')) {
    /**
     * Determine if the current user is a guest (not authenticated)
     * 
     * @param string|null $guard Guard name
     * @return bool
     */
    function guest(?string $guard = null): bool
    {
        return auth($guard)->guest();
    }
}

if (!function_exists('login')) {
    /**
     * Log a user into the application
     * 
     * @param mixed $user User instance or identifier
     * @param bool $remember Whether to remember the user
     * @param string|null $guard Guard name
     * @return void
     */
    function login(mixed $user, bool $remember = false, ?string $guard = null): void
    {
        auth($guard)->login($user, $remember);
    }
}

if (!function_exists('logout')) {
    /**
     * Log the user out of the application
     * 
     * @param string|null $guard Guard name
     * @return void
     */
    function logout(?string $guard = null): void
    {
        auth($guard)->logout();
    }
}

if (!function_exists('attempt')) {
    /**
     * Attempt to authenticate a user using the given credentials
     * 
     * @param array $credentials User credentials
     * @param bool $remember Whether to remember the user
     * @param string|null $guard Guard name
     * @return bool
     */
    function attempt(array $credentials, bool $remember = false, ?string $guard = null): bool
    {
        return auth($guard)->attempt($credentials, $remember);
    }
}