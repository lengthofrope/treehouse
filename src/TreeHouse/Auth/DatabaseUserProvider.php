<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Database\Connection;
use LengthOfRope\TreeHouse\Database\QueryBuilder;
use LengthOfRope\TreeHouse\Security\Hash;
use LengthOfRope\TreeHouse\Support\Str;
use InvalidArgumentException;

/**
 * Database User Provider
 *
 * Implements user authentication using database storage.
 * Handles user retrieval, credential validation, and remember token management
 * using the TreeHouse database layer.
 *
 * Features:
 * - Database-backed user authentication
 * - Flexible user model support
 * - Remember token management
 * - Password rehashing support
 * - Configurable table and column names
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class DatabaseUserProvider implements UserProvider
{
    /**
     * The hash instance
     */
    protected Hash $hash;

    /**
     * The database connection
     */
    protected Connection $connection;

    /**
     * The user table name
     */
    protected string $table;

    /**
     * The user model class
     */
    protected ?string $model;

    /**
     * Create a new DatabaseUserProvider instance
     *
     * @param Hash $hash Hash instance
     * @param array $config Provider configuration
     */
    public function __construct(Hash $hash, array $config)
    {
        $this->hash = $hash;
        $this->table = $config['table'] ?? 'users';
        $this->model = $config['model'] ?? null;
        
        // Create database connection with config
        $dbConfig = $config['connection'] ?? [];
        $this->connection = new Connection($dbConfig);
    }

    /**
     * Retrieve a user by their unique identifier
     *
     * @param mixed $identifier User identifier
     * @return mixed
     */
    public function retrieveById(mixed $identifier): mixed
    {
        $query = $this->newModelQuery();
        
        $user = $query->where('id', '=', $identifier)->first();
        
        return $user ? $this->getGenericUser($user) : null;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     *
     * @param mixed $identifier User identifier
     * @param string $token Remember me token
     * @return mixed
     */
    public function retrieveByToken(mixed $identifier, string $token): mixed
    {
        $query = $this->newModelQuery();
        
        $user = $query->where('id', '=', $identifier)
                     ->where('remember_token', '=', $token)
                     ->first();
        
        return $user ? $this->getGenericUser($user) : null;
    }

    /**
     * Update the "remember me" token for the given user in storage
     *
     * @param mixed $user User instance
     * @param string $token New remember me token
     * @return void
     */
    public function updateRememberToken(mixed $user, string $token): void
    {
        $query = $this->newModelQuery();
        
        $query->where('id', '=', $this->getUserId($user))
              ->update(['remember_token' => $token]);
    }

    /**
     * Retrieve a user by the given credentials
     *
     * @param array $credentials User credentials
     * @return mixed
     */
    public function retrieveByCredentials(array $credentials): mixed
    {
        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if ($key !== 'password') {
                $query->where($key, '=', $value);
            }
        }

        $user = $query->first();
        
        return $user ? $this->getGenericUser($user) : null;
    }

    /**
     * Validate a user against the given credentials
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @return bool
     */
    public function validateCredentials(mixed $user, array $credentials): bool
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        $password = $this->getUserPassword($user);
        
        return $this->hash->check($credentials['password'], $password);
    }

    /**
     * Rehash the user's password if required
     *
     * @param mixed $user User instance
     * @param array $credentials User credentials
     * @param bool $force Force rehashing
     * @return void
     */
    public function rehashPasswordIfRequired(mixed $user, array $credentials, bool $force = false): void
    {
        if (!isset($credentials['password'])) {
            return;
        }

        $password = $this->getUserPassword($user);
        
        if ($force || $this->hash->needsRehash($password)) {
            $newHash = $this->hash->make($credentials['password']);
            
            $query = $this->newModelQuery();
            $query->where('id', '=', $this->getUserId($user))
                  ->update(['password' => $newHash]);
        }
    }

    /**
     * Create a new query builder for the user table
     *
     * @return QueryBuilder
     */
    protected function newModelQuery(): QueryBuilder
    {
        return new QueryBuilder($this->connection, $this->table);
    }

    /**
     * Get a generic user instance from the database result
     *
     * @param array $user User data from database
     * @return GenericUser
     */
    protected function getGenericUser(array $user): GenericUser
    {
        if ($this->model) {
            // If a model class is specified, create an instance of it
            $modelClass = $this->model;
            return new $modelClass($user);
        }
        
        // Otherwise, return a generic user
        return new GenericUser($user);
    }

    /**
     * Get the user ID from a user instance
     *
     * @param mixed $user User instance
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
     * Get the user password from a user instance
     *
     * @param mixed $user User instance
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getUserPassword(mixed $user): string
    {
        if (is_object($user)) {
            if (method_exists($user, 'getAuthPassword')) {
                return $user->getAuthPassword();
            }
            
            if (isset($user->password)) {
                return $user->password;
            }
        }

        if (is_array($user) && isset($user['password'])) {
            return $user['password'];
        }

        throw new InvalidArgumentException('User must have a password or implement getAuthPassword method');
    }

    /**
     * Get the database connection
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}