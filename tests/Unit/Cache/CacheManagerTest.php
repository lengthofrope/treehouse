<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use LengthOfRope\TreeHouse\Cache\CacheManager;
use LengthOfRope\TreeHouse\Cache\CacheInterface;
use LengthOfRope\TreeHouse\Cache\FileCache;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Cache Manager Test
 * 
 * Tests the CacheManager functionality including driver management and delegation.
 */
class CacheManagerTest extends TestCase
{
    private string $cacheDir;
    private CacheManager $manager;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/test_cache_manager_' . uniqid();
        
        $config = [
            'file' => [
                'path' => $this->cacheDir,
                'default_ttl' => 3600
            ]
        ];
        
        $this->manager = new CacheManager($config, 'file');
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

    public function testConstructorWithDefaults(): void
    {
        $manager = new CacheManager();
        $this->assertEquals('file', $manager->getDefaultDriver());
    }

    public function testConstructorWithCustomDefaults(): void
    {
        $config = ['custom' => ['setting' => 'value']];
        $manager = new CacheManager($config, 'custom');
        
        $this->assertEquals('custom', $manager->getDefaultDriver());
        $this->assertEquals($config, $manager->getConfig());
    }

    public function testGetDefaultDriver(): void
    {
        $this->assertEquals('file', $this->manager->getDefaultDriver());
    }

    public function testSetDefaultDriver(): void
    {
        $this->manager->setDefaultDriver('memory');
        $this->assertEquals('memory', $this->manager->getDefaultDriver());
    }

    public function testDriverReturnsFileCache(): void
    {
        $driver = $this->manager->driver('file');
        $this->assertInstanceOf(FileCache::class, $driver);
        $this->assertInstanceOf(CacheInterface::class, $driver);
    }

    public function testDriverWithNullUsesDefault(): void
    {
        $defaultDriver = $this->manager->driver();
        $explicitDriver = $this->manager->driver('file');
        
        $this->assertSame($defaultDriver, $explicitDriver);
    }

    public function testDriverCaching(): void
    {
        $driver1 = $this->manager->driver('file');
        $driver2 = $this->manager->driver('file');
        
        $this->assertSame($driver1, $driver2);
    }

    public function testUnsupportedDriverThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache driver [unsupported] is not supported.');
        
