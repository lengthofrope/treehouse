<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenValidator;
use LengthOfRope\TreeHouse\Auth\Jwt\TokenGenerator;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * JWT Authentication Guard
 *
 * Implements stateless JWT-based authentication. This guard validates
 * JWT tokens from HTTP requests and provides user authentication
 * without requiring sessions or server-side state.
 *
 * Features:
 * - Stateless authentication via JWT tokens
 * - Bearer token extraction from Authorization header
 * - Optional cookie and query parameter token extraction
 * - Integration with existing UserProvider system
 * - JWT token generation and validation
 * - Support for refresh tokens and token blacklisting
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class JwtGuard implements Guard
{
    /**
     * The user provider instance
     */
    protected UserProvider $provider;

    /**
     * The JWT configuration
     */
    protected JwtConfig $jwtConfig;

    /**
     * The token validator instance
     */
    protected TokenValidator $tokenValidator;

    /**
     * The token generator instance
     */
    protected TokenGenerator $tokenGenerator;

    /**
     * The current HTTP request
     */
    protected ?Request $request = null;

    /**
     * The currently authenticated user
     */
    protected mixed $user = null;

    /**
     * The current JWT claims
     */
    protected ?ClaimsManager $claims = null;

    /**
     * The current JWT token
     */
    protected ?string $token = null;

    /**
     * Indicates if user resolution has been attempted
     */
    protected bool $userResolutionAttempted = false;

    /**
     * Token extraction sources in order of preference
     */
    protected array $tokenSources = ['header', 'cookie', 'query'];

    /**
     * Create a new JwtGuard instance
     *
     * @param UserProvider $provider User provider instance
     * @param JwtConfig $jwtConfig JWT configuration
     * @param Request|null $request Current HTTP request
     */
    public function __construct(
        UserProvider $provider,
        JwtConfig $jwtConfig,
        ?Request $request = null
    ) {
        $this->provider = $provider;
        $this->jwtConfig = $jwtConfig;
        $this->request = $request;
        
        $this->tokenValidator = new TokenValidator($jwtConfig);
        $this->tokenGenerator = new TokenGenerator($jwtConfig);
    }

    /**
     * Determine if the current user is authenticated
     *
     * @return bool
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest (not authenticated)
     *
     * @return bool
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user
     *
     * @return mixed
     */
    public function user(): mixed
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($this->userResolutionAttempted) {
            return null;
        }

        $this->userResolutionAttempted = true;

        try {
            $token = $this->getTokenFromRequest();
            if ($token === null) {
                return null;
            }

            $claims = $this->tokenValidator->validateAuthToken($token);
            $userId = $claims->getSubject();

            if ($userId === null) {
                return null;
            }

            $user = $this->provider->retrieveById($userId);
            if ($user !== null) {
                $this->user = $user;
                $this->claims = $claims;
                $this->token = $token;
            }

            return $this->user;
        } catch (InvalidArgumentException $e) {
            // Invalid or expired token
            return null;
        } catch (\Exception $e) {
            // Any other errors should not expose user
            return null;
        }
    }

    /**
     * Get the ID of the currently authenticated user
     *
     * @return mixed
     */
    public function id(): mixed
    {
        $user = $this->user();
        return $user ? $this->getUserId($user) : null;
    }

    /**
     * Validate a user's credentials
     *
     * For JWT guard, this validates JWT tokens rather than credentials
     *
     * @param array $credentials Token or credentials array
     * @return bool
     */
    public function validate(array $credentials = []): bool
    {
        $token = $credentials['token'] ?? null;
        
        if (!$token) {
            return false;
        }

        try {
            $this->tokenValidator->validateAuthToken($token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Attempt to authenticate a user using the given credentials
     *
     * For JWT guard, this generates a new token if credentials are valid
     *
     * @param array $credentials User credentials
     * @param bool $remember Whether to issue a refresh token (not used in JWT)
     * @return bool
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if (!is_null($user) && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Attempt to authenticate a user using the given credentials and "remember" them
     *
     * @param array $credentials User credentials
     * @return bool
     */
    public function attemptWhen(array $credentials = []): bool
    {
        return $this->attempt($credentials, true);
    }

    /**
     * Log a user into the application without sessions or cookies
     *
     * @param mixed $user User instance or user identifier
     * @return bool
     */
    public function once(mixed $user): bool
    {
        if (!is_null($user)) {
            $this->setUser($user);
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application
     *
     * For JWT guard, this generates a new access token
     *
     * @param mixed $user User instance or user identifier
     * @param bool $remember Whether to issue a refresh token
     * @return void
     */
    public function login(mixed $user, bool $remember = false): void
    {
        $userId = $this->getUserId($user);
        
        // Generate access token
        $this->token = $this->tokenGenerator->generateAuthToken($userId);
        
        // Generate claims for the token
        try {
            $this->claims = $this->tokenValidator->validateAuthToken($this->token);
        } catch (InvalidArgumentException $e) {
            throw new AuthenticationException('Failed to generate valid JWT token', 'JWT_TOKEN_GENERATION_FAILED');
        }
        
        $this->setUser($user);
    }

    /**
     * Log the given user ID into the application
     *
     * @param mixed $id User identifier
     * @param bool $remember Whether to issue a refresh token
     * @return mixed
     */
    public function loginUsingId(mixed $id, bool $remember = false): mixed
    {
        $user = $this->provider->retrieveById($id);

        if (!is_null($user)) {
            $this->login($user, $remember);
            return $user;
        }

        return false;
    }

    /**
     * Log the given user ID into the application without sessions or cookies
     *
     * @param mixed $id User identifier
     * @return mixed
     */
    public function onceUsingId(mixed $id): mixed
    {
        $user = $this->provider->retrieveById($id);

        if (!is_null($user)) {
            $this->setUser($user);
            return $user;
        }

        return false;
    }

    /**
     * Determine if the user was authenticated via "remember me" cookie
     *
     * For JWT guard, this is always false as JWT tokens are stateless
     *
     * @return bool
     */
    public function viaRemember(): bool
    {
        return false;
    }

    /**
     * Log the user out of the application
     *
     * For JWT guard, this clears the current user and token
     * Token blacklisting should be handled at application level
     *
     * @return void
     */
    public function logout(): void
    {
        $this->user = null;
        $this->claims = null;
        $this->token = null;
        $this->userResolutionAttempted = false;
    }

    /**
     * Invalidate other sessions for the current user
     *
     * For JWT guard, this is not applicable as JWT is stateless
     * Token invalidation should be handled at application level via blacklisting
     *
     * @param string $password Current user password for verification
     * @return bool
     */
    public function logoutOtherDevices(string $password): bool
    {
        $user = $this->user();

        if (is_null($user)) {
            return false;
        }

        // Validate the current user's password
        if (!$this->provider->validateCredentials($user, ['password' => $password])) {
            return false;
        }

        // For JWT, this would require implementing token blacklisting
        // at the application level. For now, we return true to indicate
        // the password was validated successfully.
        return true;
    }

    /**
     * Get the user provider used by the guard
     *
     * @return UserProvider
     */
    public function getProvider(): UserProvider
    {
        return $this->provider;
    }

    /**
     * Set the user provider used by the guard
     *
     * @param UserProvider $provider The user provider instance
     * @return void
     */
    public function setProvider(UserProvider $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Get the current JWT token
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        // Ensure user resolution has been attempted
        $this->user();
        return $this->token;
    }

    /**
     * Get the current JWT claims
     *
     * @return ClaimsManager|null
     */
    public function getClaims(): ?ClaimsManager
    {
        // Ensure user resolution has been attempted
        $this->user();
        return $this->claims;
    }

    /**
     * Set the current HTTP request
     *
     * @param Request $request HTTP request instance
     * @return void
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
        
        // Reset user resolution state when request changes
        $this->user = null;
        $this->claims = null;
        $this->token = null;
        $this->userResolutionAttempted = false;
    }

    /**
     * Generate a JWT token for the given user
     *
     * @param mixed $user User instance
     * @param array $customClaims Additional claims to include
     * @return string JWT token
     */
    public function generateTokenForUser(mixed $user, array $customClaims = []): string
    {
        $userId = $this->getUserId($user);
        
        return $this->tokenGenerator->generateAuthToken(
            $userId,
            $customClaims
        );
    }

    /**
     * Set the current user
     *
     * @param mixed $user User instance
     * @return void
     */
    protected function setUser(mixed $user): void
    {
        $this->user = $user;
        $this->userResolutionAttempted = true;
    }

    /**
     * Get the user ID from the user instance
     *
     * @param mixed $user User instance
     * @return mixed User ID
     * @throws AuthenticationException If user ID cannot be determined
     */
    protected function getUserId(mixed $user): mixed
    {
        if (is_object($user)) {
            if (method_exists($user, 'getAuthIdentifier')) {
                return $user->getAuthIdentifier();
            }
            
            if (isset($user->id)) {
                return $user->id;
            }
        }

        if (is_array($user) && isset($user['id'])) {
            return $user['id'];
        }

        throw new AuthenticationException(
            'User must have an ID or implement getAuthIdentifier method',
            'AUTH_INVALID_USER_ID'
        );
    }

    /**
     * Get JWT token from the current request
     *
     * @return string|null JWT token or null if not found
     */
    protected function getTokenFromRequest(): ?string
    {
        if ($this->request === null) {
            return null;
        }

        foreach ($this->tokenSources as $source) {
            $token = match ($source) {
                'header' => $this->getTokenFromHeader(),
                'cookie' => $this->getTokenFromCookie(),
                'query' => $this->getTokenFromQuery(),
                default => null
            };

            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Extract JWT token from Authorization header
     *
     * @return string|null JWT token or null if not found
     */
    protected function getTokenFromHeader(): ?string
    {
        $header = $this->request->header('authorization');
        
        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Extract JWT token from cookie
     *
     * @return string|null JWT token or null if not found
     */
    protected function getTokenFromCookie(): ?string
    {
        return $this->request->cookie('jwt_token');
    }

    /**
     * Extract JWT token from query parameter
     *
     * @return string|null JWT token or null if not found
     */
    protected function getTokenFromQuery(): ?string
    {
        return $this->request->query('token');
    }
}