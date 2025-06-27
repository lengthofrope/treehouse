<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Http;

/**
 * Cookie Handler
 * 
 * Provides secure cookie management with proper encoding,
 * security attributes, and validation.
 * 
 * @package LengthOfRope\TreeHouse\Http
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Cookie
{
    /**
     * Cookie name
     */
    protected string $name;

    /**
     * Cookie value
     */
    protected string $value;

    /**
     * Expiration time
     */
    protected int $expires;

    /**
     * Cookie path
     */
    protected string $path;

    /**
     * Cookie domain
     */
    protected string $domain;

    /**
     * Secure flag
     */
    protected bool $secure;

    /**
     * HTTP only flag
     */
    protected bool $httpOnly;

    /**
     * SameSite attribute
     */
    protected string $sameSite;

    /**
     * Create a new Cookie instance
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration time (0 for session cookie)
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Secure flag
     * @param bool $httpOnly HTTP only flag
     * @param string $sameSite SameSite attribute (Strict, Lax, None)
     */
    public function __construct(
        string $name,
        string $value = '',
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->expires = $expires;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }

    /**
     * Create a cookie that expires in the specified minutes
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $minutes Minutes until expiration
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Secure flag
     * @param bool $httpOnly HTTP only flag
     * @param string $sameSite SameSite attribute
     * @return static
     */
    public static function make(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;
        
        return new static(
            $name,
            $value,
            $expires,
            $path,
            $domain,
            $secure,
            $httpOnly,
            $sameSite
        );
    }

    /**
     * Create a cookie that expires forever (5 years)
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Secure flag
     * @param bool $httpOnly HTTP only flag
     * @param string $sameSite SameSite attribute
     * @return static
     */
    public static function forever(
        string $name,
        string $value,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): static {
        return static::make($name, $value, 2628000, $path, $domain, $secure, $httpOnly, $sameSite); // 5 years
    }

    /**
     * Create a cookie that expires immediately (for deletion)
     * 
     * @param string $name Cookie name
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @return static
     */
    public static function forget(string $name, string $path = '/', string $domain = ''): static
    {
        return new static($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Get the cookie name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the cookie value
     * 
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the expiration time
     * 
     * @return int
     */
    public function getExpires(): int
    {
        return $this->expires;
    }

    /**
     * Get the cookie path
     * 
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the cookie domain
     * 
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Check if cookie is secure
     * 
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Check if cookie is HTTP only
     * 
     * @return bool
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * Get the SameSite attribute
     * 
     * @return string
     */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    /**
     * Set the cookie name
     * 
     * @param string $name Cookie name
     * @return static
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the cookie value
     * 
     * @param string $value Cookie value
     * @return static
     */
    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Set the expiration time
     * 
     * @param int $expires Expiration time
     * @return static
     */
    public function setExpires(int $expires): static
    {
        $this->expires = $expires;
        return $this;
    }

    /**
     * Set expiration in minutes from now
     * 
     * @param int $minutes Minutes until expiration
     * @return static
     */
    public function expiresIn(int $minutes): static
    {
        $this->expires = time() + ($minutes * 60);
        return $this;
    }

    /**
     * Set the cookie path
     * 
     * @param string $path Cookie path
     * @return static
     */
    public function setPath(string $path): static
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set the cookie domain
     * 
     * @param string $domain Cookie domain
     * @return static
     */
    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Set the secure flag
     * 
     * @param bool $secure Secure flag
     * @return static
     */
    public function setSecure(bool $secure = true): static
    {
        $this->secure = $secure;
        return $this;
    }

    /**
     * Set the HTTP only flag
     * 
     * @param bool $httpOnly HTTP only flag
     * @return static
     */
    public function setHttpOnly(bool $httpOnly = true): static
    {
        $this->httpOnly = $httpOnly;
        return $this;
    }

    /**
     * Set the SameSite attribute
     * 
     * @param string $sameSite SameSite attribute
     * @return static
     */
    public function setSameSite(string $sameSite): static
    {
        $this->sameSite = $sameSite;
        return $this;
    }

    /**
     * Check if cookie is expired
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires > 0 && $this->expires < time();
    }

    /**
     * Check if cookie is a session cookie
     * 
     * @return bool
     */
    public function isSessionCookie(): bool
    {
        return $this->expires === 0;
    }

    /**
     * Get the max age in seconds
     * 
     * @return int
     */
    public function getMaxAge(): int
    {
        if ($this->expires === 0) {
            return 0;
        }
        
        return max(0, $this->expires - time());
    }

    /**
     * Send the cookie to the browser
     * 
     * @return bool
     */
    public function send(): bool
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie(
            $this->name,
            $this->value,
            [
                'expires' => $this->expires,
                'path' => $this->path,
                'domain' => $this->domain,
                'secure' => $this->secure,
                'httponly' => $this->httpOnly,
                'samesite' => $this->sameSite,
            ]
        );
    }

    /**
     * Get the cookie as a header string
     * 
     * @return string
     */
    public function toHeaderString(): string
    {
        $cookie = $this->name . '=' . rawurlencode($this->value);
        
        if ($this->expires > 0) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $this->expires);
            $cookie .= '; Max-Age=' . $this->getMaxAge();
        }
        
        if ($this->path) {
            $cookie .= '; Path=' . $this->path;
        }
        
        if ($this->domain) {
            $cookie .= '; Domain=' . $this->domain;
        }
        
        if ($this->secure) {
            $cookie .= '; Secure';
        }
        
        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }
        
        if ($this->sameSite) {
            $cookie .= '; SameSite=' . $this->sameSite;
        }
        
        return $cookie;
    }

    /**
     * Validate cookie name
     * 
     * @param string $name Cookie name
     * @return bool
     */
    public static function isValidName(string $name): bool
    {
        // Cookie names cannot contain certain characters
        $invalidChars = [' ', "\t", "\r", "\n", "\013", "\014", '(', ')', '<', '>', '@', ',', ';', ':', '\\', '"', '/', '[', ']', '?', '=', '{', '}'];
        
        foreach ($invalidChars as $char) {
            if (str_contains($name, $char)) {
                return false;
            }
        }
        
        return $name !== '';
    }

    /**
     * Validate SameSite attribute
     * 
     * @param string $sameSite SameSite value
     * @return bool
     */
    public static function isValidSameSite(string $sameSite): bool
    {
        return in_array($sameSite, ['Strict', 'Lax', 'None'], true);
    }

    /**
     * Get cookie from global $_COOKIE array
     * 
     * @param string $name Cookie name
     * @param string|null $default Default value
     * @return string|null
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Check if cookie exists in global $_COOKIE array
     * 
     * @param string $name Cookie name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Convert cookie to string representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toHeaderString();
    }
}