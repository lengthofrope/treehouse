<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use LengthOfRope\TreeHouse\Cache\FileCache;
use LengthOfRope\TreeHouse\Cache\CacheInterface;
use LengthOfRope\TreeHouse\Support\Carbon;
use Tests\TestCase;

/**
 * File Cache Test
 * 
 * Tests the FileCache implementation to ensure proper caching functionality.
 */
class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/test_cache_' . uniqid();
        $this->cache = new FileCache($this->cacheDir, 3600);
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
        $this->assertInstanceOf(CacheInterface::class, $this->cache);
    }

    public function testCacheDirectoryIsCreated(): void
    {
        $this->assertTrue(is_dir($this->cacheDir));
    }

    public function testPutAndGet(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->assertTrue($this->cache->put($key, $value));
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testGetWithDefault(): void
    {
        $key = 'nonexistent_key';
        $default = 'default_value';

        $this->assertEquals($default, $this->cache->get($key, $default));
    }

    public function testGetWithNullDefault(): void
    {
        $key = 'nonexistent_key';

        $this->assertNull($this->cache->get($key));
    }

    public function testHas(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->assertFalse($this->cache->has($key));
        
        $this->cache->put($key, $value);
        $this->assertTrue($this->cache->has($key));
    }

    public function testForget(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->cache->put($key, $value);
        $this->assertTrue($this->cache->has($key));

        $this->assertTrue($this->cache->forget($key));
        $this->assertFalse($this->cache->has($key));
    }

    public function testForgetNonexistentKey(): void
    {
        $key = 'nonexistent_key';
        $this->assertTrue($this->cache->forget($key));
    }

    public function testFlush(): void
    {
        $this->cache->put('key1', 'value1');
        $this->cache->put('key2', 'value2');
        $this->cache->put('key3', 'value3');

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));

        $this->assertTrue($this->cache->flush());

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testPutWithTtl(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 1; // 1 second

        $this->assertTrue($this->cache->put($key, $value, $ttl));
        $this->assertTrue($this->cache->has($key));

        // Travel 2 seconds into the future to ensure expiration
        $this->travelForward(2);

        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    public function testForever(): void
    {
        $key = 'forever_key';
        $value = 'forever_value';

        $this->assertTrue($this->cache->forever($key, $value));
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));
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
        $result = $this->cache->remember($key, 3600, $callback);
        $this->assertEquals($value, $result);
        $this->assertTrue($callbackExecuted);

        // Reset flag
        $callbackExecuted = false;

        // Second call should return cached value without executing callback
        $result = $this->cache->remember($key, 3600, $callback);
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
        $result = $this->cache->rememberForever($key, $callback);
        $this->assertEquals($value, $result);
        $this->assertTrue($callbackExecuted);

        // Reset flag
        $callbackExecuted = false;

        // Second call should return cached value without executing callback
        $result = $this->cache->rememberForever($key, $callback);
        $this->assertEquals($value, $result);
        $this->assertFalse($callbackExecuted);
    }

    public function testIncrement(): void
    {
        $key = 'counter';

        // Increment non-existent key (should start from 0)
        $this->assertEquals(1, $this->cache->increment($key));
        $this->assertEquals(1, $this->cache->get($key));

        // Increment existing key
        $this->assertEquals(2, $this->cache->increment($key));
        $this->assertEquals(2, $this->cache->get($key));

        // Increment by custom value
        $this->assertEquals(7, $this->cache->increment($key, 5));
        $this->assertEquals(7, $this->cache->get($key));
    }

    public function testIncrementNonNumericValue(): void
    {
        $key = 'non_numeric';
        $this->cache->put($key, 'string_value');

        $this->assertFalse($this->cache->increment($key));
    }

    public function testDecrement(): void
    {
        $key = 'counter';
        $this->cache->put($key, 10);

        // Decrement existing key
        $this->assertEquals(9, $this->cache->decrement($key));
        $this->assertEquals(9, $this->cache->get($key));

        // Decrement by custom value
        $this->assertEquals(4, $this->cache->decrement($key, 5));
        $this->assertEquals(4, $this->cache->get($key));
    }

    public function testDecrementNonExistentKey(): void
    {
        $key = 'nonexistent_counter';

        // Decrement non-existent key (should start from 0)
        $this->assertEquals(-1, $this->cache->decrement($key));
        $this->assertEquals(-1, $this->cache->get($key));
    }

    public function testMany(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        foreach ($data as $key => $value) {
            $this->cache->put($key, $value);
        }

        $keys = ['key1', 'key2', 'key3', 'nonexistent'];
        $result = $this->cache->many($keys);

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
        $this->assertNull($result['nonexistent']);
    }

    public function testPutMany(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        $this->assertTrue($this->cache->putMany($data, 3600));

        foreach ($data as $key => $value) {
            $this->assertTrue($this->cache->has($key));
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    public function testPutManyWithTtl(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];

        $this->assertTrue($this->cache->putMany($data, 1)); // 1 second TTL

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        // Mock time to 2 seconds in the future to ensure expiration
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        
        // Clear mock time
        Carbon::clearTestNow();
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
            $this->assertTrue($this->cache->put($key, $value));
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    public function testCleanup(): void
    {
        // Add some expired items
        $this->cache->put('expired1', 'value1', 1);
        $this->cache->put('expired2', 'value2', 1);
        $this->cache->put('valid', 'value3', 3600);

        // Mock time to ensure expiration
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $cleaned = $this->cache->cleanup();
        $this->assertEquals(2, $cleaned);

        // Valid item should still exist
        $this->assertTrue($this->cache->has('valid'));
        $this->assertFalse($this->cache->has('expired1'));
        $this->assertFalse($this->cache->has('expired2'));
        
        // Clear mock time
        Carbon::clearTestNow();
    }

    public function testGetStats(): void
    {
        $this->cache->put('key1', 'value1');
        $this->cache->put('key2', 'value2');
        $this->cache->put('expired', 'value3', 1);

        // Mock time to make one item expired
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $stats = $this->cache->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_files', $stats);
        $this->assertArrayHasKey('total_size', $stats);
        $this->assertArrayHasKey('expired_files', $stats);

        $this->assertEquals(3, $stats['total_files']);
        $this->assertGreaterThan(0, $stats['total_size']);
        $this->assertEquals(1, $stats['expired_files']);
        
        // Clear mock time
        Carbon::clearTestNow();
    }

    public function testKeyHashing(): void
    {
        $longKey = str_repeat('a', 1000);
        $value = 'test_value';

        $this->assertTrue($this->cache->put($longKey, $value));
        $this->assertEquals($value, $this->cache->get($longKey));
        $this->assertTrue($this->cache->has($longKey));
    }

    public function testSpecialCharactersInKeys(): void
    {
        $specialKeys = [
            'key with spaces',
            'key/with/slashes',
            'key:with:colons',
            'key-with-dashes',
            'key_with_underscores',
            'key.with.dots'
        ];

        foreach ($specialKeys as $key) {
            $value = "value_for_{$key}";
            $this->assertTrue($this->cache->put($key, $value));
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    public function testConcurrentAccess(): void
    {
        $key = 'concurrent_key';
        $value = 'concurrent_value';

        // Simulate concurrent writes by putting the same key multiple times
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($this->cache->put($key, $value . $i));
        }

        // The last value should be stored
        $this->assertEquals($value . '9', $this->cache->get($key));
    }

    public function testNullValues(): void
    {
        $key = 'null_key';
        $value = null;

        $this->assertTrue($this->cache->put($key, $value));
        $this->assertTrue($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    public function testBooleanValues(): void
    {
        $this->cache->put('true_key', true);
        $this->cache->put('false_key', false);

        $this->assertTrue($this->cache->get('true_key'));
        $this->assertFalse($this->cache->get('false_key'));
    }

    public function testZeroValues(): void
    {
        $this->cache->put('zero_int', 0);
        $this->cache->put('zero_float', 0.0);
        $this->cache->put('zero_string', '0');

        $this->assertEquals(0, $this->cache->get('zero_int'));
        $this->assertEquals(0.0, $this->cache->get('zero_float'));
        $this->assertEquals('0', $this->cache->get('zero_string'));
    }
}