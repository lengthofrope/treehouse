<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Contracts;

/**
 * Authorizable Contract
 *
 * Defines the interface for user entities that support role-based authorization.
 * This contract ensures consistent authorization methods across different user implementations.
 *
 * @package LengthOfRope\TreeHouse\Auth\Contracts
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface Authorizable
{
    /**
     * Check if the user has a specific role
     *
     * @param string $role Role name to check
     * @return bool
     */
    public function hasRole(string $role): bool;

    /**
     * Check if the user has any of the given roles
     *
     * @param array $roles Array of role names to check
     * @return bool
     */
    public function hasAnyRole(array $roles): bool;

    /**
     * Check if the user can perform a specific permission
     *
     * @param string $permission Permission name to check
     * @return bool
     */
    public function can(string $permission): bool;

    /**
     * Check if the user cannot perform a specific permission
     *
     * @param string $permission Permission name to check
     * @return bool
     */
    public function cannot(string $permission): bool;

    /**
     * Assign a role to the user
     *
     * @param string $role Role name to assign
     * @return void
     */
    public function assignRole(string $role): void;

    /**
     * Remove a role from the user
     *
     * @param string $role Role name to remove
     * @return void
     */
    public function removeRole(string $role): void;

    /**
     * Get the user's current role(s)
     *
     * @return string|array
     */
    public function getRole(): string|array;

    /**
     * Get the unique identifier for the user
     *
     * @return mixed
     */
    public function getAuthIdentifier(): mixed;
}