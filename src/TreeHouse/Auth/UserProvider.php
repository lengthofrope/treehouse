<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

/**
 * User Provider Interface
 *
 * Defines the contract for user providers that handle user data retrieval
 * and credential validation. User providers abstract the user storage
 * mechanism (database, API, etc.) from the authentication guards.
 *
 * User providers are responsible for:
 * - Retrieving users by identifier
 * - Retrieving users by credentials
 * - Validating user credentials
 * - Updating remember tokens
 * - Creating user instances from data
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface UserProvider
{
    /**
     * Retrieve a user by their unique identifier
     *
     * @param mixed $identifier User identifier (typically ID)
     * @return mixed User instance or null if not found
     */
    public function retrieveById(mixed $identifier): mixed;

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     *
     * @param mixed $identifier User identifier
     * @param string $token Remember me token
     * @return mixed User instance or null if not found
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed;

    /**
     * Update the "remember me" token for the given user in storage
     *
     * @param mixed $user User instance
     * @param string $token New remember me token
     * @return void
     */
    public function updateRememberToken(mixed $user, string $token): void;

    /**
     * Retrieve a user by the given credentials
     *
     * @param array $credentials User credentials (typically email/username and password)
     * @return mixed User instance or null if not found
     */
    public function retrieveByCredentials(array $credentials): mixed;

    /**
     * Validate a user against the given credentials
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @return bool True if credentials are valid, false otherwise
     */
    public function validateCredentials(mixed $user, array $credentials): bool;

    /**
     * Rehash the user's password if required
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @param bool $force Force rehashing even if not required
     * @return void
     */
    public function rehashPasswordIfRequired(mixed $user, array $credentials, bool $force = false): void;
}