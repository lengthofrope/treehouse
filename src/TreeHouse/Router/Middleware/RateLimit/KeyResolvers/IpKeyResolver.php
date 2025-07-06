<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers;

use LengthOfRope\TreeHouse\Http\Request;

/**
 * IP Address Key Resolver
 *
 * Generates rate limiting keys based on the client's IP address.
 * Supports proxy headers and IPv6 normalization.
 *
 * @package LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class IpKeyResolver implements KeyResolverInterface
{
    /**
     * Resolver configuration
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Create a new IP key resolver
     *
     * @param array<string, mixed> $config Resolver configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Resolve a rate limiting key from the request
     *
     * @param Request $request HTTP request
     * @return string|null Rate limiting key, or null if IP cannot be determined
     */
    public function resolveKey(Request $request): ?string
    {
        $ip = $this->getClientIp($request);
        
        if ($ip === null) {
            return null;
        }

        // Normalize IPv6 addresses if configured
        if ($this->config['normalize_ipv6']) {
            $ip = $this->normalizeIpv6($ip);
        }

        // Apply subnet masking if configured
        if ($this->config['subnet_mask_ipv4'] || $this->config['subnet_mask_ipv6']) {
            $ip = $this->applySubnetMask($ip);
        }

        return "ip:{$ip}";
    }

    /**
     * Get the resolver name
     */
    public function getName(): string
    {
        return 'ip';
    }

    /**
     * Check if this resolver can handle the request
     *
     * @param Request $request HTTP request
     * @return bool True if this resolver can generate a key for the request
     */
    public function canResolve(Request $request): bool
    {
        return $this->getClientIp($request) !== null;
    }


    /**
     * Get resolver-specific configuration options
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'trust_proxies' => false,
            'proxy_headers' => [
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'HTTP_CLIENT_IP',
                'HTTP_X_CLUSTER_CLIENT_IP',
            ],
            'normalize_ipv6' => true,
            'subnet_mask_ipv4' => null, // e.g., 24 for /24 subnet
            'subnet_mask_ipv6' => null, // e.g., 64 for /64 subnet
        ];
    }

    /**
     * Set resolver configuration
     *
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the client IP address
     *
     * @param Request $request HTTP request
     * @return string|null Client IP address
     */
    private function getClientIp(Request $request): ?string
    {
        // If proxy trust is enabled, check proxy headers first
        if ($this->config['trust_proxies']) {
            foreach ($this->config['proxy_headers'] as $header) {
                $ip = $request->server($header);
                if ($ip !== null) {
                    // Handle comma-separated IPs (X-Forwarded-For)
                    if (str_contains($ip, ',')) {
                        $ip = trim(explode(',', $ip)[0]);
                    }
                    
                    if ($this->isValidIp($ip)) {
                        return $ip;
                    }
                }
            }
        }

        // Fall back to REMOTE_ADDR
        $ip = $request->server('REMOTE_ADDR');
        
        return $this->isValidIp($ip) ? $ip : null;
    }

    /**
     * Check if an IP address is valid
     *
     * @param string|null $ip IP address to validate
     * @return bool True if valid IP address
     */
    private function isValidIp(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            || filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Normalize IPv6 addresses
     *
     * @param string $ip IP address
     * @return string Normalized IP address
     */
    private function normalizeIpv6(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Convert to full IPv6 format
            return inet_ntop(inet_pton($ip));
        }

        return $ip;
    }

    /**
     * Apply subnet masking to IP address
     *
     * @param string $ip IP address
     * @return string Masked IP address
     */
    private function applySubnetMask(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->config['subnet_mask_ipv4']) {
            return $this->maskIpv4($ip, $this->config['subnet_mask_ipv4']);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && $this->config['subnet_mask_ipv6']) {
            return $this->maskIpv6($ip, $this->config['subnet_mask_ipv6']);
        }

        return $ip;
    }

    /**
     * Apply subnet mask to IPv4 address
     *
     * @param string $ip IPv4 address
     * @param int $mask Subnet mask (e.g., 24 for /24)
     * @return string Masked IPv4 address
     */
    private function maskIpv4(string $ip, int $mask): string
    {
        $long = ip2long($ip);
        $maskLong = -1 << (32 - $mask);
        $maskedLong = $long & $maskLong;
        
        return long2ip($maskedLong);
    }

    /**
     * Apply subnet mask to IPv6 address
     *
     * @param string $ip IPv6 address
     * @param int $mask Subnet mask (e.g., 64 for /64)
     * @return string Masked IPv6 address
     */
    private function maskIpv6(string $ip, int $mask): string
    {
        $binary = inet_pton($ip);
        $bytes = str_split($binary);
        
        $maskBytes = intval($mask / 8);
        $maskBits = $mask % 8;
        
        // Zero out bytes beyond the mask
        for ($i = $maskBytes; $i < 16; $i++) {
            if ($i === $maskBytes && $maskBits > 0) {
                // Partial byte masking
                $bytes[$i] = chr(ord($bytes[$i]) & (0xFF << (8 - $maskBits)));
            } else {
                $bytes[$i] = "\0";
            }
        }
        
        return inet_ntop(implode('', $bytes));
    }
}