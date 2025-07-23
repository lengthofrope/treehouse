<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\KeyRotationManager;
use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * Test KeyRotationManager functionality
 */
class KeyRotationManagerTest extends TestCase
{
    private CacheManager $cache;
    private KeyRotationManager $keyManager;

    protected function setUp(): void
    {
        $this->cache = new CacheManager();
        $this->keyManager = new KeyRotationManager($this->cache);
    }

    public function testCanCreateKeyRotationManager(): void
    {
        $this->assertInstanceOf(KeyRotationManager::class, $this->keyManager);
    }

    public function testCanGenerateNewKey(): void
    {
        $key = $this->keyManager->generateNewKey('HS256');
        
        $this->assertIsArray($key);
        $this->assertArrayHasKey('id', $key);
        $this->assertArrayHasKey('key', $key);
        $this->assertArrayHasKey('algorithm', $key);
        $this->assertArrayHasKey('created_at', $key);
        $this->assertArrayHasKey('expires_at', $key);
        $this->assertEquals('HS256', $key['algorithm']);
        $this->assertIsString($key['key']);
        $this->assertGreaterThan(0, strlen($key['key']));
    }

    public function testCanGetCurrentKey(): void
    {
        // Generate a key first
        $this->keyManager->generateNewKey('HS256');
        
        $currentKey = $this->keyManager->getCurrentKey('HS256');
        
        $this->assertIsArray($currentKey);
        $this->assertArrayHasKey('id', $currentKey);
        $this->assertArrayHasKey('key', $currentKey);
        $this->assertEquals('HS256', $currentKey['algorithm']);
    }

    public function testCanRotateKey(): void
    {
        // Generate initial key
        $initialKey = $this->keyManager->generateNewKey('HS256');
        
        // Rotate to new key
        $newKey = $this->keyManager->rotateKey('HS256');
        
        $this->assertIsArray($newKey);
        $this->assertNotEquals($initialKey['id'], $newKey['id']);
        $this->assertNotEquals($initialKey['key'], $newKey['key']);
        $this->assertEquals('HS256', $newKey['algorithm']);
    }

    public function testCanGetValidKeys(): void
    {
        // Generate a few keys
        $this->keyManager->generateNewKey('HS256');
        $this->keyManager->rotateKey('HS256');
        
        $validKeys = $this->keyManager->getValidKeys('HS256');
        
        $this->assertIsArray($validKeys);
        $this->assertGreaterThanOrEqual(1, count($validKeys));
        
        foreach ($validKeys as $key) {
            $this->assertArrayHasKey('id', $key);
            $this->assertArrayHasKey('key', $key);
            $this->assertEquals('HS256', $key['algorithm']);
        }
    }

    public function testCanGetRotationStats(): void
    {
        // Generate a key
        $this->keyManager->generateNewKey('HS256');
        
        $stats = $this->keyManager->getRotationStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current_key_id', $stats);
        $this->assertArrayHasKey('current_key_age', $stats);
        $this->assertArrayHasKey('time_until_rotation', $stats);
        $this->assertArrayHasKey('valid_keys_count', $stats);
        $this->assertArrayHasKey('total_rotations', $stats);
    }

    public function testThrowsExceptionForInvalidAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->keyManager->generateNewKey('INVALID');
    }

    public function testCanHandleMultipleRotations(): void
    {
        // Generate some keys and rotate multiple times
        $this->keyManager->generateNewKey('HS256');
        $key1 = $this->keyManager->rotateKey('HS256');
        $key2 = $this->keyManager->rotateKey('HS256');
        
        // Verify we have different keys
        $this->assertNotEquals($key1['id'], $key2['id']);
        $this->assertNotEquals($key1['key'], $key2['key']);
        
        // Verify we still have valid keys
        $validKeys = $this->keyManager->getValidKeys('HS256');
        $this->assertGreaterThanOrEqual(1, count($validKeys));
    }

    public function testSupportsMultipleAlgorithms(): void
    {
        $algorithms = ['HS256', 'RS256', 'ES256'];
        
        foreach ($algorithms as $algorithm) {
            $key = $this->keyManager->generateNewKey($algorithm);
            $this->assertEquals($algorithm, $key['algorithm']);
        }
    }

    public function testKeyHasProperStructure(): void
    {
        $key = $this->keyManager->generateNewKey('HS256');
        
        // Verify key structure
        $this->assertIsString($key['id']);
        $this->assertIsString($key['key']);
        $this->assertIsString($key['algorithm']);
        $this->assertIsInt($key['created_at']);
        $this->assertIsInt($key['expires_at']);
        $this->assertIsInt($key['grace_expires_at']);
        
        // Verify timestamps make sense
        $this->assertLessThan($key['expires_at'], $key['created_at']);
        $this->assertLessThan($key['grace_expires_at'], $key['expires_at']);
    }

    protected function tearDown(): void
    {
        // Clean up cache
        $this->cache->flush();
    }
}