<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\RateLimiter;
use LengthOfRope\TreeHouse\Cache\CacheInterface;

/**
 * Rate Limiter Tests
 */
class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache implementation
        $this->cache = $this->createMock(CacheInterface::class);
        $this->rateLimiter = new RateLimiter($this->cache);
    }

    public function testAttemptAllowsRequestWithinLimit(): void
    {
        $key = 'test_key';
        $limit = 10;
        $windowInMinutes = 1;

        // Mock cache to return empty bucket (first request)
        $this->cache->expects($this->once())
            ->method('get')
            ->with('rate_limit:' . md5($key), $this->anything())
            ->willReturn(['count' => 0, 'window_start' => time()]);

        $this->cache->expects($this->once())
            ->method('put')
            ->with(
                'rate_limit:' . md5($key),
                $this->callback(function($bucket) {
                    return $bucket['count'] === 1;
                }),
                $this->greaterThan(60)
            )
            ->willReturn(true);

        $result = $this->rateLimiter->attempt($key, $limit, $windowInMinutes);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(1, $result['current']);
        $this->assertEquals(9, $result['remaining']);
        $this->assertEquals(0, $result['retry_after']);
    }

    public function testAttemptDeniesRequestWhenLimitExceeded(): void
    {
        $key = 'test_key';
        $limit = 5;
        $windowInMinutes = 1;
        $windowStart = time();

        // Mock cache to return bucket at limit
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(['count' => 5, 'window_start' => $windowStart]);

        // Should not call put when limit is exceeded
        $this->cache->expects($this->never())
            ->method('put');

        $result = $this->rateLimiter->attempt($key, $limit, $windowInMinutes);

        $this->assertFalse($result['allowed']);
        $this->assertEquals(5, $result['current']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    public function testAttemptResetsWindowWhenExpired(): void
    {
        $key = 'test_key';
        $limit = 10;
        $windowInMinutes = 1;
        $oldWindowStart = time() - 120; // 2 minutes ago

        // Mock cache to return expired bucket
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(['count' => 10, 'window_start' => $oldWindowStart]);

        $this->cache->expects($this->once())
            ->method('put')
            ->with(
                'rate_limit:' . md5($key),
                $this->callback(function($bucket) use ($oldWindowStart) {
                    return $bucket['count'] === 1 && $bucket['window_start'] > $oldWindowStart;
                }),
                $this->anything()
            )
            ->willReturn(true);

        $result = $this->rateLimiter->attempt($key, $limit, $windowInMinutes);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(1, $result['current']);
        $this->assertEquals(9, $result['remaining']);
    }

    public function testGetStatusReturnsCurrentState(): void
    {
        $key = 'test_key';
        $limit = 10;
        $windowInMinutes = 1;
        $windowStart = time();

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(['count' => 3, 'window_start' => $windowStart]);

        $status = $this->rateLimiter->getStatus($key, $limit, $windowInMinutes);

        $this->assertEquals(3, $status['current']);
        $this->assertEquals(7, $status['remaining']);
        $this->assertIsInt($status['reset_time']);
    }

    public function testGetStatusWithExpiredWindow(): void
    {
        $key = 'test_key';
        $limit = 10;
        $windowInMinutes = 1;
        $oldWindowStart = time() - 120; // 2 minutes ago

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(['count' => 8, 'window_start' => $oldWindowStart]);

        $status = $this->rateLimiter->getStatus($key, $limit, $windowInMinutes);

        $this->assertEquals(0, $status['current']);
        $this->assertEquals(10, $status['remaining']);
    }

    public function testClearRemovesRateLimitForKey(): void
    {
        $key = 'test_key';

        $this->cache->expects($this->once())
            ->method('forget')
            ->with('rate_limit:' . md5($key))
            ->willReturn(true);

        $result = $this->rateLimiter->clear($key);

        $this->assertTrue($result);
    }

    public function testClearAllFlushesCache(): void
    {
        $this->cache->expects($this->once())
            ->method('flush')
            ->willReturn(true);

        $result = $this->rateLimiter->clearAll();

        $this->assertTrue($result);
    }

    public function testMultipleAttemptsIncrementCount(): void
    {
        $key = 'test_key';
        $limit = 10;
        $windowInMinutes = 1;
        $windowStart = time();

        // First call - bucket with 0 count
        $this->cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                ['count' => 0, 'window_start' => $windowStart],
                ['count' => 1, 'window_start' => $windowStart]
            );

        // Two put calls
        $this->cache->expects($this->exactly(2))
            ->method('put')
            ->willReturn(true);

        // First attempt
        $result1 = $this->rateLimiter->attempt($key, $limit, $windowInMinutes);
        $this->assertTrue($result1['allowed']);
        $this->assertEquals(1, $result1['current']);

        // Second attempt
        $result2 = $this->rateLimiter->attempt($key, $limit, $windowInMinutes);
        $this->assertTrue($result2['allowed']);
        $this->assertEquals(2, $result2['current']);
    }
}