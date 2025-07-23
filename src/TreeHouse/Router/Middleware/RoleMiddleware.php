<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Auth\PermissionChecker;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use Closure;

/**
 * Role Middleware
 *
 * Middleware for checking user roles before allowing access to routes.
 * Supports checking single roles or multiple roles with OR logic.
 *
 * Usage:
 * - `role:admin` - User must have admin role
 * - `role:admin,editor` - User must have admin OR editor role
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class RoleMiddleware implements MiddlewareInterface
{
    /**
     * Permission checker instance
     */
    private PermissionChecker $checker;

    /**
     * Auth configuration
     */
    private array $config;

    /**
     * Authentication manager instance
     */
    private AuthManager $authManager;

    /**
     * Guards to try for authentication
     */
    private array $guards;

    /**
     * Create a new role middleware instance
     *
     * @param array $config Auth configuration
     */
    public function __construct(...$args)
    {
        // Parse arguments - can be roles, guards, or both
        $this->config = [];
        $this->guards = ['web']; // Default guard
        
        foreach ($args as $arg) {
            if (is_array($arg)) {
                // Config array (old style)
                $this->config = array_merge($this->config, $arg);
            } elseif (str_contains($arg, ':')) {
                // Guard specification (e.g., "auth:api,web")
                if (str_starts_with($arg, 'auth:')) {
                    $this->guards = explode(',', substr($arg, 5));
                } else {
                    // Role with guard (e.g., "admin:api")
                    [$role, $guard] = explode(':', $arg, 2);
                    $this->config['required_roles'][] = $role;
                    $this->guards = [$guard];
                }
            } else {
                // Simple role string
                $this->config['required_roles'][] = $arg;
            }
        }
        
        // Get AuthManager instance
        $app = $GLOBALS['app'] ?? null;
        if (!$app) {
            throw new \RuntimeException('Application instance not available');
        }
        $this->authManager = $app->make('auth');
        
        $this->checker = new PermissionChecker($this->config);
    }

    /**
     * Handle the request
     *
     * @param Request $request HTTP request
     * @param callable $next Next middleware
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Get the current user
        $user = $this->getCurrentUser($request);

        // If no user is authenticated, deny access
        if (!$user) {
            return $this->unauthorized($request);
        }

        // Get required roles from constructor parameters or config
        $requiredRoles = $this->config['required_roles'] ?? [];
        
        // If roles were passed via constructor, use those
        // Otherwise fall back to query params for backward compatibility
        if (empty($requiredRoles)) {
            $roles = $request->query('_roles', '');
            $requiredRoles = $this->parseRoles($roles);
        }

        // If no roles specified, this is just auth middleware - allow access
        if (empty($requiredRoles)) {
            return $next($request);
        }

        // Check if user has any of the required roles
        if (!$this->checker->hasAnyRole($user, $requiredRoles)) {
            return $this->forbidden($request, $requiredRoles);
        }

        // User has required role, continue to next middleware
        return $next($request);
    }

    /**
     * Get the currently authenticated user
     *
     * @param Request $request HTTP request
     * @return Authorizable|null
     */
    private function getCurrentUser(Request $request): ?Authorizable
    {
        // Try each configured guard for authentication
        foreach ($this->guards as $guardName) {
            try {
                $guard = $this->authManager->guard($guardName);
                
                // Set request for JWT guards
                if ($guard instanceof JwtGuard) {
                    $guard->setRequest($request);
                }
                
                if ($guard->check()) {
                    $user = $guard->user();
                    if ($user instanceof Authorizable) {
                        return $user;
                    }
                }
            } catch (\Exception $e) {
                // Continue trying other guards
                continue;
            }
        }

        return null;
    }

    // Removed old session/token methods - now using AuthManager properly

    /**
     * Parse roles string into array
     *
     * @param string $roles Comma-separated roles
     * @return array
     */
    private function parseRoles(string $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        return array_map('trim', explode(',', $roles));
    }

    /**
     * Return unauthorized response (401)
     *
     * @param Request $request HTTP request
     * @return Response
     */
    private function unauthorized(Request $request): Response
    {
        $response = new Response('Unauthorized', 401);
        
        // For AJAX/JSON requests, return JSON
        if ($this->isAjaxRequest($request)) {
            $response->setContent(json_encode([
                'error' => 'Unauthorized',
                'message' => 'Authentication required to access this resource',
                'guards_tried' => $this->guards,
                'code' => 'AUTH_REQUIRED'
            ]));
            $response->setHeader('Content-Type', 'application/json');
            
            // Add JWT authentication challenge if JWT guards are being used
            if ($this->hasJwtGuard()) {
                $response->setHeader('WWW-Authenticate', 'Bearer realm="API"');
            }
        } else {
            // For regular requests, return HTML
            $guardsList = implode(', ', $this->guards);
            $response->setContent('<!DOCTYPE html>
<html>
<head><title>Unauthorized</title></head>
<body>
    <h1>401 - Unauthorized</h1>
    <p>You must be authenticated to access this resource.</p>
    <p><small>Guards tried: ' . htmlspecialchars($guardsList) . '</small></p>
</body>
</html>');
            $response->setHeader('Content-Type', 'text/html');
        }

        return $response;
    }

    /**
     * Return forbidden response (403)
     *
     * @param Request $request HTTP request
     * @param array $requiredRoles Required roles
     * @return Response
     */
    private function forbidden(Request $request, array $requiredRoles): Response
    {
        $response = new Response('Forbidden', 403);
        
        // For AJAX/JSON requests, return JSON
        if ($this->isAjaxRequest($request)) {
            $response->setContent(json_encode([
                'error' => 'Forbidden',
                'message' => 'Insufficient role privileges to access this resource',
                'required_roles' => $requiredRoles,
                'guards_used' => $this->guards,
                'code' => 'INSUFFICIENT_ROLE'
            ]));
            $response->setHeader('Content-Type', 'application/json');
        } else {
            // For regular requests, return HTML
            $roleList = implode(', ', $requiredRoles);
            $guardsList = implode(', ', $this->guards);
            $response->setContent('<!DOCTYPE html>
<html>
<head><title>Forbidden</title></head>
<body>
    <h1>403 - Forbidden</h1>
    <p>You do not have the required role to access this resource.</p>
    <p><strong>Required role(s):</strong> ' . htmlspecialchars($roleList) . '</p>
    <p><small>Authentication guards: ' . htmlspecialchars($guardsList) . '</small></p>
</body>
</html>');
            $response->setHeader('Content-Type', 'text/html');
        }

        return $response;
    }

    /**
     * Check if the request is an AJAX request
     *
     * @param Request $request HTTP request
     * @return bool
     */
    private function isAjaxRequest(Request $request): bool
    {
        return $request->header('X-Requested-With') === 'XMLHttpRequest' ||
               $request->header('Accept') === 'application/json' ||
               str_contains($request->header('Accept', ''), 'application/json');
    }

    /**
     * Set configuration
     *
     * @param array $config Auth configuration
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->checker->setConfig($config);
    }

    /**
     * Check if any of the guards is a JWT guard
     *
     * @return bool
     */
    private function hasJwtGuard(): bool
    {
        foreach ($this->guards as $guardName) {
            try {
                $guard = $this->authManager->guard($guardName);
                if ($guard instanceof JwtGuard) {
                    return true;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Get the guards being used by this middleware
     *
     * @return array
     */
    public function getGuards(): array
    {
        return $this->guards;
    }

    /**
     * Set the guards to use for authentication
     *
     * @param array $guards Guard names
     * @return void
     */
    public function setGuards(array $guards): void
    {
        $this->guards = $guards;
    }
}