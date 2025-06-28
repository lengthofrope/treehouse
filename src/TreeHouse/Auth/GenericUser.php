<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth;

use LengthOfRope\TreeHouse\Auth\Contracts\Authorizable;

/**
 * Generic User
 *
 * A simple user implementation that can be used with the authentication system.
 * Provides basic user functionality including authentication identifier and
 * password retrieval, as well as remember token management.
 *
 * Now includes authorization capabilities through the Authorizable interface,
 * making it a complete user implementation for both authentication and authorization.
 *
 * This class can be used as a base for custom user implementations or
 * as a standalone user class for authentication and authorization scenarios.
 *
 * @package LengthOfRope\TreeHouse\Auth
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class GenericUser implements Authorizable
{
    use AuthorizableUser;
    /**
     * User attributes
     */
    protected array $attributes = [];

    /**
     * Create a new GenericUser instance
     *
     * @param array $attributes User attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the unique identifier for the user
     *
     * @return mixed
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Get the user's current role(s)
     *
     * Overrides the AuthorizableUser trait method to use the attribute system
     *
     * @return string|array
     */
    public function getRole(): string|array
    {
        return $this->attributes['role'] ?? $this->getDefaultRole();
    }

    /**
     * Set the user's role(s)
     *
     * Overrides the AuthorizableUser trait method to use the attribute system
     *
     * @param string|array $role Role(s) to set
     * @return void
     */
    protected function setRole(string|array $role): void
    {
        $this->attributes['role'] = $role;
    }

    /**
     * Get the password for the user
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return $this->attributes['password'] ?? '';
    }

    /**
     * Get the remember token for the user
     *
     * @return string|null
     */
    public function getRememberToken(): ?string
    {
        return $this->attributes['remember_token'] ?? null;
    }

    /**
     * Set the remember token for the user
     *
     * @param string $token Remember token
     * @return void
     */
    public function setRememberToken(string $token): void
    {
        $this->attributes['remember_token'] = $token;
    }

    /**
     * Get an attribute value
     *
     * @param string $key Attribute key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set an attribute value
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if an attribute exists
     *
     * @param string $key Attribute key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set all attributes
     *
     * @param array $attributes Attributes array
     * @return void
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * Convert the user to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the user to JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->attributes);
    }

    /**
     * Dynamically get an attribute
     *
     * @param string $key Attribute key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set an attribute
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Dynamically check if an attribute is set
     *
     * @param string $key Attribute key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * Dynamically unset an attribute
     *
     * @param string $key Attribute key
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Convert the user to a string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}