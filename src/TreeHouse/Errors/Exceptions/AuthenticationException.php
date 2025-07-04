<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Errors\Exceptions;

use Throwable;

/**
 * Authentication Exception
 * 
 * Thrown when authentication fails or when authentication is required
 * but not provided. Handles various authentication scenarios including
 * login failures, token validation, and session management.
 * 
 * @package LengthOfRope\TreeHouse\Errors\Exceptions
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AuthenticationException extends BaseException
{
    /**
     * Default error severity for authentication errors
     */
    protected string $severity = 'medium';

    /**
     * Default HTTP status code for authentication errors
     */
    protected int $statusCode = 401;

    /**
     * Authentication method that failed
     */
    protected ?string $authMethod = null;

    /**
     * User identifier (if available)
     */
    protected ?string $userIdentifier = null;

    /**
     * Authentication attempt details
     *
     * @var array<string, mixed>
     */
    protected array $attemptDetails = [];

    /**
     * Create a new authentication exception
     *
     * @param string $message Exception message
     * @param string|null $authMethod Authentication method
     * @param string|null $userIdentifier User identifier
     * @param array<string, mixed> $attemptDetails Authentication attempt details
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = 'Authentication failed',
        ?string $authMethod = null,
        ?string $userIdentifier = null,
        array $attemptDetails = [],
        ?Throwable $previous = null,
        array $context = []
    ) {
        $this->authMethod = $authMethod;
        $this->userIdentifier = $userIdentifier;
        $this->attemptDetails = $attemptDetails;

        // Add authentication information to context (sanitized)
        $context = array_merge($context, [
            'auth_method' => $authMethod,
            'user_identifier' => $userIdentifier,
            'attempt_details' => $this->sanitizeAttemptDetails($attemptDetails),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        parent::__construct($message, 0, $previous, $context);
        
        $this->userMessage = 'Authentication failed. Please check your credentials and try again.';
        
        // Authentication failures should not be reported by default (to avoid spam)
        $this->reportable = false;
    }

    /**
     * Sanitize attempt details to remove sensitive information
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function sanitizeAttemptDetails(array $details): array
    {
        $sanitized = $details;
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'hash'];
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[HIDDEN]';
            }
        }
        
        return $sanitized;
    }

    /**
     * Get the authentication method
     */
    public function getAuthMethod(): ?string
    {
        return $this->authMethod;
    }

    /**
     * Get the user identifier
     */
    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    /**
     * Get the attempt details (sanitized)
     *
     * @return array<string, mixed>
     */
    public function getAttemptDetails(): array
    {
        return $this->sanitizeAttemptDetails($this->attemptDetails);
    }

    /**
     * Create exception for invalid credentials
     *
     * @param string|null $userIdentifier
     * @param string $authMethod
     * @param array<string, mixed> $attemptDetails
     * @return static
     */
    public static function invalidCredentials(
        ?string $userIdentifier = null,
        string $authMethod = 'password',
        array $attemptDetails = []
    ): static {
        $message = 'Invalid credentials provided';
        
        if ($userIdentifier) {
            $message .= " for user: {$userIdentifier}";
        }

        return new static($message, $authMethod, $userIdentifier, $attemptDetails);
    }

    /**
     * Create exception for expired credentials
     *
     * @param string $credentialType
     * @param string|null $userIdentifier
     * @param \DateTimeInterface|null $expiredAt
     * @return static
     */
    public static function expiredCredentials(
        string $credentialType,
        ?string $userIdentifier = null,
        ?\DateTimeInterface $expiredAt = null
    ): static {
        $message = "Expired {$credentialType}";
        
        if ($userIdentifier) {
            $message .= " for user: {$userIdentifier}";
        }

        $attemptDetails = ['credential_type' => $credentialType];
        if ($expiredAt) {
            $attemptDetails['expired_at'] = $expiredAt->format('Y-m-d H:i:s');
        }

        return new static($message, $credentialType, $userIdentifier, $attemptDetails);
    }

    /**
     * Create exception for missing authentication
     *
     * @param string $requiredMethod
     * @param string|null $resource
     * @return static
     */
    public static function missingAuthentication(string $requiredMethod = 'any', ?string $resource = null): static
    {
        $message = 'Authentication required';
        
        if ($resource) {
            $message .= " to access: {$resource}";
        }

        $attemptDetails = ['required_method' => $requiredMethod];
        if ($resource) {
            $attemptDetails['protected_resource'] = $resource;
        }

        return new static($message, $requiredMethod, null, $attemptDetails);
    }

    /**
     * Create exception for invalid token
     *
     * @param string $tokenType
     * @param string|null $reason
     * @param string|null $userIdentifier
     * @return static
     */
    public static function invalidToken(
        string $tokenType = 'bearer',
        ?string $reason = null,
        ?string $userIdentifier = null
    ): static {
        $message = "Invalid {$tokenType} token";
        
        if ($reason) {
            $message .= ": {$reason}";
        }

        $attemptDetails = [
            'token_type' => $tokenType,
            'failure_reason' => $reason,
        ];

        return new static($message, $tokenType, $userIdentifier, $attemptDetails);
    }

    /**
     * Create exception for account locked
     *
     * @param string $userIdentifier
     * @param string|null $reason
     * @param \DateTimeInterface|null $unlocksAt
     * @return static
     */
    public static function accountLocked(
        string $userIdentifier,
        ?string $reason = null,
        ?\DateTimeInterface $unlocksAt = null
    ): static {
        $message = "Account locked for user: {$userIdentifier}";
        
        if ($reason) {
            $message .= " - {$reason}";
        }

        $attemptDetails = ['lock_reason' => $reason];
        if ($unlocksAt) {
            $attemptDetails['unlocks_at'] = $unlocksAt->format('Y-m-d H:i:s');
        }

        $exception = new static($message, 'account_lock', $userIdentifier, $attemptDetails);
        $exception->setStatusCode(423); // Locked
        
        return $exception;
    }

    /**
     * Create exception for too many attempts
     *
     * @param string|null $userIdentifier
     * @param int $attemptCount
     * @param int $maxAttempts
     * @param int|null $retryAfterSeconds
     * @return static
     */
    public static function tooManyAttempts(
        ?string $userIdentifier = null,
        int $attemptCount = 0,
        int $maxAttempts = 5,
        ?int $retryAfterSeconds = null
    ): static {
        $message = "Too many authentication attempts";
        
        if ($userIdentifier) {
            $message .= " for user: {$userIdentifier}";
        }

        $attemptDetails = [
            'attempt_count' => $attemptCount,
            'max_attempts' => $maxAttempts,
            'retry_after_seconds' => $retryAfterSeconds,
        ];

        $exception = new static($message, 'rate_limit', $userIdentifier, $attemptDetails);
        $exception->setStatusCode(429); // Too Many Requests
        
        if ($retryAfterSeconds) {
            $exception->addContext('retry_after_seconds', $retryAfterSeconds);
        }
        
        return $exception;
    }

    /**
     * Create exception for session expired
     *
     * @param string|null $userIdentifier
     * @param \DateTimeInterface|null $expiredAt
     * @return static
     */
    public static function sessionExpired(
        ?string $userIdentifier = null,
        ?\DateTimeInterface $expiredAt = null
    ): static {
        $message = 'Session expired';
        
        if ($userIdentifier) {
            $message .= " for user: {$userIdentifier}";
        }

        $attemptDetails = [];
        if ($expiredAt) {
            $attemptDetails['expired_at'] = $expiredAt->format('Y-m-d H:i:s');
        }

        return new static($message, 'session', $userIdentifier, $attemptDetails);
    }

    /**
     * Convert to array with authentication-specific information
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        $array['authentication'] = [
            'auth_method' => $this->authMethod,
            'user_identifier' => $this->userIdentifier,
            'attempt_details' => $this->getAttemptDetails(), // Use sanitized version
        ];
        
        return $array;
    }
}