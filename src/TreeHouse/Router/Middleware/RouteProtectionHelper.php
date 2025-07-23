<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

/**
 * Route Protection Helper
 *
 * Utility class for easy and fluent route protection configuration.
 * Provides convenient methods to create middleware arrays for common
 * authentication and authorization scenarios.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RouteProtectionHelper
{
    /**
     * Middleware configuration array
     */
    private array $middleware = [];

    /**
     * Create a new route protection helper instance
     */
    public function __construct()
    {
        // Start with empty middleware stack
    }

    /**
     * Create a new protection helper instance
     *
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Require authentication using specified guards
     *
     * @param string|array|null $guards Guard names (e.g., 'api', ['api', 'web'])
     * @return static
     */
    public function auth(string|array|null $guards = null): static
    {
        if ($guards === null) {
            $this->middleware[] = 'auth';
        } elseif (is_string($guards)) {
            $this->middleware[] = "auth:{$guards}";
        } elseif (is_array($guards)) {
            $this->middleware[] = 'auth:' . implode(',', $guards);
        }

        return $this;
    }

    /**
     * Require JWT authentication using specified JWT guards
     *
     * @param string|array|null $guards JWT guard names (e.g., 'api', ['api', 'mobile'])
     * @return static
     */
    public function jwt(string|array|null $guards = null): static
    {
        if ($guards === null) {
            $this->middleware[] = 'jwt';
        } elseif (is_string($guards)) {
            $this->middleware[] = "jwt:{$guards}";
        } elseif (is_array($guards)) {
            $this->middleware[] = 'jwt:' . implode(',', $guards);
        }

        return $this;
    }

    /**
     * Require specific roles
     *
     * @param string|array $roles Role names (e.g., 'admin', ['admin', 'editor'])
     * @param string|array|null $guards Optional guard specification
     * @return static
     */
    public function roles(string|array $roles, string|array|null $guards = null): static
    {
        $roleStr = is_array($roles) ? implode(',', $roles) : $roles;
        
        if ($guards !== null) {
            $guardStr = is_array($guards) ? implode(',', $guards) : $guards;
            $this->middleware[] = "role:{$roleStr}:auth:{$guardStr}";
        } else {
            $this->middleware[] = "role:{$roleStr}";
        }

        return $this;
    }

    /**
     * Require specific permissions
     *
     * @param string|array $permissions Permission names (e.g., 'edit-posts', ['edit-posts', 'delete-posts'])
     * @param string|array|null $guards Optional guard specification
     * @return static
     */
    public function permissions(string|array $permissions, string|array|null $guards = null): static
    {
        $permissionStr = is_array($permissions) ? implode(',', $permissions) : $permissions;
        
        if ($guards !== null) {
            $guardStr = is_array($guards) ? implode(',', $guards) : $guards;
            $this->middleware[] = "permission:{$permissionStr}:auth:{$guardStr}";
        } else {
            $this->middleware[] = "permission:{$permissionStr}";
        }

        return $this;
    }

    /**
     * Add rate limiting
     *
     * @param int $requests Number of requests
     * @param int $minutes Time window in minutes
     * @param string|null $strategy Rate limiting strategy ('fixed', 'sliding', 'token-bucket')
     * @return static
     */
    public function throttle(int $requests, int $minutes = 1, ?string $strategy = null): static
    {
        $throttleStr = "throttle:{$requests},{$minutes}";
        
        if ($strategy !== null) {
            $throttleStr .= ",{$strategy}";
        }

        $this->middleware[] = $throttleStr;

        return $this;
    }

    /**
     * Add custom middleware
     *
     * @param string|array $middleware Middleware class names or aliases
     * @return static
     */
    public function custom(string|array $middleware): static
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * Get the middleware array
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Convert to array (alias for getMiddleware)
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getMiddleware();
    }

    /**
     * Common protection patterns
     */

    /**
     * Web authentication (session-based)
     *
     * @param string|array|null $roles Optional roles requirement
     * @param string|array|null $permissions Optional permissions requirement
     * @return static
     */
    public static function web(string|array|null $roles = null, string|array|null $permissions = null): static
    {
        $helper = static::create()->auth('web');

        if ($roles !== null) {
            $helper->roles($roles, 'web');
        }

        if ($permissions !== null) {
            $helper->permissions($permissions, 'web');
        }

        return $helper;
    }

    /**
     * API authentication (JWT-based)
     *
     * @param string|array|null $roles Optional roles requirement
     * @param string|array|null $permissions Optional permissions requirement
     * @param int|null $rateLimit Optional rate limit (requests per minute)
     * @return static
     */
    public static function api(
        string|array|null $roles = null, 
        string|array|null $permissions = null, 
        ?int $rateLimit = null
    ): static {
        $helper = static::create()->jwt('api');

        if ($roles !== null) {
            $helper->roles($roles, 'api');
        }

        if ($permissions !== null) {
            $helper->permissions($permissions, 'api');
        }

        if ($rateLimit !== null) {
            $helper->throttle($rateLimit);
        }

        return $helper;
    }

    /**
     * Admin-only protection (web + admin role)
     *
     * @param string|array $guards Guards to use (default: 'web')
     * @return static
     */
    public static function admin(string|array $guards = 'web'): static
    {
        return static::create()
            ->auth($guards)
            ->roles('admin', $guards);
    }

    /**
     * Multi-guard authentication (try multiple guards)
     *
     * @param array $guards Guard names to try in order
     * @param string|array|null $roles Optional roles requirement
     * @param string|array|null $permissions Optional permissions requirement
     * @return static
     */
    public static function multiAuth(
        array $guards, 
        string|array|null $roles = null, 
        string|array|null $permissions = null
    ): static {
        $helper = static::create()->auth($guards);

        if ($roles !== null) {
            $helper->roles($roles, $guards);
        }

        if ($permissions !== null) {
            $helper->permissions($permissions, $guards);
        }

        return $helper;
    }

    /**
     * Guest-only routes (no authentication required, but redirect if authenticated)
     *
     * @return static
     */
    public static function guest(): static
    {
        // This would need a GuestMiddleware implementation
        return static::create()->custom('guest');
    }

    /**
     * Optional authentication (user can be authenticated or not)
     *
     * @param string|array $guards Guards to try
     * @return static
     */
    public static function optional(string|array $guards = ['web', 'api']): static
    {
        // This would need an OptionalAuthMiddleware implementation
        $guardStr = is_array($guards) ? implode(',', $guards) : $guards;
        return static::create()->custom("optional:{$guardStr}");
    }

    /**
     * Debug helper - get middleware as string for inspection
     *
     * @return string
     */
    public function debug(): string
    {
        return implode(' -> ', $this->middleware);
    }

    /**
     * Check if middleware stack is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->middleware);
    }

    /**
     * Clear all middleware
     *
     * @return static
     */
    public function clear(): static
    {
        $this->middleware = [];
        return $this;
    }

    /**
     * Count middleware in stack
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->middleware);
    }

    /**
     * Magic method to convert to string for debugging
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->debug();
    }
}