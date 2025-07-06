<?php

declare(strict_types=1);

namespace Tests\Unit\Router\Exceptions;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Router\Exceptions\RateLimitExceededException;

/**
 * Rate Limit Exceeded Exception Tests
 */
class RateLimitExceededExceptionTest extends TestCase
{
    public function testExceptionCreation(): void
    {
        $limit = 60;
        $windowInMinutes = 1;
        $retryAfter = 30;
        $identifier = 'ip';

        $exception = new RateLimitExceededException($limit, $windowInMinutes, $retryAfter, $identifier);

        $this->assertEquals(429, $exception->getCode());
        $this->assertEquals(429, $exception->getStatusCode());
        $this->assertStringContainsString("Rate limit exceeded for {$identifier}", $exception->getMessage());
        $this->assertStringContainsString("Limit: {$limit} requests per {$windowInMinutes} minute(s)", $exception->getMessage());
        $this->assertStringContainsString("Try again in {$retryAfter} seconds", $exception->getMessage());
    }

    public function testExceptionHeaders(): void
    {
        $limit = 100;
        $windowInMinutes = 5;
        $retryAfter = 120;
        $currentTime = time();

        $exception = new RateLimitExceededException($limit, $windowInMinutes, $retryAfter, 'user');

        $headers = $exception->getHeaders();

        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertEquals((string) $limit, $headers['X-RateLimit-Limit']);

        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertEquals('0', $headers['X-RateLimit-Remaining']);

        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertGreaterThanOrEqual($currentTime + $retryAfter - 1, (int) $headers['X-RateLimit-Reset']);
        $this->assertLessThanOrEqual($currentTime + $retryAfter + 1, (int) $headers['X-RateLimit-Reset']);

        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertEquals((string) $retryAfter, $headers['Retry-After']);
    }

    public function testRateLimitInfo(): void
    {
        $limit = 50;
        $windowInMinutes = 10;
        $retryAfter = 60;
        $identifier = 'session';

        $exception = new RateLimitExceededException($limit, $windowInMinutes, $retryAfter, $identifier);

        $info = $exception->getRateLimitInfo();

        $this->assertIsArray($info);
        $this->assertEquals($limit, $info['limit']);
        $this->assertEquals($windowInMinutes, $info['window_minutes']);
        $this->assertEquals($retryAfter, $info['retry_after']);
        $this->assertEquals($identifier, $info['identifier']);
        $this->assertArrayHasKey('reset_time', $info);
        $this->assertIsInt($info['reset_time']);
    }

    public function testDefaultIdentifier(): void
    {
        $exception = new RateLimitExceededException(10, 1, 60);

        $this->assertStringContainsString('Rate limit exceeded for request', $exception->getMessage());
        
        $info = $exception->getRateLimitInfo();
        $this->assertEquals('request', $info['identifier']);
    }

    public function testUserFriendlyMessage(): void
    {
        $exception = new RateLimitExceededException(60, 1, 30, 'ip');

        $userMessage = $exception->getUserMessage();
        $this->assertEquals('You are making too many requests. Please wait and try again.', $userMessage);
    }
}