<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt\Algorithms;

/**
 * JWT Algorithm Interface
 *
 * Defines the contract for JWT signature algorithms. All signature algorithms
 * must implement this interface to provide signing and verification capabilities.
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt\Algorithms
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface AlgorithmInterface
{
    /**
     * Get the algorithm name
     *
     * @return string Algorithm name (e.g., 'HS256', 'RS256', 'ES256')
     */
    public function getAlgorithmName(): string;

    /**
     * Sign the given message with the provided key
     *
     * @param string $message Message to sign
     * @param string $key Signing key
     * @return string Signature
     */
    public function sign(string $message, string $key): string;

    /**
     * Verify the signature of the given message
     *
     * @param string $message Original message
     * @param string $signature Signature to verify
     * @param string $key Verification key
     * @return bool True if signature is valid
     */
    public function verify(string $message, string $signature, string $key): bool;
}