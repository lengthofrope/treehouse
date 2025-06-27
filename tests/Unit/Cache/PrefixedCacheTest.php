<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Cache\CacheInterface;
use LengthOfRope\TreeHouse\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Prefixed Cache Test
 * 
 * Tests the PrefixedCache wrapper functionality.
 */
class PrefixedCacheTest extends TestCase
{
    private string $cacheDir;
    private CacheManager $manager;
    private CacheInterface $prefixedCache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/test_prefixed_cache_' . uniqid();
        
        $config = [
            'file' => [
                'path' => $this->cacheDir,
                'default_ttl' => 3600
            ]
        ];
        
        $this->manager = new CacheManager($config, 'file');
        $this->prefixedCache = $this->manager->prefix('test');
    }

    protected function tearDown(): void
    {
        $this->cleanupCacheDirectory();
    }

    private function cleanupCacheDirectory(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->cacheDir);
        }
    }

    public function testImplementsCacheInterface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, $this->prefixedCache);
    }

    public function testPutAndGet(): void
    {
        $key = 'user_123';
        $value = 'user_data';

        $this->assertTrue($this->prefixedCache->put($key, $value));
        $this->assertEquals($value, $this->prefixedCache->get($key));
    }

    public function testKeyPrefixing(): void
    {
        $key = 'user_123';
        $value = 'user_data';

        // Store with prefix
        $this->prefixedCache->put($key, $value);

        // Should not be accessible without prefix
        $this->assertNull($this->manager->get($key));

        // Should be accessible with manual prefix
        $this->assertEquals($value, $this->manager->get('test:' . $key));

        // Should be accessible through prefixed cache
        $this->assertEquals($value, $this->prefixedCache->get($key));
    }

    public function testHas(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->assertFalse($this->prefixedCache->has($key));
        
        $this->prefixedCache->put($key, $value);
        $this->assertTrue($this->prefixedCache->has($key));

        // Should not be found without prefix
        $this->assertFalse($this->manager->has($key));
    }

    public function testForget(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->prefixedCache->put($key, $value);
        $this->assertTrue($this->prefixedCache->has($key));

        $this->assertTrue($this->prefixedCache->forget($key));
        $this->assertFalse($this->prefixedCache->has($key));
    }

    public function testFlush(): void
    {
        $this->prefixedCache->put('key1', 'value1');
        $this->prefixedCache->put('key2', 'value2');
        $this->manager->put('other_key', 'other_value');

        $this->assertTrue($this->prefixedCache->has('key1'));
        $this->assertTrue($this->prefixedCache->has('key2'));
        $this->assertTrue($this->manager->has('other_key'));

        // Flush through prefixed cache flushes everything
        $this->assertTrue($this->prefixedCache->flush());

        $this->assertFalse($this->prefixedCache->has('key1'));
        $this->assertFalse($this->prefixedCache->has('key2'));
        $this->assertFalse($this->manager->has('other_key'));
    }

    public function testRemember(): void
    {
        $key = 'remember_key';
        $value = 'computed_value';
        $callbackExecuted = false;

        $callback = function() use ($value, &$callbackExecuted) {
            $callbackExecuted = true;
            return $value;
        };

        // First call should execute callback
        $result = $this->prefixedCache->remember($key, 3600, $callback);
        $this->assertEquals($value, $result);
        $this->assertTrue($callbackExecuted);

        // Reset flag
        $callbackExecuted = false;

        // Second call should return cached value without executing callback
        $result = $this->prefixedCache->remember($key, 3600, $callback);
        $this->assertEquals($value, $result);
        $this->assertFalse($callbackExecuted);
    }

    public function testRememberForever(): void
    {
        $key = 'remember_forever_key';
        $value = 'computed_value';
        $callbackExecuted = false;

        $callback = function() use ($value, &$callbackExecuted) {
            $callbackExecuted = true;
            return $value;
        };

        // First call should execute callback
        $result = $this->prefixedCache->rememberForever($key, $callback);
        $this->assertEquals($value, $result);
        $this->assertTrue($callbackExecuted);

        // Reset flag
        $callbackExecuted = false;

        // Second call should return cached value without executing callback
        $result = $this->prefixedCache->rememberForever($key, $callback);
        $this->assertEquals($value, $result);
        $this->assertFalse($callbackExecuted);
    }

    public function testIncrement(): void
    {
        $key = 'counter';

        // Increment non-existent key (should start from 0)
        $this->assertEquals(1, $this->prefixedCache->increment($key));
        $this->assertEquals(1, $this->prefixedCache->get($key));

        // Increment existing key
        $this->assertEquals(2, $this->prefixedCache->increment($key));
        $this->assertEquals(2, $this->prefixedCache->get($key));

        // Increment by custom value
        $this->assertEquals(7, $this->prefixedCache->increment($key, 5));
        $this->assertEquals(7, $this->prefixedCache->get($key));
    }

    public function testDecrement(): void
    {
        $key = 'counter';
        $this->prefixedCache->put($key, 10);

        // Decrement existing key
        $this->assertEquals(9, $this->prefixedCache->decrement($key));
        $this->assertEquals(9, $this->prefixedCache->get($key));

        // Decrement by custom value
        $this->assertEquals(4, $this->prefixedCache->decrement($key, 5));
        $this->assertEquals(4, $this->prefixedCache->get($key));
    }

    public function testForever(): void
    {
        $key = 'forever_key';
        $value = 'forever_value';

        $this->assertTrue($this->prefixedCache->forever($key, $value));
        $this->assertTrue($this->prefixedCache->has($key));
        $this->assertEquals($value, $this->prefixedCache->get($key));
    }

    public function testMany(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        foreach ($data as $key => $value) {
            $this->prefixedCache->put($key, $value);
        }

        $keys = ['key1', 'key2', 'key3', 'nonexistent'];
        $result = $this->prefixedCache->many($keys);

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
        $this->assertNull($result['nonexistent']);

        // Keys should be unprefixed in result
        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayNotHasKey('test:key1', $result);
    }

    public function testPutMany(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $this->assertTrue($this->prefixedCache->putMany($data, 3600));

        foreach ($data as $key => $value) {
            $this->assertTrue($this->prefixedCache->has($key));
            $this->assertEquals($value, $this->prefixedCache->get($key));
        }
    }

    public function testMultiplePrefixes(): void
    {
        $userCache = $this->manager->prefix('users');
        $sessionCache = $this->manager->prefix('sessions');

        $userCache->put('123', 'user_data');
        $sessionCache->put('123', 'session_data');

        // Both should exist independently
        $this->assertEquals('user_data', $userCache->get('123'));
        $this->assertEquals('session_data', $sessionCache->get('123'));

        // Should not interfere with each other
        $this->assertNull($userCache->get('sessions:123'));
        $this->assertNull($sessionCache->get('users:123'));
    }

    public function testPrefixWithColons(): void
    {
        $cache = $this->manager->prefix('namespace:with:colons');
        
        $cache->put('key', 'value');
        $this->assertEquals('value', $cache->get('key'));
        
        // Should be stored with proper prefix
        $this->assertEquals('value', $this->manager->get('namespace:with:colons:key'));
    }

    public function testPrefixNormalization(): void
    {
        $cache1 = $this->manager->prefix('test');
        $cache2 = $this->manager->prefix('test:');
        $cache3 = $this->manager->prefix('test::');

        $cache1->put('key', 'value1');
        $cache2->put('key', 'value2');
        $cache3->put('key', 'value3');

        // All should use the same normalized prefix
        $this->assertEquals('value3', $cache1->get('key'));
        $this->assertEquals('value3', $cache2->get('key'));
        $this->assertEquals('value3', $cache3->get('key'));
    }

    public function testEmptyPrefix(): void
    {
        $cache = $this->manager->prefix('');
        
        $cache->put('key', 'value');
        $this->assertEquals('value', $cache->get('key'));
        
        // Should be stored with colon prefix
        $this->assertEquals('value', $this->manager->get(':key'));
    }

    public function testPrefixIsolation(): void
    {
        $cache1 = $this->manager->prefix('prefix1');
        $cache2 = $this->manager->prefix('prefix2');

        $cache1->put('same_key', 'value1');
        $cache2->put('same_key', 'value2');

        $this->assertEquals('value1', $cache1->get('same_key'));
        $this->assertEquals('value2', $cache2->get('same_key'));

        // Should not see each other's keys
        $this->assertNull($cache1->get('prefix2:same_key'));
        $this->assertNull($cache2->get('prefix1:same_key'));
    }

    public function testGetWithDefault(): void
    {
        $key = 'nonexistent_key';
        $default = 'default_value';

        $this->assertEquals($default, $this->prefixedCache->get($key, $default));
    }

    public function testComplexDataTypes(): void
    {
        $data = [
            'array' => ['a', 'b', 'c'],
            'object' => (object)['prop' => 'value'],
            'nested' => [
                'level1' => [
                    'level2' => 'deep_value'
                ]
            ]
        ];

        foreach ($data as $key => $value) {
            $this->assertTrue($this->prefixedCache->put($key, $value));
            $this->assertEquals($value, $this->prefixedCache->get($key));
        }
    }

    public function testTtlHandling(): void
    {
        $key = 'ttl_key';
        $value = 'ttl_value';
        $ttl = 1; // 1 second

        $this->assertTrue($this->prefixedCache->put($key, $value, $ttl));
        $this->assertTrue($this->prefixedCache->has($key));

        // Mock time to 2 seconds in the future to ensure expiration
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $this->assertFalse($this->prefixedCache->has($key));
        $this->assertNull($this->prefixedCache->get($key));
        
        // Clear mock time
        Carbon::clearTestNow();
    }
}