<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers;

use LengthOfRope\TreeHouse\Http\Request;

/**
 * User-based Key Resolver
 *
 * Resolves rate limiting keys based on authenticated users.
 * Falls back to IP-based resolution for unauthenticated requests.
 * 
 * This resolver allows for different rate limits for authenticated
 * vs anonymous users, and enables per-user rate limiting.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class UserKeyResolver implements KeyResolverInterface
{
    /**
     * Resolver configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new user key resolver
     *
     * @param array<string, mixed> $config Resolver configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Resolve the rate limiting key for a request
     *
     * @param Request $request HTTP request
     * @return string|null Rate limiting key or null if cannot be resolved
     */
    public function resolveKey(Request $request): ?string
    {
        // Try to get authenticated user
        $user = $this->getAuthenticatedUser($request);
        
        if ($user !== null) {
            $userId = $this->getUserIdentifier($user);
            if ($userId !== null) {
                $prefix = $this->config['user_prefix'];
                return "{$prefix}:{$userId}";
            }
        }
        
        // Fall back to IP-based resolution if configured
        if ($this->config['fallback_to_ip']) {
            $ip = $this->getClientIp($request);
            if ($ip !== null) {
                $prefix = $this->config['ip_prefix'];
                return "{$prefix}:{$ip}";
            }
        }
        
        return null;
    }

    /**
     * Get the resolver name
     */
    public function getName(): string
    {
        return 'user';
    }

    /**
     * Check if this resolver can handle the request
     *
     * @param Request $request HTTP request
     * @return bool True if this resolver can generate a key for the request
     */
    public function canResolve(Request $request): bool
    {
        // Can always resolve - either to user ID or IP (if fallback enabled)
        if ($this->getAuthenticatedUser($request) !== null) {
            return true;
        }
        
        // Check if IP fallback is enabled and available
        return $this->config['fallback_to_ip'] && $this->getClientIp($request) !== null;
    }

    /**
     * Get default configuration
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'user_prefix' => 'user',
            'ip_prefix' => 'ip',
            'fallback_to_ip' => true,
            'user_id_field' => 'id',
            'auth_helper' => 'auth', // Name of global auth helper function
        ];
    }

    /**
     * Set resolver configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get authenticated user from request
     *
     * @param Request $request HTTP request
     * @return mixed|null User object or null if not authenticated
     */
    private function getAuthenticatedUser(Request $request): mixed
    {
        // Try using global auth helper function
        $authHelper = $this->config['auth_helper'];
        if (function_exists($authHelper)) {
            try {
                $authManager = $authHelper();
                if ($authManager && method_exists($authManager, 'user')) {
                    return $authManager->user();
                }
            } catch (\Exception $e) {
                // Silently continue to next method
            }
        }
        
        // Try getting from global app container
        if (isset($GLOBALS['app'])) {
            try {
                $authManager = $GLOBALS['app']->make('auth');
                if ($authManager && method_exists($authManager, 'user')) {
                    return $authManager->user();
                }
            } catch (\Exception $e) {
                // Silently continue to next method
            }
        }
        
        // Try getting from session (basic session-based auth)
        if (session_status() === PHP_SESSION_ACTIVE || session_start()) {
            if (isset($_SESSION['user_id'])) {
                return ['id' => $_SESSION['user_id']];
            }
            if (isset($_SESSION['user'])) {
                return $_SESSION['user'];
            }
        }
        
        return null;
    }

    /**
     * Get user identifier from user object
     *
     * @param mixed $user User object
     * @return string|null User identifier
     */
    private function getUserIdentifier(mixed $user): ?string
    {
        if ($user === null) {
            return null;
        }
        
        $idField = $this->config['user_id_field'];
        
        // Handle array user
        if (is_array($user)) {
            return isset($user[$idField]) ? (string) $user[$idField] : null;
        }
        
        // Handle object user with getter method
        if (is_object($user)) {
            // Try getId() method
            if (method_exists($user, 'getId')) {
                $id = $user->getId();
                return $id !== null ? (string) $id : null;
            }
            
            // Try configured field name as method
            $getter = 'get' . ucfirst($idField);
            if (method_exists($user, $getter)) {
                $id = $user->{$getter}();
                return $id !== null ? (string) $id : null;
            }
            
            // Try property access
            if (property_exists($user, $idField)) {
                $id = $user->{$idField};
                return $id !== null ? (string) $id : null;
            }
            
            // Try magic __get method
            try {
                $id = $user->{$idField};
                return $id !== null ? (string) $id : null;
            } catch (\Exception $e) {
                // Property doesn't exist or not accessible
            }
        }
        
        // Handle scalar user ID
        if (is_scalar($user)) {
            return (string) $user;
        }
        
        return null;
    }

    /**
     * Get client IP address from request
     *
     * @param Request $request HTTP request
     * @return string|null Client IP address
     */
    private function getClientIp(Request $request): ?string
    {
        // Try TreeHouse Request ip() method first
        if (method_exists($request, 'ip')) {
            return $request->ip();
        }
        
        // Manual IP detection for fallback
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            $ip = $_SERVER[$header] ?? null;
            if (!empty($ip)) {
                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Check if user is authenticated for this request
     *
     * @param Request $request HTTP request
     * @return bool True if user is authenticated
     */
    public function isAuthenticated(Request $request): bool
    {
        return $this->getAuthenticatedUser($request) !== null;
    }

    /**
     * Get key type for the current request
     *
     * @param Request $request HTTP request
     * @return string Key type ('user' or 'ip')
     */
    public function getKeyType(Request $request): string
    {
        return $this->isAuthenticated($request) ? 'user' : 'ip';
    }

    /**
     * Get debugging information
     *
     * @param Request $request HTTP request
     * @return array<string, mixed>
     */
    public function getDebugInfo(Request $request): array
    {
        $user = $this->getAuthenticatedUser($request);
        $userId = $user ? $this->getUserIdentifier($user) : null;
        $ip = $this->getClientIp($request);
        
        return [
            'resolver' => $this->getName(),
            'is_authenticated' => $user !== null,
            'user_id' => $userId,
            'client_ip' => $ip,
            'resolved_key' => $this->resolveKey($request),
            'key_type' => $this->getKeyType($request),
        ];
    }
}