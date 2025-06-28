<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Auth\PermissionChecker;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
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
     * Create a new role middleware instance
     *
     * @param array $config Auth configuration
     */
    public function __construct(...$args)
    {
        // Handle both old array config and new parameter-based config
        if (count($args) === 1 && is_array($args[0])) {
            // Old style: config array
            $this->config = $args[0];
        } else {
            // New style: roles as parameters (e.g., 'admin', 'editor')
            $this->config = [];
            if (!empty($args)) {
                $this->config['required_roles'] = $args;
            }
        }
        
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

        // Try to get user ID from session cookie
        $sessionId = $request->cookie('session_id');
        if ($sessionId) {
            $userId = $this->getUserIdFromSession($sessionId);
            if ($userId) {
                return $this->findUserById($userId);
            }
        }

        // Try to get user ID from Authorization header (Bearer token)
        $authHeader = $request->header('authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $userId = $this->getUserIdFromToken($token);
            if ($userId) {
                return $this->findUserById($userId);
            }
        }

        return null;
    }

    /**
     * Get user ID from session
     *
     * @param string $sessionId Session ID
     * @return mixed|null
     */
    private function getUserIdFromSession(string $sessionId): mixed
    {
        // This is a simplified implementation
        // In a real application, you'd look up the session in a session store
        // For now, just return null - this would need integration with session system
        return null;
    }

    /**
     * Get user ID from token
     *
     * @param string $token Bearer token
     * @return mixed|null
     */
    private function getUserIdFromToken(string $token): mixed
    {
        // This is a simplified implementation
        // In a real application, you'd validate and decode a JWT or API token
        // For now, just return null - this would need integration with token system
        return null;
    }

    /**
     * Find user by ID
     *
     * @param mixed $userId User ID
     * @return Authorizable|null
     */
    private function findUserById(mixed $userId): ?Authorizable
    {
        // Try to use User model if available
        if (class_exists('\LengthOfRope\TreeHouse\Models\User')) {
            try {
                $userClass = '\LengthOfRope\TreeHouse\Models\User';
                $user = $userClass::find($userId);
                if ($user instanceof Authorizable) {
                    return $user;
                }
            } catch (\Exception $e) {
                // Ignore errors and return null
            }
        }

        return null;
    }

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
        
        // For AJAX requests, return JSON
        if ($this->isAjaxRequest($request)) {
            $response->setContent(json_encode([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ]));
            $response->setHeader('Content-Type', 'application/json');
        } else {
            // For regular requests, you might want to redirect to login
            // For now, just return a simple response
            $response->setContent('<!DOCTYPE html>
<html>
<head><title>Unauthorized</title></head>
<body>
    <h1>401 - Unauthorized</h1>
    <p>You must be logged in to access this resource.</p>
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
        
        // For AJAX requests, return JSON
        if ($this->isAjaxRequest($request)) {
            $response->setContent(json_encode([
                'error' => 'Forbidden',
                'message' => 'Insufficient privileges',
                'required_roles' => $requiredRoles
            ]));
            $response->setHeader('Content-Type', 'application/json');
        } else {
            // For regular requests, return HTML
            $roleList = implode(', ', $requiredRoles);
            $response->setContent('<!DOCTYPE html>
<html>
<head><title>Forbidden</title></head>
<body>
    <h1>403 - Forbidden</h1>
    <p>You do not have permission to access this resource.</p>
    <p>Required role(s): ' . htmlspecialchars($roleList) . '</p>
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
}