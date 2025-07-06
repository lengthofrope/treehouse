<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitManager;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\RateLimitConfig;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\FixedWindowStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\SlidingWindowStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\Strategies\TokenBucketStrategy;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\IpKeyResolver;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\UserKeyResolver;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\HeaderKeyResolver;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\CompositeKeyResolver;

class RateLimitManagerTest extends TestCase
{
    private RateLimitManager $manager;

    protected function setUp(): void
    {
        $this->manager = new RateLimitManager();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->manager->getConfig();
        
        $this->assertArrayHasKey('default_strategy', $config);
        $this->assertArrayHasKey('default_key_resolver', $config);
        $this->assertArrayHasKey('cache_prefix', $config);
        
        $this->assertEquals('fixed', $config['default_strategy']);
        $this->assertEquals('ip', $config['default_key_resolver']);
        $this->assertEquals('rate_limit', $config['cache_prefix']);
    }

    public function testSetConfig(): void
    {
        $newConfig = [
            'default_strategy' => 'sliding',
            'default_key_resolver' => 'user',
            'cache_prefix' => 'custom_rate_limit',
        ];
        
        $this->manager->setConfig($newConfig);
        $config = $this->manager->getConfig();
        
        $this->assertEquals('sliding', $config['default_strategy']);
        $this->assertEquals('user', $config['default_key_resolver']);
        $this->assertEquals('custom_rate_limit', $config['cache_prefix']);
    }

    public function testGetAvailableStrategies(): void
    {
        $strategies = $this->manager->getAvailableStrategies();
        
        $this->assertIsArray($strategies);
        $this->assertArrayHasKey('fixed', $strategies);
        $this->assertArrayHasKey('sliding', $strategies);
        $this->assertArrayHasKey('token_bucket', $strategies);
        
        $this->assertEquals(FixedWindowStrategy::class, $strategies['fixed']);
        $this->assertEquals(SlidingWindowStrategy::class, $strategies['sliding']);
        $this->assertEquals(TokenBucketStrategy::class, $strategies['token_bucket']);
    }

    public function testGetAvailableKeyResolvers(): void
    {
        $resolvers = $this->manager->getAvailableKeyResolvers();
        
        $this->assertIsArray($resolvers);
        $this->assertArrayHasKey('ip', $resolvers);
        $this->assertArrayHasKey('user', $resolvers);
        $this->assertArrayHasKey('composite', $resolvers);
        $this->assertArrayHasKey('header', $resolvers);
        
        $this->assertEquals(IpKeyResolver::class, $resolvers['ip']);
        $this->assertEquals(UserKeyResolver::class, $resolvers['user']);
        $this->assertEquals(CompositeKeyResolver::class, $resolvers['composite']);
        $this->assertEquals(HeaderKeyResolver::class, $resolvers['header']);
    }

    public function testGetFixedWindowStrategy(): void
    {
        $strategy = $this->manager->getStrategy('fixed');
        
        $this->assertInstanceOf(FixedWindowStrategy::class, $strategy);
        $this->assertEquals('fixed', $strategy->getName());
    }

    public function testGetSlidingWindowStrategy(): void
    {
        $strategy = $this->manager->getStrategy('sliding');
        
        $this->assertInstanceOf(SlidingWindowStrategy::class, $strategy);
        $this->assertEquals('sliding', $strategy->getName());
    }

    public function testGetTokenBucketStrategy(): void
    {
        $strategy = $this->manager->getStrategy('token_bucket');
        
        $this->assertInstanceOf(TokenBucketStrategy::class, $strategy);
        $this->assertEquals('token_bucket', $strategy->getName());
    }

    public function testGetUnknownStrategyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rate limiting strategy: unknown');
        
