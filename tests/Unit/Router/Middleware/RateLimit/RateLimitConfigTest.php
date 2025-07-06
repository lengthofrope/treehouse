<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitConfig;

class RateLimitConfigTest extends TestCase
{
    public function testBasicConfiguration(): void
    {
        $config = new RateLimitConfig(['limit' => 100, 'window' => 60]);
        
        $this->assertEquals(100, $config->getLimit());
        $this->assertEquals(60, $config->getWindow());
        $this->assertEquals('fixed', $config->getStrategy());
        $this->assertEquals('ip', $config->getKeyResolver());
    }

    public function testFromParametersBasic(): void
    {
        $config = RateLimitConfig::fromParameters('100,60');
        
        $this->assertEquals(100, $config->getLimit());
        $this->assertEquals(60, $config->getWindow());
        $this->assertEquals('fixed', $config->getStrategy());
        $this->assertEquals('ip', $config->getKeyResolver());
    }

    public function testFromParametersWithStrategy(): void
    {
        $config = RateLimitConfig::fromParameters('50,30,sliding');
        
        $this->assertEquals(50, $config->getLimit());
        $this->assertEquals(30, $config->getWindow());
        $this->assertEquals('sliding', $config->getStrategy());
        $this->assertEquals('ip', $config->getKeyResolver());
    }

    public function testFromParametersWithStrategyAndKeyResolver(): void
    {
        $config = RateLimitConfig::fromParameters('200,120,token_bucket,user');
        
        $this->assertEquals(200, $config->getLimit());
        $this->assertEquals(120, $config->getWindow());
        $this->assertEquals('token_bucket', $config->getStrategy());
        $this->assertEquals(['type' => 'user'], $config->getKeyResolver());
    }

    public function testFromParametersWithHeader(): void
    {
        $config = RateLimitConfig::fromParameters('1000,3600,fixed,header');
        
        $this->assertEquals(1000, $config->getLimit());
        $this->assertEquals(3600, $config->getWindow());
        $this->assertEquals('fixed', $config->getStrategy());
        $this->assertEquals(['type' => 'header'], $config->getKeyResolver());
    }

    public function testFromParametersWithComposite(): void
    {
        $config = RateLimitConfig::fromParameters('500,1800,sliding,composite');
        
        $this->assertEquals(500, $config->getLimit());
        $this->assertEquals(1800, $config->getWindow());
        $this->assertEquals('sliding', $config->getStrategy());
        $this->assertEquals(['type' => 'composite'], $config->getKeyResolver());
    }

    public function testAllValidStrategies(): void
    {
        $strategies = ['fixed', 'sliding', 'token_bucket'];
        
        foreach ($strategies as $strategy) {
            $config = RateLimitConfig::fromParameters("100,60,{$strategy}");
            $this->assertEquals($strategy, $config->getStrategy());
        }
    }

    public function testAllValidKeyResolvers(): void
    {
        $resolvers = ['ip', 'user', 'composite', 'header'];
        
        foreach ($resolvers as $resolver) {
            $config = RateLimitConfig::fromParameters("100,60,fixed,{$resolver}");
            $keyResolverConfig = $config->getKeyResolver();
            
            if (is_array($keyResolverConfig)) {
                $this->assertEquals($resolver, $keyResolverConfig['type']);
            } else {
                $this->assertEquals($resolver, $keyResolverConfig);
            }
        }
    }

    public function testInvalidStrategyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid strategy 'invalid'");
        
