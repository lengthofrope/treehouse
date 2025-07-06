<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers;

use LengthOfRope\TreeHouse\Http\Request;

/**
 * Key Resolver Interface
 *
 * Defines the contract for generating rate limiting keys from HTTP requests.
 * Different resolvers can identify users by IP address, user ID, API keys,
 * or custom logic.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
interface KeyResolverInterface
{
    /**
     * Resolve a rate limiting key from the request
     *
     * @param Request $request HTTP request
     * @return string|null Rate limiting key, or null if key cannot be resolved
     */
    public function resolveKey(Request $request): ?string;

    /**
     * Get the resolver name
     */
    public function getName(): string;

    /**
     * Check if this resolver can handle the request
     *
     * @param Request $request HTTP request
     * @return bool True if this resolver can generate a key for the request
     */
    public function canResolve(Request $request): bool;

    /**
     * Get resolver-specific configuration options
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array;

    /**
     * Set resolver configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void;
}