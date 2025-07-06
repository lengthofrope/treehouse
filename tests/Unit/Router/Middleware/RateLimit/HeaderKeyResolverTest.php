<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Middleware\RateLimit;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Middleware\RateLimit\KeyResolvers\HeaderKeyResolver;

class HeaderKeyResolverTest extends TestCase
{
    private HeaderKeyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new HeaderKeyResolver();
    }

    public function testGetName(): void
    {
        $this->assertEquals('header', $this->resolver->getName());
    }

    public function testDefaultConfig(): void
    {
        $config = $this->resolver->getDefaultConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('header', $config);
        $this->assertArrayHasKey('fallback_headers', $config);
        $this->assertArrayHasKey('header_prefix', $config);
        $this->assertArrayHasKey('ip_prefix', $config);
        $this->assertArrayHasKey('fallback_to_ip', $config);
        $this->assertArrayHasKey('case_sensitive', $config);
        $this->assertArrayHasKey('extract_bearer_token', $config);
        $this->assertArrayHasKey('hash_value', $config);
        
        $this->assertEquals('X-API-Key', $config['header']);
        $this->assertEquals('header', $config['header_prefix']);
        $this->assertEquals('ip', $config['ip_prefix']);
        $this->assertTrue($config['fallback_to_ip']);
        $this->assertFalse($config['case_sensitive']);
        $this->assertTrue($config['extract_bearer_token']);
        $this->assertFalse($config['hash_value']);
    }

    public function testConfigUpdate(): void
    {
        $newConfig = [
            'header' => 'X-Client-ID',
            'header_prefix' => 'client',
            'fallback_to_ip' => false,
        ];
        $this->resolver->setConfig($newConfig);
        
        $config = $this->resolver->getConfig();
        $this->assertEquals('X-Client-ID', $config['header']);
        $this->assertEquals('client', $config['header_prefix']);
        $this->assertFalse($config['fallback_to_ip']);
    }

    public function testGetPrimaryHeader(): void
    {
        $this->assertEquals('X-API-Key', $this->resolver->getPrimaryHeader());
        
        $this->resolver->setConfig(['header' => 'Authorization']);
        $this->assertEquals('Authorization', $this->resolver->getPrimaryHeader());
    }

    public function testGetFallbackHeaders(): void
    {
        $fallbacks = $this->resolver->getFallbackHeaders();
        
        $this->assertIsArray($fallbacks);
        $this->assertContains('Authorization', $fallbacks);
        $this->assertContains('X-Auth-Token', $fallbacks);
        $this->assertContains('X-Client-ID', $fallbacks);
    }

    public function testForApiKeyFactory(): void
    {
        $resolver = HeaderKeyResolver::forApiKey('X-Custom-Key');
        
        $this->assertEquals('X-Custom-Key', $resolver->getPrimaryHeader());
    }

    public function testForAuthorizationFactory(): void
    {
        $resolver = HeaderKeyResolver::forAuthorization();
        
        $this->assertEquals('Authorization', $resolver->getPrimaryHeader());
        $config = $resolver->getConfig();
        $this->assertTrue($config['extract_bearer_token']);
    }

    public function testExtractBearerTokenConfig(): void
    {
        $resolver = new HeaderKeyResolver(['extract_bearer_token' => true]);
        $config = $resolver->getConfig();
        
        $this->assertTrue($config['extract_bearer_token']);
    }

    public function testHashValueConfig(): void
    {
        $resolver = new HeaderKeyResolver(['hash_value' => true]);
        $config = $resolver->getConfig();
        
        $this->assertTrue($config['hash_value']);
    }

    public function testCaseSensitiveConfig(): void
    {
        $resolver = new HeaderKeyResolver(['case_sensitive' => true]);
        $config = $resolver->getConfig();
        
        $this->assertTrue($config['case_sensitive']);
    }

    public function testCustomFallbackHeaders(): void
    {
        $customHeaders = ['X-Token', 'X-Secret'];
        $resolver = new HeaderKeyResolver(['fallback_headers' => $customHeaders]);
        
        $this->assertEquals($customHeaders, $resolver->getFallbackHeaders());
    }

    public function testCustomPrefixes(): void
    {
        $resolver = new HeaderKeyResolver([
            'header_prefix' => 'api',
            'ip_prefix' => 'client',
        ]);
        
        $config = $resolver->getConfig();
        $this->assertEquals('api', $config['header_prefix']);
        $this->assertEquals('client', $config['ip_prefix']);
    }

    public function testResolverConfiguration(): void
    {
        // Test that all configuration options can be set
        $config = [
            'header' => 'X-Custom-Header',
            'fallback_headers' => ['X-Backup-1', 'X-Backup-2'],
            'header_prefix' => 'custom',
            'ip_prefix' => 'backup',
            'fallback_to_ip' => false,
            'case_sensitive' => true,
            'extract_bearer_token' => false,
            'hash_value' => true,
        ];
        
        $resolver = new HeaderKeyResolver($config);
        $actualConfig = $resolver->getConfig();
        
        foreach ($config as $key => $value) {
            $this->assertEquals($value, $actualConfig[$key], "Config key '$key' does not match");
        }
    }
}