        RateLimitConfig::fromParameters('100,60,invalid');
    }

    public function testInvalidKeyResolverThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid key resolver 'invalid'");
        
        RateLimitConfig::fromParameters('100,60,fixed,invalid');
    }

    public function testInsufficientParametersThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit configuration requires at least limit and window');
        
        RateLimitConfig::fromParameters('100');
    }

    public function testZeroLimitThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit and window must be positive integers');
        
        RateLimitConfig::fromParameters('0,60');
    }

    public function testNegativeWindowThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit and window must be positive integers');
        
        RateLimitConfig::fromParameters('100,-60');
    }

    public function testGetAndSetMethods(): void
    {
        $config = new RateLimitConfig();
        
        $config->set('custom_key', 'custom_value');
        $this->assertEquals('custom_value', $config->get('custom_key'));
        
        $this->assertNull($config->get('non_existent_key'));
        $this->assertEquals('default', $config->get('non_existent_key', 'default'));
    }

    public function testToArray(): void
    {
        $config = RateLimitConfig::fromParameters('100,60,sliding,user');
        $array = $config->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('limit', $array);
        $this->assertArrayHasKey('window', $array);
        $this->assertArrayHasKey('strategy', $array);
        $this->assertArrayHasKey('key_resolver', $array);
        
        $this->assertEquals(100, $array['limit']);
        $this->assertEquals(60, $array['window']);
        $this->assertEquals('sliding', $array['strategy']);
    }

    public function testValidateValidConfiguration(): void
    {
        $config = RateLimitConfig::fromParameters('100,60,fixed,ip');
        
        // Should not throw an exception
        $config->validate();
        $this->assertTrue(true); // Assert that we got here without exception
    }

    public function testValidateInvalidConfiguration(): void
    {
        $config = new RateLimitConfig(['limit' => -1, 'window' => 60]);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit and window must be positive integers');
        
        $config->validate();
    }

    public function testMultipleLimitsConfiguration(): void
    {
        $config = RateLimitConfig::fromParameters('100,60|1000,3600');
        
        $this->assertTrue($config->hasMultipleLimits());
        
        $limits = $config->getLimits();
        $this->assertCount(2, $limits);
        
        $this->assertEquals(100, $limits[0]['limit']);
        $this->assertEquals(60, $limits[0]['window']);
        $this->assertEquals(1000, $limits[1]['limit']);
        $this->assertEquals(3600, $limits[1]['window']);
    }

    public function testMultipleLimitsWithStrategies(): void
    {
        $config = RateLimitConfig::fromParameters('50,30,sliding|200,120,token_bucket');
        
        $this->assertTrue($config->hasMultipleLimits());
        
        $limits = $config->getLimits();
        $this->assertEquals('sliding', $limits[0]['strategy']);
        $this->assertEquals('token_bucket', $limits[1]['strategy']);
    }

    public function testCacheStoreConfiguration(): void
    {
        $config = new RateLimitConfig(['cache_store' => 'redis']);
        
        $this->assertEquals('redis', $config->getCacheStore());
    }

    public function testCachePrefixConfiguration(): void
    {
        $config = new RateLimitConfig(['cache_prefix' => 'custom_prefix']);
        
        $this->assertEquals('custom_prefix', $config->getCachePrefix());
    }

    public function testDefaultValues(): void
    {
        $config = new RateLimitConfig();
        
        $this->assertEquals('fixed', $config->getStrategy());
        $this->assertEquals('ip', $config->getKeyResolver());
        $this->assertEquals('default', $config->getCacheStore());
        $this->assertEquals('rate_limit', $config->getCachePrefix());
        $this->assertFalse($config->hasMultipleLimits());
    }

    public function testComplexKeyResolverParsing(): void
    {
        // Test header-based resolver parsing
        $config = RateLimitConfig::fromParameters('100,60,fixed,header:x-api-key');
        $keyResolver = $config->getKeyResolver();
        
        $this->assertIsArray($keyResolver);
        $this->assertEquals('header', $keyResolver['type']);
        $this->assertEquals('x-api-key', $keyResolver['header']);
    }

    public function testCompositeKeyResolverParsing(): void
    {
        // Test composite resolver parsing
        $config = RateLimitConfig::fromParameters('100,60,fixed,ip+user');
        $keyResolver = $config->getKeyResolver();
        
        $this->assertIsArray($keyResolver);
        $this->assertEquals('composite', $keyResolver['type']);
        $this->assertEquals(['ip', 'user'], $keyResolver['resolvers']);
    }

    public function testCustomKeyResolverParsing(): void
    {
        // Test custom resolver parsing
        $config = RateLimitConfig::fromParameters('100,60,fixed,custom:App\\CustomResolver');
        $keyResolver = $config->getKeyResolver();
        
        $this->assertIsArray($keyResolver);
        $this->assertEquals('custom', $keyResolver['type']);
        $this->assertEquals('App\\CustomResolver', $keyResolver['class']);
    }
}