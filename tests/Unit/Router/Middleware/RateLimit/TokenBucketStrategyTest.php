<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\TokenBucketStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitResult;
use Tests\Unit\Router\Middleware\RateLimit\TestHelpers\TestCacheInterface;

class TokenBucketStrategyTest extends TestCase
{
    private TokenBucketStrategy $strategy;
    private TestCacheInterface $cache;

    protected function setUp(): void
    {
        $this->strategy = new TokenBucketStrategy();
        $this->cache = new TestCacheInterface();
    }

    public function testGetName(): void
    {
        $this->assertEquals('token_bucket', $this->strategy->getName());
    }

    public function testInitialTokensEmpty(): void
    {
        // By default, bucket starts empty
        $result = $this->strategy->checkLimit($this->cache, 'test-key', 5, 60);
        
        $this->assertTrue($result->isExceeded());
        $this->assertEquals(5, $result->getLimit());
        $this->assertGreaterThan(0, $result->getRetryAfter());
    }

    public function testCustomInitialTokens(): void
    {
        $strategy = new TokenBucketStrategy(['initial_tokens' => 5]);
        
        $result = $strategy->checkLimit($this->cache, 'test-key', 5, 60);
        
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(5, $result->getLimit());
        $this->assertEquals(4, $result->getRemaining());
    }

    public function testTokenRefill(): void
    {
        $key = 'refill-test';
        $capacity = 10;
        $refillPeriod = 60; // 60 seconds for full refill
        
        // Create strategy with some initial tokens
        $strategy = new TokenBucketStrategy(['initial_tokens' => 1]);
        
        // Use the one available token
        $result = $strategy->checkLimit($this->cache, $key, $capacity, $refillPeriod);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(0, $result->getRemaining());
        
        // Next request should be blocked (no tokens left)
        $result = $strategy->checkLimit($this->cache, $key, $capacity, $refillPeriod);
        $this->assertTrue($result->isExceeded());
    }

    public function testBurstCapability(): void
    {
        $strategy = new TokenBucketStrategy(['initial_tokens' => 5]);
        $key = 'burst-test';
        $capacity = 5;
        
        // Should be able to use all tokens in a burst
        for ($i = 0; $i < $capacity; $i++) {
            $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
            $this->assertFalse($result->isExceeded(), "Request $i should be allowed");
        }
        
        // Next request should be blocked
        $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
        $this->assertTrue($result->isExceeded());
    }

    public function testGetUsage(): void
    {
        $strategy = new TokenBucketStrategy(['initial_tokens' => 3]);
        $key = 'usage-test';
        $capacity = 5;
        $refillPeriod = 60;
        
        // Use one token
        $strategy->checkLimit($this->cache, $key, $capacity, $refillPeriod);
        
        $usage = $strategy->getUsage($this->cache, $key, $refillPeriod);
        
        $this->assertIsArray($usage);
        $this->assertArrayHasKey('current_tokens', $usage);
        $this->assertArrayHasKey('bucket_capacity', $usage);
        $this->assertArrayHasKey('refill_rate', $usage);
        $this->assertArrayHasKey('last_refill', $usage);
    }

    public function testClearLimit(): void
    {
        $key = 'clear-test';
        $capacity = 5;
        
        // Start with an empty bucket strategy (default behavior)
        $strategy = new TokenBucketStrategy(); // No initial tokens, should start empty
        
        // First request should be blocked (empty bucket)
        $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
        $this->assertTrue($result->isExceeded());
        
        // Add some tokens manually using resetBucket
        $strategy->resetBucket($this->cache, $key, $capacity, 60);
        
        // Now should be allowed
        $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
        $this->assertFalse($result->isExceeded());
        
        // Clear the limit
        $strategy->clearLimit($this->cache, $key, 60);
        
        // Next request should behave like a fresh bucket (empty)
        $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
        $this->assertTrue($result->isExceeded(), 'Bucket should be empty after clearing');
    }

    public function testResetBucket(): void
    {
        $key = 'reset-test';
        $capacity = 5;
        
        // Use all tokens
        $strategy = new TokenBucketStrategy(['initial_tokens' => $capacity]);
        for ($i = 0; $i < $capacity; $i++) {
            $strategy->checkLimit($this->cache, $key, $capacity, 60);
        }
        
        // Should be blocked
        $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
        $this->assertTrue($result->isExceeded());
        
        // Reset bucket to full
        $reset = $strategy->resetBucket($this->cache, $key, $capacity, 60);
        $this->assertTrue($reset);
        
        // Should now be allowed
        $result = $strategy->checkLimit($this->cache, $key, $capacity, 60);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals($capacity - 1, $result->getRemaining());
    }

    public function testGetWindowInfo(): void
    {
        $refillPeriod = 120;
        $info = $this->strategy->getWindowInfo($refillPeriod);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('refill_period', $info);
        $this->assertArrayHasKey('current_time', $info);
        $this->assertArrayHasKey('tokens_per_second', $info);
        $this->assertEquals($refillPeriod, $info['refill_period']);
    }

    public function testDefaultConfig(): void
    {
        $config = $this->strategy->getDefaultConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('cache_prefix', $config);
        $this->assertArrayHasKey('ttl_buffer', $config);
        $this->assertArrayHasKey('initial_tokens', $config);
        $this->assertEquals('rate_limit_token_bucket', $config['cache_prefix']);
        $this->assertNull($config['initial_tokens']); // Default to empty bucket
    }

    public function testConfigUpdate(): void
    {
        $newConfig = [
            'cache_prefix' => 'custom_bucket',
            'initial_tokens' => 10,
        ];
        $this->strategy->setConfig($newConfig);
        
        $config = $this->strategy->getConfig();
        $this->assertEquals('custom_bucket', $config['cache_prefix']);
        $this->assertEquals(10, $config['initial_tokens']);
    }

    public function testRetryAfterCalculation(): void
    {
        $key = 'retry-test';
        $capacity = 1;
        $refillPeriod = 60; // 1 token per 60 seconds
        
        // Start with empty bucket
        $result = $this->strategy->checkLimit($this->cache, $key, $capacity, $refillPeriod);
        
        $this->assertTrue($result->isExceeded());
        $this->assertGreaterThan(0, $result->getRetryAfter());
        $this->assertLessThanOrEqual($refillPeriod, $result->getRetryAfter());
    }

    public function testResetTimeCalculation(): void
    {
        $strategy = new TokenBucketStrategy(['initial_tokens' => 3]);
        $key = 'reset-time-test';
        $capacity = 5;
        $refillPeriod = 100;
        
        // Use one token
        $result = $strategy->checkLimit($this->cache, $key, $capacity, $refillPeriod);
        
        $this->assertFalse($result->isExceeded());
        $this->assertGreaterThan(time(), $result->getResetTime());
    }

    public function testZeroCapacityHandling(): void
    {
        $result = $this->strategy->checkLimit($this->cache, 'zero-test', 0, 60);
        
        $this->assertTrue($result->isExceeded());
        $this->assertEquals(0, $result->getLimit());
    }

}