        $this->manager->driver('unsupported');
    }

    public function testExtendWithCustomDriver(): void
    {
        $customDriver = $this->createMock(CacheInterface::class);
        $this->manager->extend('custom', $customDriver);
        
        $driver = $this->manager->driver('custom');
        $this->assertSame($customDriver, $driver);
    }

    public function testGetDrivers(): void
    {
        // Initially empty
        $this->assertEmpty($this->manager->getDrivers());
        
        // After getting a driver
        $this->manager->driver('file');
        $drivers = $this->manager->getDrivers();
        
        $this->assertCount(1, $drivers);
        $this->assertArrayHasKey('file', $drivers);
        $this->assertInstanceOf(FileCache::class, $drivers['file']);
    }

    public function testClearDrivers(): void
    {
        $this->manager->driver('file');
        $this->assertNotEmpty($this->manager->getDrivers());
        
        $this->manager->clearDrivers();
        $this->assertEmpty($this->manager->getDrivers());
    }

    public function testGetConfig(): void
    {
        $config = $this->manager->getConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('file', $config);
    }

    public function testSetConfig(): void
    {
        $newConfig = [
            'file' => [
                'path' => '/new/path',
                'default_ttl' => 7200
            ]
        ];
        
        $this->manager->setConfig($newConfig);
        $this->assertEquals($newConfig, $this->manager->getConfig());
    }

    public function testSetConfigClearsDrivers(): void
    {
        $this->manager->driver('file');
        $this->assertNotEmpty($this->manager->getDrivers());
        
        $this->manager->setConfig(['file' => ['path' => '/new/path']]);
        $this->assertEmpty($this->manager->getDrivers());
    }

    // Test delegation methods
    public function testPutDelegation(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->assertTrue($this->manager->put($key, $value));
        $this->assertEquals($value, $this->manager->get($key));
    }

    public function testGetDelegation(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $default = 'default_value';
        
        $this->assertEquals($default, $this->manager->get($key, $default));
        
        $this->manager->put($key, $value);
        $this->assertEquals($value, $this->manager->get($key, $default));
    }

    public function testHasDelegation(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->assertFalse($this->manager->has($key));
        
        $this->manager->put($key, $value);
        $this->assertTrue($this->manager->has($key));
    }

    public function testForgetDelegation(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        
        $this->manager->put($key, $value);
        $this->assertTrue($this->manager->has($key));
        
        $this->assertTrue($this->manager->forget($key));
        $this->assertFalse($this->manager->has($key));
    }

    public function testFlushDelegation(): void
    {
        $this->manager->put('key1', 'value1');
        $this->manager->put('key2', 'value2');
        
        $this->assertTrue($this->manager->has('key1'));
        $this->assertTrue($this->manager->has('key2'));
        
        $this->assertTrue($this->manager->flush());
        
        $this->assertFalse($this->manager->has('key1'));
        $this->assertFalse($this->manager->has('key2'));
    }

    public function testRememberDelegation(): void
    {
        $key = 'remember_key';
        $value = 'computed_value';
        $callbackExecuted = false;
        
        $callback = function() use ($value, &$callbackExecuted) {
            $callbackExecuted = true;
            return $value;
        };
        
        $result = $this->manager->remember($key, 3600, $callback);
        $this->assertEquals($value, $result);
        $this->assertTrue($callbackExecuted);
        
        // Reset flag
        $callbackExecuted = false;
        
        // Second call should use cached value
        $result = $this->manager->remember($key, 3600, $callback);
        $this->assertEquals($value, $result);
        $this->assertFalse($callbackExecuted);
    }

    public function testRememberForeverDelegation(): void
    {
        $key = 'remember_forever_key';
        $value = 'computed_value';
        $callbackExecuted = false;
        
        $callback = function() use ($value, &$callbackExecuted) {
            $callbackExecuted = true;
            return $value;
        };
        
        $result = $this->manager->rememberForever($key, $callback);
        $this->assertEquals($value, $result);
        $this->assertTrue($callbackExecuted);
        
        // Reset flag
        $callbackExecuted = false;
        
        // Second call should use cached value
        $result = $this->manager->rememberForever($key, $callback);
        $this->assertEquals($value, $result);
        $this->assertFalse($callbackExecuted);
    }

    public function testIncrementDelegation(): void
    {
        $key = 'counter';
        
        $this->assertEquals(1, $this->manager->increment($key));
        $this->assertEquals(6, $this->manager->increment($key, 5));
    }

    public function testDecrementDelegation(): void
    {
        $key = 'counter';
        $this->manager->put($key, 10);
        
        $this->assertEquals(9, $this->manager->decrement($key));
        $this->assertEquals(4, $this->manager->decrement($key, 5));
    }

    public function testForeverDelegation(): void
    {
        $key = 'forever_key';
        $value = 'forever_value';
        
        $this->assertTrue($this->manager->forever($key, $value));
        $this->assertEquals($value, $this->manager->get($key));
    }

    public function testManyDelegation(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        foreach ($data as $key => $value) {
            $this->manager->put($key, $value);
        }
        
        $keys = ['key1', 'key2', 'key3', 'nonexistent'];
        $result = $this->manager->many($keys);
        
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
        $this->assertNull($result['nonexistent']);
    }

    public function testPutManyDelegation(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        $this->assertTrue($this->manager->putMany($data));
        
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $this->manager->get($key));
        }
    }

    public function testPrefix(): void
    {
        $prefixedCache = $this->manager->prefix('users');
        $this->assertInstanceOf(CacheInterface::class, $prefixedCache);
        
        // Test that prefixed cache works
        $prefixedCache->put('123', 'user_data');
        
        // Should not be accessible without prefix
        $this->assertNull($this->manager->get('123'));
        
        // Should be accessible with prefix
        $this->assertEquals('user_data', $prefixedCache->get('123'));
    }

    public function testMultipleDriverInstances(): void
    {
        $customDriver1 = $this->createMock(CacheInterface::class);
        $customDriver2 = $this->createMock(CacheInterface::class);
        
        $this->manager->extend('custom1', $customDriver1);
        $this->manager->extend('custom2', $customDriver2);
        
        $this->assertSame($customDriver1, $this->manager->driver('custom1'));
        $this->assertSame($customDriver2, $this->manager->driver('custom2'));
        $this->assertNotSame($customDriver1, $customDriver2);
    }

    public function testFileDriverConfiguration(): void
    {
        $customPath = sys_get_temp_dir() . '/custom_cache_' . uniqid();
        $customTtl = 7200;
        
        $config = [
            'file' => [
                'path' => $customPath,
                'default_ttl' => $customTtl
            ]
        ];
        
        $manager = new CacheManager($config, 'file');
        $driver = $manager->driver('file');
        
        $this->assertInstanceOf(FileCache::class, $driver);
        
        // Test that the custom path is used
        $driver->put('test', 'value');
        $this->assertTrue(is_dir($customPath));
        
        // Cleanup
        if (is_dir($customPath)) {
            $files = glob($customPath . '/*');
            if ($files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($customPath);
        }
    }

    public function testFileDriverDefaultConfiguration(): void
    {
        $manager = new CacheManager();
        $driver = $manager->driver('file');
        
        $this->assertInstanceOf(FileCache::class, $driver);
        
        // Should work with default configuration
        $driver->put('test', 'value');
        $this->assertEquals('value', $driver->get('test'));
    }
}