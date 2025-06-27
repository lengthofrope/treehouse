<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Session-Based Authentication Guard
 *
 * Implements session-based authentication using PHP sessions and cookies
 * for "remember me" functionality. Handles user login, logout, and
 * authentication state management.
 *
 * Features:
 * - Session-based authentication state
 * - Remember me functionality with secure tokens
 * - User credential validation
 * - Session regeneration for security
 * - Multiple device logout support
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class SessionGuard implements Guard
{
    /**
     * The session instance
     */
    protected Session $session;

    /**
     * The cookie instance
     */
    protected Cookie $cookie;

    /**
     * The user provider instance
     */
    protected UserProvider $provider;

    /**
     * The hash instance
     */
    protected Hash $hash;

    /**
     * The currently authenticated user
     */
    protected mixed $user = null;

    /**
     * Indicates if the user was authenticated via a remember cookie
     */
    protected bool $viaRemember = false;

    /**
     * Indicates if a token user retrieval has been attempted
     */
    protected bool $tokenRetrievalAttempted = false;

    /**
     * The name of the field on the user model that contains the remember token
     */
    protected string $rememberTokenName = 'remember_token';

    /**
     * Create a new SessionGuard instance
     *
     * @param Session $session Session instance
     * @param Cookie $cookie Cookie instance
     * @param UserProvider $provider User provider instance
     * @param Hash $hash Hash instance
     */
    public function __construct(
        Session $session,
        Cookie $cookie,
        UserProvider $provider,
        Hash $hash
    ) {
        $this->session = $session;
        $this->cookie = $cookie;
        $this->provider = $provider;
        $this->hash = $hash;
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
        if (!is_null($this->user)) {
            return $this->user;
        }

        // Try to get user from session
        $id = $this->session->get($this->getName());
        if (!is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        // Try to get user from remember cookie if session is empty
        if (is_null($this->user) && !$this->tokenRetrievalAttempted) {
            $this->user = $this->getUserByRememberToken();
            $this->tokenRetrievalAttempted = true;
        }

        return $this->user;
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
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials
     *
     * @param array $credentials
     * @param bool $remember
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
     * @param array $credentials
     * @return bool
     */
    public function attemptWhen(array $credentials = []): bool
    {
        return $this->attempt($credentials, true);
    }

    /**
     * Log a user into the application without sessions or cookies
     *
     * @param mixed $user
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
     * @param mixed $user
     * @param bool $remember
     * @return void
     */
    public function login(mixed $user, bool $remember = false): void
    {
        $this->updateSession($this->getUserId($user));

        // If the user should be permanently "remembered" by the application we will
        // queue a permanent cookie that contains the encrypted copy of the user
        // identifier. We will then decrypt this on subsequent visits to retrieve
        // the user.
        if ($remember) {
            $this->ensureRememberTokenIsSet($user);
            $this->queueRememberCookie($user);
        }

        $this->setUser($user);
    }

    /**
     * Log the given user ID into the application
     *
     * @param mixed $id
     * @param bool $remember
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
     * @param mixed $id
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
     * @return bool
     */
    public function viaRemember(): bool
    {
        return $this->viaRemember;
    }

    /**
     * Log the user out of the application
     *
     * @return void
     */
    public function logout(): void
    {
        $user = $this->user();

        // Clear the user from the session
        $this->clearUserDataFromStorage();

        // If we have an event dispatcher instance, we can fire off the logout event
        // so any further processing can be done. This allows the developer to be
        // listening for anytime a user signs out of this application manually.
        if (!is_null($user)) {
            $this->cycleRememberToken($user);
        }

        // Once we have fired the logout event we will clear the users out of memory
        // so they are no longer available as the user is no longer considered as
        // being signed into this application and should not be available here.
        $this->user = null;
        $this->viaRemember = false;
    }

    /**
     * Invalidate other sessions for the current user
     *
     * @param string $password
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

        // Regenerate the session ID to invalidate other sessions
        $this->session->regenerate(true);

        // Cycle the remember token to invalidate remember cookies
        $this->cycleRememberToken($user);

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
     * @param UserProvider $provider
     * @return void
     */
    public function setProvider(UserProvider $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Get the session key name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'login_' . md5(static::class);
    }

    /**
     * Get the remember me cookie name
     *
     * @return string
     */
    public function getRememberName(): string
    {
        return 'remember_' . md5(static::class);
    }

    /**
     * Set the current user
     *
     * @param mixed $user
     * @return void
     */
    public function setUser(mixed $user): void
    {
        $this->user = $user;
        $this->viaRemember = false;
    }

    /**
     * Get the user ID from the user instance
     *
     * @param mixed $user
     * @return mixed
     * @throws InvalidArgumentException
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

        throw new InvalidArgumentException('User must have an ID or implement getAuthIdentifier method');
    }

    /**
     * Update the session with the given ID
     *
     * @param mixed $id
     * @return void
     */
    protected function updateSession(mixed $id): void
    {
        $this->session->set($this->getName(), $id);
        $this->session->regenerate(true);
    }

    /**
     * Get the user by remember token
     *
     * @return mixed
     */
    protected function getUserByRememberToken(): mixed
    {
        $token = $this->getRememberToken();

        if (!is_null($token)) {
            [$id, $rememberToken] = explode('|', $token, 2);
            
            $user = $this->provider->retrieveByToken($id, $rememberToken);
            
            if (!is_null($user)) {
                $this->viaRemember = true;
                $this->updateSession($this->getUserId($user));
            }
            
            return $user;
        }

        return null;
    }

    /**
     * Get the remember token from the cookie
     *
     * @return string|null
     */
    protected function getRememberToken(): ?string
    {
        return $this->cookie->get($this->getRememberName());
    }

    /**
     * Queue the remember cookie
     *
     * @param mixed $user
     * @return void
     */
    protected function queueRememberCookie(mixed $user): void
    {
        $value = $this->getUserId($user) . '|' . $this->getRememberTokenForUser($user);
        
        $cookie = Cookie::make(
            $this->getRememberName(),
            $value,
            525600, // 1 year in minutes
            '/',
            '',
            true, // secure
            true, // httponly
            'Lax'
        );
        
        $cookie->send();
    }

    /**
     * Get the remember token for the user
     *
     * @param mixed $user
     * @return string
     */
    protected function getRememberTokenForUser(mixed $user): string
    {
        if (is_object($user)) {
            if (method_exists($user, 'getRememberToken')) {
                return $user->getRememberToken();
            }
            
            if (isset($user->{$this->rememberTokenName})) {
                return $user->{$this->rememberTokenName};
            }
        }

        if (is_array($user) && isset($user[$this->rememberTokenName])) {
            return $user[$this->rememberTokenName];
        }

        throw new RuntimeException('User must have a remember token');
    }

    /**
     * Ensure the remember token is set for the user
     *
     * @param mixed $user
     * @return void
     */
    protected function ensureRememberTokenIsSet(mixed $user): void
    {
        $token = $this->getRememberTokenForUser($user);
        
        if (empty($token)) {
            $this->cycleRememberToken($user);
        }
    }

    /**
     * Cycle the remember token for the user
     *
     * @param mixed $user
     * @return void
     */
    protected function cycleRememberToken(mixed $user): void
    {
        $token = Str::random(60);
        $this->provider->updateRememberToken($user, $token);
    }

    /**
     * Clear the user data from storage
     *
     * @return void
     */
    protected function clearUserDataFromStorage(): void
    {
        $this->session->remove($this->getName());
        
        if (!is_null($this->getRememberToken())) {
            $this->cookie->forget($this->getRememberName());
        }
    }
}