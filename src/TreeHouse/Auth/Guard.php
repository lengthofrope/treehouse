<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

/**
 * Authentication Guard Interface
 *
 * Defines the contract for authentication guards that handle
 * user authentication, login, logout, and user retrieval.
 *
 * Guards are responsible for:
 * - Authenticating users with credentials
 * - Maintaining authentication state
 * - Retrieving the currently authenticated user
 * - Logging users in and out
 * - Checking authentication status
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface Guard
{
    /**
     * Determine if the current user is authenticated
     *
     * @return bool True if user is authenticated, false otherwise
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest (not authenticated)
     *
     * @return bool True if user is not authenticated, false otherwise
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user
     *
     * @return mixed The authenticated user or null if not authenticated
     */
    public function user(): mixed;

    /**
     * Get the ID of the currently authenticated user
     *
     * @return mixed The user ID or null if not authenticated
     */
    public function id(): mixed;

    /**
     * Validate a user's credentials
     *
     * @param array $credentials User credentials (typically email/username and password)
     * @return bool True if credentials are valid, false otherwise
     */
    public function validate(array $credentials = []): bool;

    /**
     * Attempt to authenticate a user using the given credentials
     *
     * @param array $credentials User credentials
     * @param bool $remember Whether to remember the user
     * @return bool True if authentication successful, false otherwise
     */
    public function attempt(array $credentials = [], bool $remember = false): bool;

    /**
     * Attempt to authenticate a user using the given credentials and "remember" them
     *
     * @param array $credentials User credentials
     * @return bool True if authentication successful, false otherwise
     */
    public function attemptWhen(array $credentials = []): bool;

    /**
     * Log a user into the application without sessions or cookies
     *
     * @param mixed $user User instance or user identifier
     * @return bool True if login successful, false otherwise
     */
    public function once(mixed $user): bool;

    /**
     * Log a user into the application
     *
     * @param mixed $user User instance or user identifier
     * @param bool $remember Whether to remember the user
     * @return void
     */
    public function login(mixed $user, bool $remember = false): void;

    /**
     * Log the given user ID into the application
     *
     * @param mixed $id User identifier
     * @param bool $remember Whether to remember the user
     * @return mixed The authenticated user
     */
    public function loginUsingId(mixed $id, bool $remember = false): mixed;

    /**
     * Log the given user ID into the application without sessions or cookies
     *
     * @param mixed $id User identifier
     * @return mixed The authenticated user or false if not found
     */
    public function onceUsingId(mixed $id): mixed;

    /**
     * Determine if the user was authenticated via "remember me" cookie
     *
     * @return bool True if authenticated via remember token, false otherwise
     */
    public function viaRemember(): bool;

    /**
     * Log the user out of the application
     *
     * @return void
     */
    public function logout(): void;

    /**
     * Invalidate other sessions for the current user
     *
     * @param string $password Current user password for verification
     * @return bool True if other sessions were invalidated, false otherwise
     */
    public function logoutOtherDevices(string $password): bool;

    /**
     * Get the user provider used by the guard
     *
     * @return UserProvider The user provider instance
     */
    public function getProvider(): UserProvider;

    /**
     * Set the user provider used by the guard
     *
     * @param UserProvider $provider The user provider instance
     * @return void
     */
    public function setProvider(UserProvider $provider): void;
}