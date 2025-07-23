<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Auth\Jwt;

use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;
use LengthOfRope\TreeHouse\Errors\Exceptions\SystemException;
use LengthOfRope\TreeHouse\Security\Encryption;
use LengthOfRope\TreeHouse\Support\Carbon;

/**
 * JWT Key Rotation Manager
 *
 * Manages automatic JWT signing key rotation for enhanced security.
 * Supports multiple key algorithms and provides seamless key transitions
 * with configurable rotation schedules and grace periods.
 *
 * Features:
 * - Automatic key generation and rotation
 * - Multi-algorithm support (HS256, RS256, ES256)
 * - Configurable rotation schedules
 * - Grace period for token validation
 * - Key versioning and tracking
 * - Secure key storage and retrieval
 *
 * @package LengthOfRope\TreeHouse\Auth\Jwt
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class KeyRotationManager
{
    private const KEY_PREFIX = 'jwt_key_';
    private const CURRENT_KEY_ID = 'jwt_current_key_id';
    private const KEY_HISTORY = 'jwt_key_history';
    
    /**
     * Default rotation settings
     */
    private const DEFAULTS = [
        'rotation_interval' => 2592000, // 30 days
        'grace_period' => 604800,      // 7 days
        'max_keys' => 10,              // Maximum keys to keep in history
        'auto_rotation' => true,       // Enable automatic rotation
        'key_strength' => 256,         // Key strength in bits
    ];

    private CacheManager $cache;
    private array $config;
    private Encryption $encryption;

    /**
     * Create new key rotation manager
     *
     * @param CacheManager $cache Cache manager for key storage
     * @param array $config Rotation configuration
     */
    public function __construct(CacheManager $cache, array $config = [])
    {
        $this->cache = $cache;
        $this->config = array_merge(self::DEFAULTS, $config);
        $this->encryption = new Encryption($this->getEncryptionKey());
        
        $this->validateConfig();
    }

    /**
     * Get current signing key
     *
     * @param string $algorithm Algorithm for key generation
     * @return array Key data with metadata
     * @throws RuntimeException If no current key exists
     */
    public function getCurrentKey(string $algorithm = 'HS256'): array
    {
        $keyId = $this->getCurrentKeyId();
        
        if (!$keyId) {
            // No current key, generate one
            return $this->generateNewKey($algorithm);
        }
        
        $keyData = $this->getKeyById($keyId);
        
        if (!$keyData) {
            // Key missing, generate new one
            return $this->generateNewKey($algorithm);
        }
        
        // Check if rotation is needed
        if ($this->needsRotation($keyData) && $this->config['auto_rotation']) {
            return $this->rotateKey($algorithm);
        }
        
        return $keyData;
    }

    /**
     * Get key by ID for validation
     *
     * @param string $keyId Key identifier
     * @return array|null Key data or null if not found
     */
    public function getKeyById(string $keyId): ?array
    {
        $cacheKey = self::KEY_PREFIX . $keyId;
        $encryptedData = $this->cache->get($cacheKey);
        
        if (!$encryptedData) {
            return null;
        }
        
        try {
            $keyData = $this->encryption->decryptPayload($encryptedData);
            
            // Check if key is within grace period
            if ($this->isKeyExpired($keyData)) {
                $this->removeKey($keyId);
                return null;
            }
            
            return $keyData;
        } catch (\Exception $e) {
            // Key corrupted, remove it
            $this->removeKey($keyId);
            return null;
        }
    }

    /**
     * Manually rotate the signing key
     *
     * @param string $algorithm Algorithm for new key
     * @return array New key data
     */
    public function rotateKey(string $algorithm = 'HS256'): array
    {
        $oldKeyId = $this->getCurrentKeyId();
        $newKey = $this->generateNewKey($algorithm);
        
        // Update key history
        if ($oldKeyId) {
            $this->addToHistory($oldKeyId);
        }
        
        return $newKey;
    }

    /**
     * Generate new signing key
     *
     * @param string $algorithm Algorithm for key generation
     * @return array Generated key data
     * @throws InvalidArgumentException If algorithm is not supported
     */
    public function generateNewKey(string $algorithm): array
    {
        $keyId = $this->generateKeyId();
        $keyData = [
            'id' => $keyId,
            'algorithm' => $algorithm,
            'created_at' => Carbon::now()->getTimestamp(),
            'expires_at' => Carbon::now()->addSeconds($this->config['rotation_interval'])->getTimestamp(),
            'grace_expires_at' => Carbon::now()->addSeconds(
                $this->config['rotation_interval'] + $this->config['grace_period']
            )->getTimestamp(),
        ];

        // Generate key material based on algorithm
        switch ($algorithm) {
            case 'HS256':
            case 'HS384':
            case 'HS512':
                $keyData['secret'] = $this->generateSymmetricKey();
                break;
                
            case 'RS256':
            case 'RS384':
            case 'RS512':
                $keys = $this->generateRsaKeyPair();
                $keyData['private_key'] = $keys['private'];
                $keyData['public_key'] = $keys['public'];
                break;
                
            case 'ES256':
            case 'ES384':
            case 'ES512':
                $keys = $this->generateEcdsaKeyPair();
                $keyData['private_key'] = $keys['private'];
                $keyData['public_key'] = $keys['public'];
                break;
                
            default:
                throw new InvalidArgumentException("Unsupported algorithm: {$algorithm}", 'UNSUPPORTED_ALGORITHM');
        }

        // Store encrypted key
        $this->storeKey($keyId, $keyData);
        $this->setCurrentKeyId($keyId);
        
        // Clean up old keys
        $this->cleanupOldKeys();
        
        return $keyData;
    }

    /**
     * Get all valid keys for token validation
     *
     * @return array Array of key data indexed by key ID
     */
    public function getValidKeys(): array
    {
        $keys = [];
        $currentKeyId = $this->getCurrentKeyId();
        $history = $this->getKeyHistory();
        
        // Add current key
        if ($currentKeyId) {
            $keyData = $this->getKeyById($currentKeyId);
            if ($keyData) {
                $keys[$currentKeyId] = $keyData;
            }
        }
        
        // Add historical keys within grace period
        foreach ($history as $keyId) {
            if ($keyId !== $currentKeyId) {
                $keyData = $this->getKeyById($keyId);
                if ($keyData && !$this->isKeyExpired($keyData)) {
                    $keys[$keyId] = $keyData;
                }
            }
        }
        
        return $keys;
    }

    /**
     * Check if key rotation is needed
     *
     * @param array $keyData Key data to check
     * @return bool True if rotation is needed
     */
    public function needsRotation(array $keyData): bool
    {
        return Carbon::now()->getTimestamp() >= $keyData['expires_at'];
    }

    /**
     * Check if key is expired (beyond grace period)
     *
     * @param array $keyData Key data to check
     * @return bool True if key is expired
     */
    public function isKeyExpired(array $keyData): bool
    {
        return Carbon::now()->getTimestamp() >= $keyData['grace_expires_at'];
    }

    /**
     * Get key rotation statistics
     *
     * @return array Rotation statistics
     */
    public function getRotationStats(): array
    {
        $currentKeyId = $this->getCurrentKeyId();
        $currentKey = $currentKeyId ? $this->getKeyById($currentKeyId) : null;
        $validKeys = $this->getValidKeys();
        $history = $this->getKeyHistory();
        
        return [
            'current_key_id' => $currentKeyId,
            'current_key_age' => $currentKey ? Carbon::now()->getTimestamp() - $currentKey['created_at'] : 0,
            'time_until_rotation' => $currentKey ? max(0, $currentKey['expires_at'] - Carbon::now()->getTimestamp()) : 0,
            'valid_keys_count' => count($validKeys),
            'total_keys_in_history' => count($history),
            'auto_rotation_enabled' => $this->config['auto_rotation'],
            'rotation_interval' => $this->config['rotation_interval'],
            'grace_period' => $this->config['grace_period'],
        ];
    }

    /**
     * Manually purge expired keys
     *
     * @return int Number of keys purged
     */
    public function purgeExpiredKeys(): int
    {
        $history = $this->getKeyHistory();
        $purged = 0;
        
        foreach ($history as $keyId) {
            $keyData = $this->getKeyById($keyId);
            if (!$keyData || $this->isKeyExpired($keyData)) {
                $this->removeKey($keyId);
                $purged++;
            }
        }
        
        $this->updateKeyHistory();
        return $purged;
    }

    /**
     * Generate unique key identifier
     *
     * @return string Key ID
     */
    private function generateKeyId(): string
    {
        return 'key_' . bin2hex(random_bytes(16)) . '_' . Carbon::now()->getTimestamp();
    }

    /**
     * Generate symmetric key for HMAC algorithms
     *
     * @return string Base64 encoded key
     */
    private function generateSymmetricKey(): string
    {
        $keyLength = max(32, intval($this->config['key_strength'] / 8));
        return base64_encode(random_bytes($keyLength));
    }

    /**
     * Generate RSA key pair
     *
     * @return array Private and public keys
     * @throws RuntimeException If key generation fails
     */
    private function generateRsaKeyPair(): array
    {
        $keySize = max(2048, $this->config['key_strength'] * 8);
        
        $config = [
            'private_key_bits' => $keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        $resource = openssl_pkey_new($config);
        if (!$resource) {
            throw new SystemException('Failed to generate RSA key pair', 'RSA_GENERATION_FAILED');
        }
        
        openssl_pkey_export($resource, $privateKey);
        $publicKey = openssl_pkey_get_details($resource)['key'];
        
        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }

    /**
     * Generate ECDSA key pair
     *
     * @return array Private and public keys
     * @throws RuntimeException If key generation fails
     */
    private function generateEcdsaKeyPair(): array
    {
        $curve = $this->config['key_strength'] >= 384 ? 'secp384r1' : 'secp256r1';
        
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => $curve,
        ];
        
        $resource = openssl_pkey_new($config);
        if (!$resource) {
            throw new SystemException('Failed to generate ECDSA key pair', 'ECDSA_GENERATION_FAILED');
        }
        
        openssl_pkey_export($resource, $privateKey);
        $publicKey = openssl_pkey_get_details($resource)['key'];
        
        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }

    /**
     * Store encrypted key in cache
     *
     * @param string $keyId Key identifier
     * @param array $keyData Key data to store
     */
    private function storeKey(string $keyId, array $keyData): void
    {
        $cacheKey = self::KEY_PREFIX . $keyId;
        $encryptedData = $this->encryption->encryptPayload($keyData, $keyData['grace_expires_at']);
        
        $this->cache->put($cacheKey, $encryptedData, $this->config['grace_period']);
    }

    /**
     * Remove key from storage
     *
     * @param string $keyId Key identifier
     */
    private function removeKey(string $keyId): void
    {
        $cacheKey = self::KEY_PREFIX . $keyId;
        $this->cache->forget($cacheKey);
    }

    /**
     * Get current key ID
     *
     * @return string|null Current key ID
     */
    private function getCurrentKeyId(): ?string
    {
        return $this->cache->get(self::CURRENT_KEY_ID);
    }

    /**
     * Set current key ID
     *
     * @param string $keyId Key identifier
     */
    private function setCurrentKeyId(string $keyId): void
    {
        $this->cache->forever(self::CURRENT_KEY_ID, $keyId);
    }

    /**
     * Get key history
     *
     * @return array Array of key IDs
     */
    private function getKeyHistory(): array
    {
        return $this->cache->get(self::KEY_HISTORY, []);
    }

    /**
     * Add key to history
     *
     * @param string $keyId Key identifier
     */
    private function addToHistory(string $keyId): void
    {
        $history = $this->getKeyHistory();
        
        if (!in_array($keyId, $history)) {
            array_unshift($history, $keyId);
        }
        
        // Limit history size
        if (count($history) > $this->config['max_keys']) {
            $history = array_slice($history, 0, $this->config['max_keys']);
        }
        
        $this->cache->forever(self::KEY_HISTORY, $history);
    }

    /**
     * Update key history by removing non-existent keys
     */
    private function updateKeyHistory(): void
    {
        $history = $this->getKeyHistory();
        $validHistory = [];
        
        foreach ($history as $keyId) {
            if ($this->cache->has(self::KEY_PREFIX . $keyId)) {
                $validHistory[] = $keyId;
            }
        }
        
        $this->cache->forever(self::KEY_HISTORY, $validHistory);
    }

    /**
     * Clean up old keys beyond maximum
     */
    private function cleanupOldKeys(): void
    {
        $history = $this->getKeyHistory();
        
        if (count($history) > $this->config['max_keys']) {
            $toRemove = array_slice($history, $this->config['max_keys']);
            
            foreach ($toRemove as $keyId) {
                $this->removeKey($keyId);
            }
            
            $this->updateKeyHistory();
        }
    }

    /**
     * Get encryption key for key storage
     *
     * @return string Encryption key
     */
    private function getEncryptionKey(): string
    {
        // Use configured key or generate from environment
        if (isset($this->config['storage_key'])) {
            return $this->config['storage_key'];
        }
        
        // Use app key as fallback
        $appKey = $_ENV['APP_KEY'] ?? 'default-key-for-jwt-storage';
        return hash('sha256', $appKey . 'jwt-key-rotation');
    }

    /**
     * Validate configuration
     *
     * @throws InvalidArgumentException If configuration is invalid
     */
    private function validateConfig(): void
    {
        if ($this->config['rotation_interval'] <= 0) {
            throw new InvalidArgumentException('Rotation interval must be positive', 'INVALID_ROTATION_INTERVAL');
        }
        
        if ($this->config['grace_period'] < 0) {
            throw new InvalidArgumentException('Grace period cannot be negative', 'INVALID_GRACE_PERIOD');
        }
        
        if ($this->config['max_keys'] <= 0) {
            throw new InvalidArgumentException('Max keys must be positive', 'INVALID_MAX_KEYS');
        }
        
        if ($this->config['key_strength'] < 128) {
            throw new InvalidArgumentException('Key strength must be at least 128 bits', 'INVALID_KEY_STRENGTH');
        }
    }
}