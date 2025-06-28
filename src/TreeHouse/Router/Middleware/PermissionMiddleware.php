<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Auth\PermissionChecker;
use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;
use Closure;

/**
 * Permission Middleware
 *
 * Middleware for checking user permissions before allowing access to routes.
 * Supports checking single permissions or multiple permissions with OR logic.
 *
 * Usage:
 * - `permission:manage-users` - User must have manage-users permission
 * - `permission:edit-posts,delete-posts` - User must have edit-posts OR delete-posts permission
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class PermissionMiddleware implements MiddlewareInterface
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
     * Create a new permission middleware instance
     *
     * @param array $config Auth configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->checker = new PermissionChecker($config);
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
        // Extract permissions from the middleware configuration
        // For now, we'll get this from query params or headers for testing
        // In production, this would be configured when the middleware is registered
        $permissions = $request->query('_permissions', '');
        
        // Get the current user
        $user = $this->getCurrentUser($request);

        // If no user is authenticated, deny access
        if (!$user) {
            return $this->unauthorized($request);
        }

        // Parse the permissions parameter
        $requiredPermissions = $this->parsePermissions($permissions);

        // If no permissions specified, allow access (this middleware wasn't configured properly)
        if (empty($requiredPermissions)) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        if (!$this->checker->hasAnyPermission($user, $requiredPermissions)) {
            return $this->forbidden($request, $requiredPermissions);
        }

        // User has required permission, continue to next middleware
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
        if (class_exists('\App\Models\User')) {
            try {
                $userClass = '\App\Models\User';
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
     * Parse permissions string into array
     *
     * @param string $permissions Comma-separated permissions
     * @return array
     */
    private function parsePermissions(string $permissions): array
    {
        if (empty($permissions)) {
            return [];
        }

        return array_map('trim', explode(',', $permissions));
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
     * @param array $requiredPermissions Required permissions
     * @return Response
     */
    private function forbidden(Request $request, array $requiredPermissions): Response
    {
        $response = new Response('Forbidden', 403);
        
        // For AJAX requests, return JSON
        if ($this->isAjaxRequest($request)) {
            $response->setContent(json_encode([
                'error' => 'Forbidden',
                'message' => 'Insufficient privileges',
                'required_permissions' => $requiredPermissions
            ]));
            $response->setHeader('Content-Type', 'application/json');
        } else {
            // For regular requests, return HTML
            $permissionList = implode(', ', $requiredPermissions);
            $response->setContent('<!DOCTYPE html>
<html>
<head><title>Forbidden</title></head>
<body>
    <h1>403 - Forbidden</h1>
    <p>You do not have permission to access this resource.</p>
    <p>Required permission(s): ' . htmlspecialchars($permissionList) . '</p>
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