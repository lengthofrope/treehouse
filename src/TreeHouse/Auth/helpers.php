<?php

declare(strict_types=1);

use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Database\Connection;

if (!function_exists('auth')) {
    /**
     * Get the authentication manager instance
     *
     * @return \LengthOfRope\TreeHouse\Auth\AuthManager|null Authentication manager instance
     */
    function auth(): ?\LengthOfRope\TreeHouse\Auth\AuthManager
    {
        // Try to get from application container first
        if (isset($GLOBALS['app']) && method_exists($GLOBALS['app'], 'make')) {
            try {
                return $GLOBALS['app']->make('auth');
            } catch (\Exception $e) {
                // Fall through to global fallback
            }
        }
        
        // Fallback to global reference set by Application
        if (isset($GLOBALS['auth_manager'])) {
            return $GLOBALS['auth_manager'];
        }
        
        // If no auth manager is available, create a basic one for library usage
        // This allows the framework to work when used as a library without full bootstrap
        static $fallbackAuth = null;
        if ($fallbackAuth === null) {
            try {
                // Try to create a minimal auth manager using available configuration
                $config = [
                    'default' => 'web',
                    'guards' => [
                        'web' => [
                            'driver' => 'session',
                            'provider' => 'users',
                        ],
                    ],
                    'providers' => [
                        'users' => [
                            'driver' => 'database',
                            'table' => 'users',
                        ],
                    ],
                ];
                
                // Create minimal dependencies
                $session = new \LengthOfRope\TreeHouse\Http\Session([
                    'name' => 'treehouse_session',
                    'lifetime' => 7200,
                    'path' => '/',
                    'domain' => '',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                $cookie = new \LengthOfRope\TreeHouse\Http\Cookie('auth_cookie');
                $hash = new \LengthOfRope\TreeHouse\Security\Hash();
                
                $fallbackAuth = new \LengthOfRope\TreeHouse\Auth\AuthManager(
                    $config,
                    $session,
                    $cookie,
                    $hash
                );
                
                // Store it globally for future use
                $GLOBALS['auth_manager'] = $fallbackAuth;
                
            } catch (\Exception $e) {
                // If we can't create an auth manager, return null
                return null;
            }
        }
        
        return $fallbackAuth;
    }
}

if (!function_exists('db')) {
    /**
     * Get database connection from application container
     *
     * @return Connection Database connection instance
     * @throws RuntimeException If database connection cannot be established
     */
    function db(): Connection
    {
        // Try to get from web application container first
        if (isset($GLOBALS['app']) && method_exists($GLOBALS['app'], 'make')) {
            return $GLOBALS['app']->make('db');
        }
        
        // Fallback for console environment - create connection directly
        return createDatabaseConnection();
    }
}

if (!function_exists('createDatabaseConnection')) {
    /**
     * Create database connection directly from configuration
     *
     * @return Connection Database connection instance
     * @throws RuntimeException If configuration is invalid
     */
    function createDatabaseConnection(): Connection
    {
        // Load environment if needed
        if (class_exists('\LengthOfRope\TreeHouse\Support\Env')) {
            \LengthOfRope\TreeHouse\Support\Env::loadIfNeeded();
        }
        
        // Try to load from config file first
        $configPath = getcwd() . '/config/database.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $dbConfig = $config['connections'][$config['default']];
            
            return new Connection([
                'driver' => $dbConfig['driver'],
                'host' => $dbConfig['host'],
                'port' => $dbConfig['port'],
                'database' => $dbConfig['database'],
                'username' => $dbConfig['username'],
                'password' => $dbConfig['password'],
                'charset' => $dbConfig['charset'] ?? 'utf8mb4',
            ]);
        }
        
        // Fallback to environment variables
        $config = [
            'driver' => $_ENV['DB_CONNECTION'] ?? $_ENV['DB_DRIVER'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? '',
            'username' => $_ENV['DB_USERNAME'] ?? '',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ];
        
        if (empty($config['database'])) {
            throw new \RuntimeException('Database configuration not found. Please check your .env file or config/database.php');
        }
        
        return new Connection($config);
    }
}

if (!function_exists('hasRole')) {
    /**
     * Check if the current user has a specific role
     *
     * @param string $role Role name to check
     * @return bool
     */
    function hasRole(string $role): bool
    {
        $user = getCurrentUser();
        return $user ? $user->hasRole($role) : false;
    }
}

if (!function_exists('hasAnyRole')) {
    /**
     * Check if the current user has any of the given roles
     *
     * @param array $roles Array of role names
     * @return bool
     */
    function hasAnyRole(array $roles): bool
    {
        $user = getCurrentUser();
        return $user ? $user->hasAnyRole($roles) : false;
    }
}

if (!function_exists('hasAllRoles')) {
    /**
     * Check if the current user has all of the given roles
     *
     * @param array $roles Array of role names
     * @return bool
     */
    function hasAllRoles(array $roles): bool
    {
        $user = getCurrentUser();
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
}

if (!function_exists('hasPermission')) {
    /**
     * Check if the current user has a specific permission
     *
     * @param string $permission Permission name to check
     * @return bool
     */
    function hasPermission(string $permission): bool
    {
        $user = getCurrentUser();
        return $user ? $user->can($permission) : false;
    }
}

if (!function_exists('hasAnyPermission')) {
    /**
     * Check if the current user has any of the given permissions
     *
     * @param array $permissions Array of permission names
     * @return bool
     */
    function hasAnyPermission(array $permissions): bool
    {
        $user = getCurrentUser();
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
}

if (!function_exists('hasAllPermissions')) {
    /**
     * Check if the current user has all of the given permissions
     *
     * @param array $permissions Array of permission names
     * @return bool
     */
    function hasAllPermissions(array $permissions): bool
    {
        $user = getCurrentUser();
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
}

if (!function_exists('getCurrentUser')) {
    /**
     * Get the currently authenticated user
     *
     * @return Authorizable|null
     */
    function getCurrentUser(): ?Authorizable
    {
        // Try to get user from global auth helper
        if (function_exists('auth')) {
            $authManager = auth();
            if ($authManager) {
                $user = $authManager->user();
                if ($user instanceof Authorizable) {
                    return $user;
                }
            }
        }

        // Try to get from session if available
        if (isset($_SESSION['user_id'])) {
            // Try to use User model if available
            if (class_exists('\LengthOfRope\TreeHouse\Models\User')) {
                try {
                    $userClass = '\LengthOfRope\TreeHouse\Models\User';
                    $user = $userClass::find($_SESSION['user_id']);
                    if ($user instanceof Authorizable) {
                        return $user;
                    }
                } catch (\Exception $e) {
                    // Ignore errors and return null
                }
            }
        }

        return null;
    }
}

if (!function_exists('userHasRole')) {
    /**
     * Check if a specific user has a role
     *
     * @param Authorizable $user User to check
     * @param string $role Role name
     * @return bool
     */
    function userHasRole(Authorizable $user, string $role): bool
    {
        return $user->hasRole($role);
    }
}

if (!function_exists('userHasPermission')) {
    /**
     * Check if a specific user has a permission
     *
     * @param Authorizable $user User to check
     * @param string $permission Permission name
     * @return bool
     */
    function userHasPermission(Authorizable $user, string $permission): bool
    {
        return $user->can($permission);
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if the current user is an administrator
     *
     * @return bool
     */
    function isAdmin(): bool
    {
        return hasRole('administrator');
    }
}

if (!function_exists('isEditor')) {
    /**
     * Check if the current user is an editor
     *
     * @return bool
     */
    function isEditor(): bool
    {
        return hasRole('editor');
    }
}

if (!function_exists('isAuthor')) {
    /**
     * Check if the current user is an author
     *
     * @return bool
     */
    function isAuthor(): bool
    {
        return hasRole('author');
    }
}

if (!function_exists('isMember')) {
    /**
     * Check if the current user is a member
     *
     * @return bool
     */
    function isMember(): bool
    {
        return hasRole('member');
    }
}

if (!function_exists('canManageUsers')) {
    /**
     * Check if the current user can manage users
     *
     * @return bool
     */
    function canManageUsers(): bool
    {
        return hasAnyPermission(['manage-users', 'create-users', 'edit-users', 'delete-users']);
    }
}

if (!function_exists('canManageContent')) {
    /**
     * Check if the current user can manage content
     *
     * @return bool
     */
    function canManageContent(): bool
    {
        return hasAnyPermission(['create-posts', 'edit-posts', 'delete-posts', 'publish-posts']);
    }
}

if (!function_exists('canAccessAdmin')) {
    /**
     * Check if the current user can access admin areas
     *
     * @return bool
     */
    function canAccessAdmin(): bool
    {
        return hasAnyPermission(['access-admin', 'manage-system']);
    }
}

if (!function_exists('requireRole')) {
    /**
     * Require a specific role or throw an exception
     *
     * @param string $role Role name required
     * @param string $message Custom error message
     * @throws \Exception
     */
    function requireRole(string $role, string $message = 'Access denied'): void
    {
        if (!hasRole($role)) {
            throw new \Exception($message);
        }
    }
}

if (!function_exists('requirePermission')) {
    /**
     * Require a specific permission or throw an exception
     *
     * @param string $permission Permission name required
     * @param string $message Custom error message
     * @throws \Exception
     */
    function requirePermission(string $permission, string $message = 'Access denied'): void
    {
        if (!hasPermission($permission)) {
            throw new \Exception($message);
        }
    }
}

if (!function_exists('requireAnyRole')) {
    /**
     * Require any of the given roles or throw an exception
     *
     * @param array $roles Array of role names
     * @param string $message Custom error message
     * @throws \Exception
     */
    function requireAnyRole(array $roles, string $message = 'Access denied'): void
    {
        if (!hasAnyRole($roles)) {
            throw new \Exception($message);
        }
    }
}

if (!function_exists('requireAnyPermission')) {
    /**
     * Require any of the given permissions or throw an exception
     *
     * @param array $permissions Array of permission names
     * @param string $message Custom error message
     * @throws \Exception
     */
    function requireAnyPermission(array $permissions, string $message = 'Access denied'): void
    {
        if (!hasAnyPermission($permissions)) {
            throw new \Exception($message);
        }
    }
}