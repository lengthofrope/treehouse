<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Http;

use LengthOfRope\TreeHouse\Support\Arr;
use RuntimeException;

/**
 * Session Management
 * 
 * Provides secure session handling with flash data,
 * CSRF protection, and session regeneration capabilities.
 * 
 * @package LengthOfRope\TreeHouse\Http
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class Session
{
    /**
     * Session started flag
     */
    protected bool $started = false;

    /**
     * Session configuration
     */
    protected array $config = [];

    /**
     * Flash data for next request
     */
    protected array $flashData = [];

    /**
     * Create a new Session instance
     * 
     * @param array $config Session configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'name' => 'treehouse_session',
            'lifetime' => 7200, // 2 hours
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'save_path' => '',
        ], $config);
    }

    /**
     * Start the session
     * 
     * @return bool
     * @throws RuntimeException
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->loadFlashData();
            return true;
        }

        // Configure session settings
        $this->configureSession();

        // Start session
        if (!session_start()) {
            throw new RuntimeException('Failed to start session');
        }

        $this->started = true;
        $this->loadFlashData();

        return true;
    }

    /**
     * Check if session is started
     * 
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started && session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Get session ID
     * 
     * @return string
     */
    public function getId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    /**
     * Set session ID
     * 
     * @param string $id Session ID
     * @return bool
     */
    public function setId(string $id): bool
    {
        if ($this->isStarted()) {
            return false;
        }

        return session_id($id) !== false;
    }

    /**
     * Get session name
     * 
     * @return string
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * Set session name
     *
     * @param string $name Session name
     * @return string
     */
    public function setName(string $name): string
    {
        session_name($name);
        return $name;
    }

    /**
     * Regenerate session ID
     * 
     * @param bool $deleteOldSession Delete old session data
     * @return bool
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        $this->ensureStarted();
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get a session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return Arr::get($_SESSION, $key, $default);
    }

    /**
     * Set a session value
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        Arr::set($_SESSION, $key, $value);
    }

    /**
     * Check if session key exists
     * 
     * @param string $key Session key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return Arr::has($_SESSION, $key);
    }

    /**
     * Remove a session value
     * 
     * @param string $key Session key
     * @return void
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        Arr::forget($_SESSION, $key);
    }

    /**
     * Get all session data
     * 
     * @return array
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    /**
     * Clear all session data
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    /**
     * Flash data for the next request
     * 
     * @param string $key Flash key
     * @param mixed $value Flash value
     * @return void
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->flashData[$key] = $value;
        $_SESSION['_flash']['new'][$key] = $value;
    }

    /**
     * Get flash data
     * 
     * @param string $key Flash key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION['_flash']['old'][$key] ?? $default;
    }

    /**
     * Check if flash data exists
     * 
     * @param string $key Flash key
     * @return bool
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION['_flash']['old'][$key]);
    }

    /**
     * Keep flash data for another request
     * 
     * @param array|string $keys Flash keys to keep
     * @return void
     */
    public function keepFlash(array|string $keys): void
    {
        $this->ensureStarted();
        
        $keys = is_array($keys) ? $keys : [$keys];
        
        foreach ($keys as $key) {
            if (isset($_SESSION['_flash']['old'][$key])) {
                $_SESSION['_flash']['new'][$key] = $_SESSION['_flash']['old'][$key];
            }
        }
    }

    /**
     * Reflash all flash data
     * 
     * @return void
     */
    public function reflash(): void
    {
        $this->ensureStarted();
        
        if (isset($_SESSION['_flash']['old'])) {
            $_SESSION['_flash']['new'] = array_merge(
                $_SESSION['_flash']['new'] ?? [],
                $_SESSION['_flash']['old']
            );
        }
    }

    /**
     * Get and remove a value (pull)
     * 
     * @param string $key Session key
     * @param mixed $default Default value
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * Increment a session value
     * 
     * @param string $key Session key
     * @param int $value Increment value
     * @return int
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        return $new;
    }

    /**
     * Decrement a session value
     * 
     * @param string $key Session key
     * @param int $value Decrement value
     * @return int
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * Get the CSRF token
     * 
     * @return string
     */
    public function token(): string
    {
        $this->ensureStarted();
        
        if (!$this->has('_token')) {
            $this->regenerateToken();
        }
        
        return $this->get('_token');
    }

    /**
     * Regenerate the CSRF token
     * 
     * @return string
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_token', $token);
        return $token;
    }

    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        $sessionToken = $this->token();
        return hash_equals($sessionToken, $token);
    }

    /**
     * Save session data
     * 
     * @return void
     */
    public function save(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        // Process flash data
        $this->processFlashData();

        // Write and close session
        session_write_close();
        $this->started = false;
    }

    /**
     * Destroy the session
     * 
     * @return bool
     */
    public function destroy(): bool
    {
        $this->ensureStarted();
        
        // Clear session data
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        $result = session_destroy();
        $this->started = false;
        
        return $result;
    }

    /**
     * Configure session settings
     * 
     * @return void
     */
    protected function configureSession(): void
    {
        // Set session name
        session_name($this->config['name']);
        
        // Set save path if specified
        if (!empty($this->config['save_path'])) {
            session_save_path($this->config['save_path']);
        }
        
        // Configure cookie parameters
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ]);
        
        // Set additional ini settings
        ini_set('session.gc_maxlifetime', (string) $this->config['lifetime']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', $this->config['httponly'] ? '1' : '0');
        ini_set('session.cookie_secure', $this->config['secure'] ? '1' : '0');
    }

    /**
     * Load flash data from previous request
     * 
     * @return void
     */
    protected function loadFlashData(): void
    {
        if (isset($_SESSION['_flash']['new'])) {
            $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'];
            $_SESSION['_flash']['new'] = [];
        }
    }

    /**
     * Process flash data for next request
     * 
     * @return void
     */
    protected function processFlashData(): void
    {
        // Clear old flash data
        unset($_SESSION['_flash']['old']);
        
        // Move new flash data to current
        if (empty($_SESSION['_flash']['new'])) {
            unset($_SESSION['_flash']);
        }
    }

    /**
     * Ensure session is started
     * 
     * @return void
     * @throws RuntimeException
     */
    protected function ensureStarted(): void
    {
        if (!$this->isStarted()) {
            $this->start();
        }
    }
}