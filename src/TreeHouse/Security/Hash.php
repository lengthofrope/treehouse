<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Security;

/**
 * Secure Password Hashing
 *
 * Provides secure password hashing using PHP's built-in password functions.
 * Uses the PASSWORD_DEFAULT algorithm (currently bcrypt) with automatic
 * salt generation and configurable options.
 *
 * Features:
 * - Secure password hashing with automatic salt generation
 * - Password verification with timing-safe comparison
 * - Hash rehashing detection for security updates
 * - Hash information extraction
 * - Configurable hashing options and algorithms
 *
 * @package LengthOfRope\TreeHouse\Security
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Hash
{
    /**
     * Create a hash of the given password
     *
     * Generates a secure hash of the password using PHP's password_hash() function.
     * Uses PASSWORD_DEFAULT algorithm unless specified otherwise. Automatically
     * generates a cryptographically secure salt.
     *
     * @param string $password Password to hash
     * @param array $options Hashing options (e.g., ['cost' => 12] for bcrypt)
     * @param mixed $algorithm Hashing algorithm (default: PASSWORD_DEFAULT)
     * @return string Hashed password
     * @throws \InvalidArgumentException If password is empty
     */
    public function make(string $password, array $options = [], $algorithm = null): string
    {
        if (empty($password)) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }

        $algorithm = $algorithm ?? PASSWORD_DEFAULT;
        
        return password_hash($password, $algorithm, $options);
    }

    /**
     * Check if the given password matches the hash
     *
     * Verifies a password against its hash using PHP's password_verify() function.
     * This function is timing-safe and handles all supported password algorithms.
     *
     * @param string $password Plain text password to verify
     * @param string $hash Hashed password to verify against
     * @return bool True if password matches hash, false otherwise
     */
    public function check(string $password, string $hash): bool
    {
        if (empty($password) || empty($hash)) {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Check if the given hash needs to be rehashed
     *
     * Determines if a hash was created with different options than the current
     * default settings. Useful for upgrading password security when algorithm
     * or cost parameters change.
     *
     * @param string $hash Existing password hash
     * @param array $options Current hashing options to compare against
     * @return bool True if hash should be regenerated, false otherwise
     */
    public function needsRehash(string $hash, array $options = []): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT, $options);
    }

    /**
     * Get information about the given hash
     *
     * Returns information about the password hash including the algorithm used,
     * algorithm options, and algorithm name. Useful for debugging and
     * security auditing.
     *
     * @param string $hash Password hash to analyze
     * @return array Hash information including 'algo', 'algoName', and 'options'
     */
    public function getInfo(string $hash): array
    {
        return password_get_info($hash);
    }
}