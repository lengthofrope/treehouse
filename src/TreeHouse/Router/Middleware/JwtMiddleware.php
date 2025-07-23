<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware;

use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Http\Response;
use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Auth\JwtGuard;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;

/**
 * JWT Authentication Middleware
 *
 * Dedicated middleware for JWT-only authentication. This middleware specifically
 * uses JWT guards and provides enhanced JWT-specific error handling and responses.
 * Perfect for API endpoints that require stateless JWT authentication.
 *
 * Usage:
 * - `jwt` - Uses default JWT guard (typically 'api')
 * - `jwt:mobile` - Uses specific JWT guard
 * - `jwt:api,mobile` - Tries multiple JWT guards in order
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtMiddleware implements MiddlewareInterface
{
    /**
     * Authentication manager instance
     */
    private AuthManager $authManager;

    /**
     * JWT guard names to try for authentication
     */
    private array $guards;

    /**
     * Create a new JWT middleware instance
     *
     * @param string|array|null $guards JWT guard names to try
     */
    public function __construct(string|array|null $guards = null)
    {
        // Use global app instance
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
            // Default to common JWT guards
            $this->guards = ['api', 'mobile'];
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
        $jwtGuard = null;

        // Try each JWT guard in order
        foreach ($this->guards as $guardName) {
            try {
                $guard = $this->authManager->guard($guardName);
                
                // Ensure it's a JWT guard
                if (!$guard instanceof JwtGuard) {
                    continue;
                }
                
                // Set the request for JWT token extraction
                $guard->setRequest($request);
                
                if ($guard->check()) {
                    $authenticated = true;
                    $jwtGuard = $guard;
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

        // Add JWT-specific headers to response
        $response = $next($request);
        
        if ($jwtGuard && $response instanceof Response) {
            $this->addJwtHeaders($response, $jwtGuard);
        }

        return $response;
    }

    /**
     * Handle unauthenticated JWT request
     *
     * @param Request $request HTTP request
     * @param \Exception|null $exception Last authentication exception
     * @return Response
     */
    private function unauthenticated(Request $request, ?\Exception $exception = null): Response
    {
        // Create JWT-specific authentication exception
        $authException = new AuthenticationException(
            'JWT authentication required',
            'JWT_AUTH_REQUIRED',
            $exception
        );

        // Add JWT-specific context
        $authException->setContext([
            'guards_attempted' => $this->guards,
            'jwt_guards_available' => $this->getAvailableJwtGuards(),
            'token_sources_checked' => ['Authorization header', 'Cookie', 'Query parameter'],
            'request_uri' => $request->uri(),
            'request_method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
        ]);

        $response = new Response('JWT Authentication Required', 401);

        // Always return JSON for JWT middleware (API-focused)
        $errorData = [
            'error' => 'JWT Authentication Required',
            'message' => 'Valid JWT token required to access this resource',
            'code' => 'JWT_AUTH_REQUIRED',
            'guards_tried' => $this->guards,
        ];

        // Add debugging information in development
        if ($this->isDebugMode()) {
            $errorData['debug'] = [
                'available_jwt_guards' => $this->getAvailableJwtGuards(),
                'token_extraction_attempted' => true,
                'exception' => $exception ? $exception->getMessage() : null,
            ];
        }

        $response->setContent(json_encode($errorData, JSON_PRETTY_PRINT));
        $response->setHeader('Content-Type', 'application/json');
        
        // Add JWT authentication challenge headers
        $response->setHeader('WWW-Authenticate', 'Bearer realm="API"');
        
        // Add CORS headers for API requests
        $this->addCorsHeaders($response, $request);

        return $response;
    }

    /**
     * Add JWT-specific headers to successful responses
     *
     * @param Response $response HTTP response
     * @param JwtGuard $jwtGuard JWT guard instance
     */
    private function addJwtHeaders(Response $response, JwtGuard $jwtGuard): void
    {
        try {
            // Add token information headers
            $token = $jwtGuard->getToken();
            if ($token) {
                $response->setHeader('X-JWT-Guard', get_class($jwtGuard));
                
                // Add token expiration info if available
                if (method_exists($jwtGuard, 'getClaims')) {
                    try {
                        $claims = $jwtGuard->getClaims();
                        if ($claims && method_exists($claims, 'getExpiration')) {
                            $expiration = $claims->getExpiration();
                            if ($expiration) {
                                $response->setHeader('X-JWT-Expires', date('c', $expiration));
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore errors getting expiration
                    }
                }
            }
        } catch (\Exception $e) {
            // Don't fail the request if header addition fails
        }
    }

    /**
     * Add CORS headers for API requests
     *
     * @param Response $response HTTP response
     * @param Request $request HTTP request
     */
    private function addCorsHeaders(Response $response, Request $request): void
    {
        // Basic CORS headers for JWT API endpoints
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With');
        $response->setHeader('Access-Control-Expose-Headers', 'X-JWT-Guard, X-JWT-Expires');
        
        if ($request->method() === 'OPTIONS') {
            $response->setHeader('Access-Control-Max-Age', '86400'); // 24 hours
        }
    }

    /**
     * Get available JWT guards from auth configuration
     *
     * @return array
     */
    private function getAvailableJwtGuards(): array
    {
        try {
            $app = $GLOBALS['app'] ?? null;
            if (!$app) {
                return [];
            }

            $authConfig = $app->config('auth.guards', []);
            $jwtGuards = [];

            foreach ($authConfig as $name => $config) {
                if (($config['driver'] ?? '') === 'jwt') {
                    $jwtGuards[] = $name;
                }
            }

            return $jwtGuards;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private function isDebugMode(): bool
    {
        try {
            $app = $GLOBALS['app'] ?? null;
            if (!$app) {
                return false;
            }
            return (bool) $app->config('app.debug', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the JWT guards being used by this middleware
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

    /**
     * Get JWT guard instance for testing
     *
     * @param string $guardName Guard name
     * @return JwtGuard|null
     */
    public function getJwtGuard(string $guardName): ?JwtGuard
    {
        try {
            $guard = $this->authManager->guard($guardName);
            return $guard instanceof JwtGuard ? $guard : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}