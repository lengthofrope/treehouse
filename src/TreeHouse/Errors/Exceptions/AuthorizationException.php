<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use Throwable;

/**
 * Authorization Exception
 * 
 * Thrown when a user is authenticated but lacks the necessary permissions
 * to perform a specific action or access a resource. Handles role-based
 * access control (RBAC) and permission-based authorization.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AuthorizationException extends BaseException
{
    /**
     * Default error severity for authorization errors
     */
    protected string $severity = 'medium';

    /**
     * Default HTTP status code for authorization errors
     */
    protected int $statusCode = 403;

    /**
     * Required permission or role
     */
    protected ?string $requiredPermission = null;

    /**
     * User's current permissions
     *
     * @var array<string>
     */
    protected array $userPermissions = [];

    /**
     * Resource being accessed
     */
    protected ?string $resource = null;

    /**
     * Action being performed
     */
    protected ?string $action = null;

    /**
     * User identifier
     */
    protected ?string $userIdentifier = null;

    /**
     * Create a new authorization exception
     *
     * @param string $message Exception message
     * @param string|null $requiredPermission Required permission
     * @param array<string> $userPermissions User's current permissions
     * @param string|null $resource Resource being accessed
     * @param string|null $action Action being performed
     * @param string|null $userIdentifier User identifier
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = 'Access denied',
        ?string $requiredPermission = null,
        array $userPermissions = [],
        ?string $resource = null,
        ?string $action = null,
        ?string $userIdentifier = null,
        ?Throwable $previous = null,
        array $context = []
    ) {
        $this->requiredPermission = $requiredPermission;
        $this->userPermissions = $userPermissions;
        $this->resource = $resource;
        $this->action = $action;
        $this->userIdentifier = $userIdentifier;

        // Add authorization information to context
        $context = array_merge($context, [
            'required_permission' => $requiredPermission,
            'user_permissions' => $userPermissions,
            'resource' => $resource,
            'action' => $action,
            'user_identifier' => $userIdentifier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        parent::__construct($message, 0, $previous, $context);
        
        $this->userMessage = 'You do not have permission to perform this action.';
        
        // Authorization failures should be reported for security monitoring
        $this->reportable = true;
    }

    /**
     * Get the required permission
     */
    public function getRequiredPermission(): ?string
    {
        return $this->requiredPermission;
    }

    /**
     * Get the user's permissions
     *
     * @return array<string>
     */
    public function getUserPermissions(): array
    {
        return $this->userPermissions;
    }

    /**
     * Get the resource
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Get the action
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Get the user identifier
     */
    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    /**
     * Create exception for missing permission
     *
     * @param string $permission
     * @param string|null $userIdentifier
     * @param array<string> $userPermissions
     * @param string|null $resource
     * @param string|null $action
     * @return static
     */
    public static function missingPermission(
        string $permission,
        ?string $userIdentifier = null,
        array $userPermissions = [],
        ?string $resource = null,
        ?string $action = null
    ): static {
        $message = "Missing required permission: {$permission}";
        
        if ($resource && $action) {
            $message .= " to {$action} {$resource}";
        } elseif ($resource) {
            $message .= " for resource: {$resource}";
        } elseif ($action) {
            $message .= " for action: {$action}";
        }

        return new static(
            $message,
            $permission,
            $userPermissions,
            $resource,
            $action,
            $userIdentifier
        );
    }

    /**
     * Create exception for missing role
     *
     * @param string $role
     * @param string|null $userIdentifier
     * @param array<string> $userRoles
     * @param string|null $resource
     * @return static
     */
    public static function missingRole(
        string $role,
        ?string $userIdentifier = null,
        array $userRoles = [],
        ?string $resource = null
    ): static {
        $message = "Missing required role: {$role}";
        
        if ($resource) {
            $message .= " to access: {$resource}";
        }

        $context = ['user_roles' => $userRoles];

        return new static(
            $message,
            "role:{$role}",
            [],
            $resource,
            'access',
            $userIdentifier,
            null,
            $context
        );
    }

    /**
     * Create exception for resource ownership
     *
     * @param string $resource
     * @param string $resourceId
     * @param string|null $userIdentifier
     * @param string|null $ownerId
     * @return static
     */
    public static function notResourceOwner(
        string $resource,
        string $resourceId,
        ?string $userIdentifier = null,
        ?string $ownerId = null
    ): static {
        $message = "Access denied: not owner of {$resource} {$resourceId}";

        $context = [
            'resource_id' => $resourceId,
            'owner_id' => $ownerId,
        ];

        return new static(
            $message,
            'ownership',
            [],
            $resource,
            'access',
            $userIdentifier,
            null,
            $context
        );
    }

    /**
     * Create exception for policy violation
     *
     * @param string $policy
     * @param string|null $userIdentifier
     * @param string|null $resource
     * @param string|null $action
     * @param array<string, mixed> $policyContext
     * @return static
     */
    public static function policyViolation(
        string $policy,
        ?string $userIdentifier = null,
        ?string $resource = null,
        ?string $action = null,
        array $policyContext = []
    ): static {
        $message = "Policy violation: {$policy}";
        
        if ($resource && $action) {
            $message .= " for {$action} on {$resource}";
        }

        $context = array_merge($policyContext, [
            'policy' => $policy,
        ]);

        return new static(
            $message,
            "policy:{$policy}",
            [],
            $resource,
            $action,
            $userIdentifier,
            null,
            $context
        );
    }

    /**
     * Create exception for rate limiting
     *
     * @param string $limit
     * @param string|null $userIdentifier
     * @param int|null $retryAfterSeconds
     * @param array<string, mixed> $limitDetails
     * @return static
     */
    public static function rateLimitExceeded(
        string $limit,
        ?string $userIdentifier = null,
        ?int $retryAfterSeconds = null,
        array $limitDetails = []
    ): static {
        $message = "Rate limit exceeded: {$limit}";

        $context = array_merge($limitDetails, [
            'limit_type' => $limit,
            'retry_after_seconds' => $retryAfterSeconds,
        ]);

        $exception = new static(
            $message,
            "rate_limit:{$limit}",
            [],
            null,
            'rate_limit',
            $userIdentifier,
            null,
            $context
        );
        
        $exception->setStatusCode(429); // Too Many Requests
        
        return $exception;
    }

    /**
     * Create exception for IP restriction
     *
     * @param string $ipAddress
     * @param array<string> $allowedIps
     * @param string|null $userIdentifier
     * @return static
     */
    public static function ipRestricted(
        string $ipAddress,
        array $allowedIps = [],
        ?string $userIdentifier = null
    ): static {
        $message = "Access denied from IP address: {$ipAddress}";

        $context = [
            'ip_address' => $ipAddress,
            'allowed_ips' => $allowedIps,
        ];

        return new static(
            $message,
            'ip_whitelist',
            [],
            null,
            'access',
            $userIdentifier,
            null,
            $context
        );
    }

    /**
     * Create exception for time-based restrictions
     *
     * @param string $restriction
     * @param \DateTimeInterface $currentTime
     * @param string|null $userIdentifier
     * @param array<string, mixed> $timeRestrictions
     * @return static
     */
    public static function timeRestricted(
        string $restriction,
        \DateTimeInterface $currentTime,
        ?string $userIdentifier = null,
        array $timeRestrictions = []
    ): static {
        $message = "Access denied due to time restriction: {$restriction}";

        $context = array_merge($timeRestrictions, [
            'restriction_type' => $restriction,
            'current_time' => $currentTime->format('Y-m-d H:i:s'),
        ]);

        return new static(
            $message,
            "time:{$restriction}",
            [],
            null,
            'access',
            $userIdentifier,
            null,
            $context
        );
    }

    /**
     * Create exception for maintenance mode
     *
     * @param string|null $userIdentifier
     * @param \DateTimeInterface|null $maintenanceEnd
     * @return static
     */
    public static function maintenanceMode(
        ?string $userIdentifier = null,
        ?\DateTimeInterface $maintenanceEnd = null
    ): static {
        $message = 'Access denied: system is in maintenance mode';

        $context = [];
        if ($maintenanceEnd) {
            $context['maintenance_end'] = $maintenanceEnd->format('Y-m-d H:i:s');
        }

        $exception = new static(
            $message,
            'maintenance',
            [],
            null,
            'access',
            $userIdentifier,
            null,
            $context
        );
        
        $exception->setStatusCode(503); // Service Unavailable
        $exception->setUserMessage('The system is currently under maintenance. Please try again later.');
        
        return $exception;
    }

    /**
     * Get permission analysis
     *
     * @return array<string, mixed>
     */
    public function getPermissionAnalysis(): array
    {
        return [
            'required' => $this->requiredPermission,
            'user_has' => $this->userPermissions,
            'missing' => $this->requiredPermission && !in_array($this->requiredPermission, $this->userPermissions, true),
            'resource' => $this->resource,
            'action' => $this->action,
        ];
    }

    /**
     * Convert to array with authorization-specific information
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['authorization'] = [
            'required_permission' => $this->requiredPermission,
            'user_permissions' => $this->userPermissions,
            'resource' => $this->resource,
            'action' => $this->action,
            'user_identifier' => $this->userIdentifier,
            'permission_analysis' => $this->getPermissionAnalysis(),
        ];
        
        return $array;
    }
}