        $this->manager->getStrategy('unknown');
    }

    public function testGetIpKeyResolver(): void
    {
        $resolver = $this->manager->getKeyResolver('ip');
        
        $this->assertInstanceOf(IpKeyResolver::class, $resolver);
        $this->assertEquals('ip', $resolver->getName());
    }

    public function testGetUserKeyResolver(): void
    {
        $resolver = $this->manager->getKeyResolver('user');
        
        $this->assertInstanceOf(UserKeyResolver::class, $resolver);
        $this->assertEquals('user', $resolver->getName());
    }

    public function testGetHeaderKeyResolver(): void
    {
        $resolver = $this->manager->getKeyResolver('header');
        
        $this->assertInstanceOf(HeaderKeyResolver::class, $resolver);
        $this->assertEquals('header', $resolver->getName());
    }

    public function testGetCompositeKeyResolver(): void
    {
        $resolver = $this->manager->getKeyResolver('composite');
        
        $this->assertInstanceOf(CompositeKeyResolver::class, $resolver);
        $this->assertEquals('composite', $resolver->getName());
    }

    public function testGetKeyResolverWithArrayConfig(): void
    {
        $config = ['type' => 'ip'];
        $resolver = $this->manager->getKeyResolver($config);
        
        $this->assertInstanceOf(IpKeyResolver::class, $resolver);
    }

    public function testGetKeyResolverWithCustomConfig(): void
    {
        $config = [
            'type' => 'header',
            'header' => 'X-Custom-Key',
        ];
        $resolver = $this->manager->getKeyResolver($config);
        
        $this->assertInstanceOf(HeaderKeyResolver::class, $resolver);
    }

    public function testRegisterCustomStrategy(): void
    {
        $customStrategyClass = 'App\\CustomStrategy';
        
        // We can't test actual instantiation without the class existing,
        // but we can test registration
        $this->manager->registerStrategy('custom', FixedWindowStrategy::class);
        
        $strategies = $this->manager->getAvailableStrategies();
        $this->assertArrayHasKey('custom', $strategies);
        $this->assertEquals(FixedWindowStrategy::class, $strategies['custom']);
    }

    public function testRegisterCustomKeyResolver(): void
    {
        $this->manager->registerKeyResolver('custom', IpKeyResolver::class);
        
        $resolvers = $this->manager->getAvailableKeyResolvers();
        $this->assertArrayHasKey('custom', $resolvers);
        $this->assertEquals(IpKeyResolver::class, $resolvers['custom']);
    }

    public function testRegisterInvalidStrategyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Strategy class must implement RateLimitStrategyInterface');
        
        $this->manager->registerStrategy('invalid', \stdClass::class);
    }

    public function testRegisterInvalidKeyResolverThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Key resolver class must implement KeyResolverInterface');
        
        $this->manager->registerKeyResolver('invalid', \stdClass::class);
    }

    public function testStrategyCaching(): void
    {
        $strategy1 = $this->manager->getStrategy('fixed');
        $strategy2 = $this->manager->getStrategy('fixed');
        
        // Should return the same instance (cached)
        $this->assertSame($strategy1, $strategy2);
    }

    public function testKeyResolverCaching(): void
    {
        $resolver1 = $this->manager->getKeyResolver('ip');
        $resolver2 = $this->manager->getKeyResolver('ip');
        
        // Should return the same instance (cached)
        $this->assertSame($resolver1, $resolver2);
    }

    public function testCustomStrategyRegistrationClearsCachedInstance(): void
    {
        // Get initial strategy
        $strategy1 = $this->manager->getStrategy('fixed');
        
        // Register a new class for the same strategy name
        $this->manager->registerStrategy('fixed', SlidingWindowStrategy::class);
        
        // Get strategy again - should be a new instance
        $strategy2 = $this->manager->getStrategy('fixed');
        
        $this->assertNotSame($strategy1, $strategy2);
        $this->assertInstanceOf(SlidingWindowStrategy::class, $strategy2);
    }

    public function testManagerWithCustomConfig(): void
    {
        $config = [
            'default_strategy' => 'token_bucket',
            'default_key_resolver' => 'header',
            'cache_prefix' => 'test_rate_limit',
        ];
        
        $manager = new RateLimitManager($config);
        $actualConfig = $manager->getConfig();
        
        $this->assertEquals('token_bucket', $actualConfig['default_strategy']);
        $this->assertEquals('header', $actualConfig['default_key_resolver']);
        $this->assertEquals('test_rate_limit', $actualConfig['cache_prefix']);
    }
}