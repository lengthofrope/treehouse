<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Foundation\Application;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;

/**
 * Authentication Middleware
 *
 * Core authentication middleware that supports multiple guards including JWT.
 * This middleware ensures that a user is authenticated before allowing access
 * to protected routes. It supports guard selection and proper error handling.
 *
 * Usage:
 * - `auth` - Uses default guard
 * - `auth:api` - Uses specific guard (e.g., JWT guard for API)
 * - `auth:web,api` - Tries multiple guards in order
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Authentication manager instance
     */
    private AuthManager $authManager;

    /**
     * Guards to try for authentication
     */
    private array $guards;

    /**
     * Create a new authentication middleware instance
     *
     * @param string|array|null $guards Guard names to try
     */
    public function __construct(string|array|null $guards = null)
    {
        // Use global app instance (set in Application constructor)
        $app = $GLOBALS['app'] ?? null;
        if (!$app) {
            throw new \RuntimeException('Application instance not available');
        }
        $this->authManager = $app->make('auth');
        
        // Parse guards parameter
        if (is_string($guards)) {
            $this->guards = array_map('trim', explode(',', $guards));
        } elseif (is_array($guards)) {
            $this->guards = $guards;
        } else {
            $this->guards = [null]; // Use default guard
        }
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
        $authenticated = false;
        $lastException = null;

        // Try each guard in order
        foreach ($this->guards as $guard) {
            try {
                $authGuard = $this->authManager->guard($guard);
                
                if ($authGuard->check()) {
                    $authenticated = true;
                    
                    // Set the request on the guard for JWT guards that need it
                    if ($authGuard instanceof \LengthOfRope\TreeHouse\Auth\JwtGuard) {
                        $authGuard->setRequest($request);
                    }
                    
                    break;
                }
            } catch (\Exception $e) {
                $lastException = $e;
                // Continue trying other guards
                continue;
            }
        }

        if (!$authenticated) {
            return $this->unauthenticated($request, $lastException);
        }

        // User is authenticated, continue to next middleware
        return $next($request);
    }

    /**
     * Handle unauthenticated request
     *
     * @param Request $request HTTP request
     * @param \Exception|null $exception Last authentication exception
     * @return Response
     */
    private function unauthenticated(Request $request, ?\Exception $exception = null): Response
    {
        // Create authentication exception with context
        $authException = new AuthenticationException(
            'Unauthenticated access attempt',
            'AUTH_UNAUTHENTICATED',
            $exception
        );

        // Add request context
        $authException->setContext([
            'guards_attempted' => $this->guards,
            'request_uri' => $request->uri(),
            'request_method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
        ]);

        $response = new Response('Unauthenticated', 401);

        // Return appropriate response format
        if ($this->expectsJson($request)) {
            $response->setContent(json_encode([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required to access this resource',
                'guards' => $this->guards,
            ]));
            $response->setHeader('Content-Type', 'application/json');
        } else {
            $response->setContent($this->getHtmlErrorPage());
            $response->setHeader('Content-Type', 'text/html');
        }

        // Add authentication challenge headers for JWT
        if ($this->hasJwtGuard()) {
            $response->setHeader('WWW-Authenticate', 'Bearer');
        }

        return $response;
    }

    /**
     * Check if the request expects JSON response
     *
     * @param Request $request HTTP request
     * @return bool
     */
    private function expectsJson(Request $request): bool
    {
        return $request->header('X-Requested-With') === 'XMLHttpRequest' ||
               $request->header('Accept') === 'application/json' ||
               str_contains($request->header('Accept', ''), 'application/json') ||
               str_contains($request->header('Content-Type', ''), 'application/json');
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
                if ($guard instanceof \LengthOfRope\TreeHouse\Auth\JwtGuard) {
                    return true;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Get client IP address
     *
     * @param Request $request HTTP request
     * @return string
     */
    // Removed getClientIp method since Request class has ip() method

    /**
     * Get HTML error page for unauthenticated requests
     *
     * @return string
     */
    private function getHtmlErrorPage(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Required - TreeHouse</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .container {
            background: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            max-width: 500px;
            margin: 1rem;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #dc3545;
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 2rem;
            margin: 1rem 0;
            color: #495057;
        }
        .error-message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .guards-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid #007bff;
        }
        .guards-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .guards-list {
            color: #6c757d;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="error-code">401</h1>
        <h2 class="error-title">Authentication Required</h2>
        <p class="error-message">
            You must be authenticated to access this resource.
            Please log in and try again.
        </p>
        <div class="guards-info">
            <div class="guards-label">Authentication Guards Tried:</div>
            <div class="guards-list">' . implode(', ', array_map(fn($g) => $g ?? 'default', $this->guards)) . '</div>
        </div>
    </div>
</body>
</html>';
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
     * Check if this middleware is using a specific guard
     *
     * @param string $guardName Guard name to check
     * @return bool
     */
    public function usesGuard(string $guardName): bool
    {
        return in_array($guardName, $this->guards, true);
    }
}