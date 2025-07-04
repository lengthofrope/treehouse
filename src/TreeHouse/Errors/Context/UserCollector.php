<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Context;

use LengthOfRope\TreeHouse\Auth\AuthManager;
use LengthOfRope\TreeHouse\Database\ActiveRecord;
use Throwable;

/**
 * Collects user authentication context information
 */
class UserCollector implements ContextCollectorInterface
{
    private ?AuthManager $auth = null;

    public function __construct(?AuthManager $auth = null)
    {
        $this->auth = $auth;
    }

    /**
     * Collect user context data
     */
    public function collect(Throwable $exception): array
    {
        $context = [
            'user' => [
                'authenticated' => false,
                'guest' => true,
                'timestamp' => time()
            ]
        ];

        if (!$this->auth) {
            return $context;
        }

        try {
            if ($this->auth->check()) {
                $user = $this->auth->user();
                
                if ($user) {
                    $context['user'] = [
                        'authenticated' => true,
                        'guest' => false,
                        'id' => $this->getUserId($user),
                        'identifier' => $this->getUserIdentifier($user),
                        'roles' => $this->getUserRoles($user),
                        'permissions' => $this->getUserPermissions($user),
                        'session_id' => $this->getSessionId(),
                        'last_activity' => $this->getLastActivity($user),
                        'ip_address' => $this->getClientIp(),
                        'user_agent' => $this->getUserAgent(),
                        'timestamp' => time()
                    ];
                }
            }
        } catch (Throwable $e) {
            // If there's an error getting user info, log it but don't fail
            $context['user']['error'] = 'Failed to collect user context: ' . $e->getMessage();
        }

        return $context;
    }

    /**
     * Get user ID safely
     */
    private function getUserId(mixed $user): mixed
    {
        if (is_object($user)) {
            // Try common ID properties
            foreach (['id', 'user_id', 'getId'] as $property) {
                if (property_exists($user, $property)) {
                    return $user->$property;
                } elseif (method_exists($user, $property)) {
                    return $user->$property();
                }
            }
            
            // For ActiveRecord models
            if ($user instanceof ActiveRecord) {
                return $user->getKey();
            }
        } elseif (is_array($user)) {
            return $user['id'] ?? $user['user_id'] ?? null;
        }

        return null;
    }

    /**
     * Get user identifier (email, username, etc.)
     */
    private function getUserIdentifier(mixed $user): ?string
    {
        if (is_object($user)) {
            // Try common identifier properties
            foreach (['email', 'username', 'name', 'login'] as $property) {
                if (property_exists($user, $property) && !empty($user->$property)) {
                    return (string) $user->$property;
                } elseif (method_exists($user, 'get' . ucfirst($property))) {
                    $method = 'get' . ucfirst($property);
                    $value = $user->$method();
                    if (!empty($value)) {
                        return (string) $value;
                    }
                }
            }
        } elseif (is_array($user)) {
            foreach (['email', 'username', 'name', 'login'] as $key) {
                if (!empty($user[$key])) {
                    return (string) $user[$key];
                }
            }
        }

        return null;
    }

    /**
     * Get user roles
     */
    private function getUserRoles(mixed $user): array
    {
        $roles = [];

        if (is_object($user)) {
            // Try common role methods/properties
            if (method_exists($user, 'getRoles')) {
                $userRoles = $user->getRoles();
                if (is_array($userRoles)) {
                    $roles = $userRoles;
                }
            } elseif (method_exists($user, 'roles')) {
                $userRoles = $user->roles();
                if (is_array($userRoles)) {
                    $roles = $userRoles;
                } elseif (is_object($userRoles) && method_exists($userRoles, 'pluck')) {
                    // For relationship collections
                    $roles = $userRoles->pluck('name')->toArray();
                }
            } elseif (property_exists($user, 'roles') && is_array($user->roles)) {
                $roles = $user->roles;
            }
        } elseif (is_array($user) && isset($user['roles'])) {
            $roles = is_array($user['roles']) ? $user['roles'] : [$user['roles']];
        }

        // Ensure we return role names as strings
        return array_map('strval', $roles);
    }

    /**
     * Get user permissions
     */
    private function getUserPermissions(mixed $user): array
    {
        $permissions = [];

        if (is_object($user)) {
            // Try common permission methods
            if (method_exists($user, 'getPermissions')) {
                $userPermissions = $user->getPermissions();
                if (is_array($userPermissions)) {
                    $permissions = $userPermissions;
                }
            } elseif (method_exists($user, 'permissions')) {
                $userPermissions = $user->permissions();
                if (is_array($userPermissions)) {
                    $permissions = $userPermissions;
                } elseif (is_object($userPermissions) && method_exists($userPermissions, 'pluck')) {
                    // For relationship collections
                    $permissions = $userPermissions->pluck('name')->toArray();
                }
            } elseif (property_exists($user, 'permissions') && is_array($user->permissions)) {
                $permissions = $user->permissions;
            }
        } elseif (is_array($user) && isset($user['permissions'])) {
            $permissions = is_array($user['permissions']) ? $user['permissions'] : [$user['permissions']];
        }

        // Ensure we return permission names as strings
        return array_map('strval', $permissions);
    }

    /**
     * Get session ID
     */
    private function getSessionId(): ?string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_id() ?: null;
        }

        return null;
    }

    /**
     * Get last activity timestamp
     */
    private function getLastActivity(mixed $user): ?int
    {
        if (is_object($user)) {
            // Try common last activity properties
            foreach (['last_activity', 'lastActivity', 'updated_at', 'last_login'] as $property) {
                if (property_exists($user, $property) && !empty($user->$property)) {
                    $value = $user->$property;
                    
                    // Convert to timestamp if it's a date string or object
                    if (is_string($value)) {
                        return strtotime($value) ?: null;
                    } elseif (is_object($value) && method_exists($value, 'getTimestamp')) {
                        return $value->getTimestamp();
                    } elseif (is_numeric($value)) {
                        return (int) $value;
                    }
                }
            }
        } elseif (is_array($user)) {
            foreach (['last_activity', 'lastActivity', 'updated_at', 'last_login'] as $key) {
                if (!empty($user[$key])) {
                    $value = $user[$key];
                    
                    if (is_string($value)) {
                        return strtotime($value) ?: null;
                    } elseif (is_numeric($value)) {
                        return (int) $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return 'unknown';
    }

    /**
     * Get user agent
     */
    private function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get collector priority
     */
    public function getPriority(): int
    {
        return 70; // High priority for user context
    }

    /**
     * Check if this collector should run
     */
    public function shouldCollect(Throwable $exception): bool
    {
        // Always collect user context (even if just guest info)
        return true;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'user';
    }
}