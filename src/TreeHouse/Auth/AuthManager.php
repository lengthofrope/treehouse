<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Http\Session;
use LengthOfRope\TreeHouse\Http\Cookie;
use LengthOfRope\TreeHouse\Http\Request;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Auth\Jwt\JwtConfig;
use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Authentication Manager
 *
 * Manages authentication guards and provides a unified interface
 * for authentication operations. Handles guard creation, configuration,
 * and delegation of authentication methods to the appropriate guard.
 *
 * Features:
 * - Multiple authentication guard support
 * - Guard configuration and creation
 * - Default guard management
 * - Authentication method delegation
 * - User provider management
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class AuthManager
{
    /**
     * The application configuration
     */
    protected array $config;

    /**
     * The session instance
     */
    protected Session $session;

    /**
     * The cookie instance
     */
    protected Cookie $cookie;

    /**
     * The hash instance
     */
    protected Hash $hash;

    /**
     * The current HTTP request
     */
    protected ?Request $request = null;

    /**
     * The array of created guards
     */
    protected array $guards = [];

    /**
     * The array of created user providers
     */
    protected array $providers = [];

    /**
     * The default guard name
     */
    protected string $defaultGuard;

    /**
     * Create a new AuthManager instance
     *
     * @param array $config Authentication configuration
     * @param Session $session Session instance
     * @param Cookie $cookie Cookie instance
     * @param Hash $hash Hash instance
     */
    public function __construct(
        array $config,
        Session $session,
        Cookie $cookie,
        Hash $hash,
        ?Request $request = null
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->cookie = $cookie;
        $this->hash = $hash;
        $this->request = $request;
        $this->defaultGuard = $config['default'] ?? 'web';
    }

    /**
     * Get a guard instance by name
     *
     * @param string|null $name Guard name
     * @return Guard
     * @throws AuthenticationException
     */
    public function guard(?string $name = null): Guard
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    /**
     * Get a user provider instance by name
     *
     * @param string|null $name Provider name
     * @return UserProvider
     * @throws AuthenticationException
     */
    public function createUserProvider(?string $name = null): UserProvider
    {
        $config = $this->getProviderConfiguration($name);

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $driver = $config['driver'] ?? 'database';

        switch ($driver) {
            case 'database':
                return $this->providers[$name] = $this->createDatabaseProvider($config);
            case 'jwt':
                return $this->providers[$name] = $this->createJwtProvider($config);
            default:
                throw new AuthenticationException("Authentication user provider [{$driver}] is not defined.", 'AUTH_INVALID_PROVIDER');
        }
    }

    /**
     * Determine if the current user is authenticated
     *
     * @param string|null $guard Guard name
     * @return bool
     */
    public function check(?string $guard = null): bool
    {
        return $this->guard($guard)->check();
    }

    /**
     * Determine if the current user is a guest
     *
     * @param string|null $guard Guard name
     * @return bool
     */
    public function guest(?string $guard = null): bool
    {
        return $this->guard($guard)->guest();
    }

    /**
     * Get the currently authenticated user
     *
     * @param string|null $guard Guard name
     * @return mixed
     */
    public function user(?string $guard = null): mixed
    {
        return $this->guard($guard)->user();
    }

    /**
     * Get the ID of the currently authenticated user
     *
     * @param string|null $guard Guard name
     * @return mixed
     */
    public function id(?string $guard = null): mixed
    {
        return $this->guard($guard)->id();
    }

    /**
     * Validate a user's credentials
     *
     * @param array $credentials User credentials
     * @param string|null $guard Guard name
     * @return bool
     */
    public function validate(array $credentials, ?string $guard = null): bool
    {
        return $this->guard($guard)->validate($credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials
     *
     * @param array $credentials User credentials
     * @param bool $remember Whether to remember the user
     * @param string|null $guard Guard name
     * @return bool
     */
    public function attempt(array $credentials, bool $remember = false, ?string $guard = null): bool
    {
        return $this->guard($guard)->attempt($credentials, $remember);
    }

    /**
     * Attempt to authenticate a user using the given credentials and "remember" them
     *
     * @param array $credentials User credentials
     * @param string|null $guard Guard name
     * @return bool
     */
    public function attemptWhen(array $credentials, ?string $guard = null): bool
    {
        return $this->guard($guard)->attemptWhen($credentials);
    }

    /**
     * Log a user into the application without sessions or cookies
     *
     * @param mixed $user User instance or identifier
     * @param string|null $guard Guard name
     * @return bool
     */
    public function once(mixed $user, ?string $guard = null): bool
    {
        return $this->guard($guard)->once($user);
    }

    /**
     * Log a user into the application
     *
     * @param mixed $user User instance or identifier
     * @param bool $remember Whether to remember the user
     * @param string|null $guard Guard name
     * @return void
     */
    public function login(mixed $user, bool $remember = false, ?string $guard = null): void
    {
        $this->guard($guard)->login($user, $remember);
    }

    /**
     * Log the given user ID into the application
     *
     * @param mixed $id User identifier
     * @param bool $remember Whether to remember the user
     * @param string|null $guard Guard name
     * @return mixed
     */
    public function loginUsingId(mixed $id, bool $remember = false, ?string $guard = null): mixed
    {
        return $this->guard($guard)->loginUsingId($id, $remember);
    }

    /**
     * Log the given user ID into the application without sessions or cookies
     *
     * @param mixed $id User identifier
     * @param string|null $guard Guard name
     * @return mixed
     */
    public function onceUsingId(mixed $id, ?string $guard = null): mixed
    {
        return $this->guard($guard)->onceUsingId($id);
    }

    /**
     * Determine if the user was authenticated via "remember me" cookie
     *
     * @param string|null $guard Guard name
     * @return bool
     */
    public function viaRemember(?string $guard = null): bool
    {
        return $this->guard($guard)->viaRemember();
    }

    /**
     * Log the user out of the application
     *
     * @param string|null $guard Guard name
     * @return void
     */
    public function logout(?string $guard = null): void
    {
        $this->guard($guard)->logout();
    }

    /**
     * Invalidate other sessions for the current user
     *
     * @param string $password Current user password
     * @param string|null $guard Guard name
     * @return bool
     */
    public function logoutOtherDevices(string $password, ?string $guard = null): bool
    {
        return $this->guard($guard)->logoutOtherDevices($password);
    }

    /**
     * Get the default authentication driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultGuard;
    }

    /**
     * Set the default authentication driver name
     *
     * @param string $name Guard name
     * @return void
     */
    public function setDefaultDriver(string $name): void
    {
        $this->defaultGuard = $name;
    }

    /**
     * Resolve the given guard
     *
     * @param string $name Guard name
     * @return Guard
     * @throws AuthenticationException
     */
    protected function resolve(string $name): Guard
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new AuthenticationException("Auth guard [{$name}] is not defined.", 'AUTH_INVALID_GUARD');
        }

        $driver = $config['driver'] ?? 'session';

        switch ($driver) {
            case 'session':
                return $this->createSessionDriver($name, $config);
            case 'jwt':
                return $this->createJwtDriver($name, $config);
            default:
                throw new AuthenticationException("Auth driver [{$driver}] for guard [{$name}] is not defined.", 'AUTH_INVALID_DRIVER');
        }
    }

    /**
     * Create a session based authentication guard
     *
     * @param string $name Guard name
     * @param array $config Guard configuration
     * @return SessionGuard
     */
    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        return new SessionGuard(
            $this->session,
            $this->cookie,
            $provider,
            $this->hash
        );
    }

    /**
     * Create a database user provider
     *
     * @param array $config Provider configuration
     * @return DatabaseUserProvider
     */
    protected function createDatabaseProvider(array $config): DatabaseUserProvider
    {
        return new DatabaseUserProvider($this->hash, $config);
    }

    /**
     * Create a JWT based authentication guard
     *
     * @param string $name Guard name
     * @param array $config Guard configuration
     * @return JwtGuard
     */
    protected function createJwtDriver(string $name, array $config): JwtGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        $jwtConfig = $this->createJwtConfig();
        
        return new JwtGuard($provider, $jwtConfig, $this->request);
    }

    /**
     * Create a JWT user provider
     *
     * @param array $config Provider configuration
     * @return JwtUserProvider
     */
    protected function createJwtProvider(array $config): JwtUserProvider
    {
        $jwtConfig = $this->createJwtConfig();
        
        // Create fallback provider if specified
        $fallbackProvider = null;
        if (isset($config['fallback_provider'])) {
            $fallbackProvider = $this->createUserProvider($config['fallback_provider']);
        }
        
        return new JwtUserProvider($jwtConfig, $this->hash, $config, $fallbackProvider);
    }

    /**
     * Create JWT configuration instance
     *
     * @return JwtConfig
     * @throws AuthenticationException If JWT configuration is missing
     */
    protected function createJwtConfig(): JwtConfig
    {
        $jwtConfig = $this->config['jwt'] ?? null;
        
        if ($jwtConfig === null) {
            throw new AuthenticationException('JWT configuration is not defined.', 'JWT_CONFIG_MISSING');
        }
        
        return new JwtConfig($jwtConfig);
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
        
        // Update request for existing JWT guards
        foreach ($this->guards as $guard) {
            if ($guard instanceof JwtGuard) {
                $guard->setRequest($request);
            }
        }
    }

    /**
     * Get the current HTTP request
     *
     * @return Request|null
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * Get the guard configuration
     *
     * @param string $name Guard name
     * @return array|null
     */
    protected function getConfig(string $name): ?array
    {
        return $this->config['guards'][$name] ?? null;
    }

    /**
     * Get the user provider configuration
     *
     * @param string|null $provider Provider name
     * @return array
     * @throws AuthenticationException
     */
    protected function getProviderConfiguration(?string $provider): array
    {
        if ($provider = $provider ?: $this->getDefaultUserProvider()) {
            if (!isset($this->config['providers'][$provider])) {
                throw new AuthenticationException('Authentication user provider is not defined.', 'AUTH_PROVIDER_NOT_DEFINED');
            }
            return $this->config['providers'][$provider];
        }

        throw new AuthenticationException('Authentication user provider is not defined.', 'AUTH_PROVIDER_NOT_DEFINED');
    }

    /**
     * Get the default user provider name
     *
     * @return string|null
     */
    protected function getDefaultUserProvider(): ?string
    {
        return $this->config['providers']['default'] ?? null;
    }

    /**
     * Dynamically call the default driver instance
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->{$method}(...$parameters);
    }
}