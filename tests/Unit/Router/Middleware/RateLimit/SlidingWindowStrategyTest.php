<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\SlidingWindowStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitResult;
use Tests\Unit\Router\Middleware\RateLimit\TestHelpers\TestCacheInterface;

class SlidingWindowStrategyTest extends TestCase
{
    private SlidingWindowStrategy $strategy;
    private TestCacheInterface $cache;

    protected function setUp(): void
    {
        $this->strategy = new SlidingWindowStrategy();
        $this->cache = new TestCacheInterface();
    }

    public function testGetName(): void
    {
        $this->assertEquals('sliding', $this->strategy->getName());
    }

    public function testAllowsRequestWhenUnderLimit(): void
    {
        $result = $this->strategy->checkLimit($this->cache, 'test-key', 5, 60);
        
        $this->assertInstanceOf(RateLimitResult::class, $result);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(5, $result->getLimit());
        $this->assertEquals(4, $result->getRemaining());
        $this->assertEquals('test-key', $result->getKey());
        $this->assertEquals('sliding', $result->getStrategy());
    }

    public function testBlocksRequestWhenLimitExceeded(): void
    {
        $key = 'test-key';
        $limit = 3;
        $window = 60;
        
        // Make requests up to the limit
        for ($i = 0; $i < $limit; $i++) {
            $result = $this->strategy->checkLimit($this->cache, $key, $limit, $window);
            $this->assertFalse($result->isExceeded());
        }
        
        // Next request should be blocked
        $result = $this->strategy->checkLimit($this->cache, $key, $limit, $window);
        $this->assertTrue($result->isExceeded());
        $this->assertEquals($limit, $result->getLimit());
        $this->assertGreaterThan(0, $result->getRetryAfter());
    }

    public function testSlidingWindowBehavior(): void
    {
        $key = 'sliding-test';
        $limit = 2;
        $window = 30; // 30 seconds
        
        // Mock timestamps for predictable behavior
        $baseTime = 1000;
        $this->mockTime($baseTime);
        
        // First request at time 1000
        $result = $this->strategy->checkLimit($this->cache, $key, $limit, $window);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(1, $result->getRemaining());
        
        // Second request at time 1000 (same second)
        $result = $this->strategy->checkLimit($this->cache, $key, $limit, $window);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(0, $result->getRemaining());
        
        // Third request should be blocked
        $result = $this->strategy->checkLimit($this->cache, $key, $limit, $window);
        $this->assertTrue($result->isExceeded());
    }

    public function testTimestampsExpireCorrectly(): void
    {
        $key = 'expire-test';
        $limit = 2;
        $window = 30;
        
        // Store some old timestamps that should be expired
        $now = time();
        $oldTimestamps = [$now - 40, $now - 35]; // Outside the 30-second window
        $this->cache->put('rate_limit_sliding:' . $key, $oldTimestamps, 3600);
        
        // Request should be allowed since old timestamps are expired
        $result = $this->strategy->checkLimit($this->cache, $key, $limit, $window);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(1, $result->getRemaining()); // Should have limit - 1 remaining
    }

    public function testGetUsage(): void
    {
        $key = 'usage-test';
        $window = 60;
        
        // Make a request to create some usage
        $this->strategy->checkLimit($this->cache, $key, 5, $window);
        
        $usage = $this->strategy->getUsage($this->cache, $key, $window);
        
        $this->assertIsArray($usage);
        $this->assertArrayHasKey('current_count', $usage);
        $this->assertArrayHasKey('window_start', $usage);
        $this->assertArrayHasKey('window_end', $usage);
        $this->assertEquals(1, $usage['current_count']);
    }

    public function testClearLimit(): void
    {
        $key = 'clear-test';
        $window = 60;
        
        // Make a request to create data
        $this->strategy->checkLimit($this->cache, $key, 5, $window);
        
        // Clear the limit
        $result = $this->strategy->clearLimit($this->cache, $key, $window);
        $this->assertTrue($result);
        
        // Next request should start fresh
        $result = $this->strategy->checkLimit($this->cache, $key, 5, $window);
        $this->assertEquals(4, $result->getRemaining());
    }

    public function testGetWindowInfo(): void
    {
        $window = 60;
        $info = $this->strategy->getWindowInfo($window);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('start', $info);
        $this->assertArrayHasKey('end', $info);
        $this->assertArrayHasKey('current', $info);
        $this->assertArrayHasKey('window_size_seconds', $info);
        $this->assertEquals($window, $info['window_size_seconds']);
    }

    public function testMaxTimestampsLimit(): void
    {
        $strategy = new SlidingWindowStrategy(['max_timestamps' => 5]);
        $key = 'max-test';
        
        // Make more requests than the max timestamps limit
        for ($i = 0; $i < 10; $i++) {
            $strategy->checkLimit($this->cache, $key, 20, 60);
        }
        
        // Check that timestamps are limited
        $cacheKey = 'rate_limit_sliding:' . $key;
        $timestamps = $this->cache->get($cacheKey, []);
        $this->assertLessThanOrEqual(5, count($timestamps));
    }

    public function testDefaultConfig(): void
    {
        $config = $this->strategy->getDefaultConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('cache_prefix', $config);
        $this->assertArrayHasKey('ttl_buffer', $config);
        $this->assertArrayHasKey('max_timestamps', $config);
        $this->assertEquals('rate_limit_sliding', $config['cache_prefix']);
    }

    public function testConfigUpdate(): void
    {
        $newConfig = ['cache_prefix' => 'custom_prefix'];
        $this->strategy->setConfig($newConfig);
        
        $config = $this->strategy->getConfig();
        $this->assertEquals('custom_prefix', $config['cache_prefix']);
    }

    private function mockTime(int $timestamp): void
    {
        // Note: In a real implementation, you might use a time provider interface
        // For this test, we'll rely on the natural timing
    }
}
