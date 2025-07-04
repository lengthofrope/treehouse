<?php

declare(strict_types=1);

namespace Tests\Unit\Errors\Exceptions;

use LengthOfRope\TreeHouse\Errors\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function testCanCreateWithDefaults(): void
    {
        $exception = new AuthenticationException('Authentication failed');
        
        $this->assertEquals('Authentication failed', $exception->getMessage());
        $this->assertEquals(401, $exception->getStatusCode());
        $this->assertEquals('medium', $exception->getSeverity());
        $this->assertStringStartsWith('AUTH_', $exception->getErrorCode());
    }

    public function testCanCreateWithCustomParameters(): void
    {
        $attemptDetails = ['failed_attempts' => 3, 'last_attempt' => '2024-01-01'];
        $context = ['custom_data' => 'test'];
        $exception = new AuthenticationException(
            'Invalid credentials',
            'password',
            'john_doe',
            $attemptDetails,
            null,
            $context
        );
        
        $this->assertEquals('Invalid credentials', $exception->getMessage());
        $this->assertEquals('password', $exception->getAuthMethod());
        $this->assertEquals('john_doe', $exception->getUserIdentifier());
        $this->assertEquals($attemptDetails, $exception->getAttemptDetails());
    }

    public function testDefaultStatusCodeIs401(): void
    {
        $exception = new AuthenticationException('Auth error');
        
        $this->assertEquals(401, $exception->getStatusCode());
    }

    public function testDefaultSeverityIsMedium(): void
    {
        $exception = new AuthenticationException('Auth error');
        
        $this->assertEquals('medium', $exception->getSeverity());
    }

    public function testContextContainsAuthInfo(): void
    {
        $attemptDetails = [
            'failed_attempts' => 3,
            'last_ip' => '10.0.0.1'
        ];
        
        $exception = new AuthenticationException(
            'Multiple failed attempts',
            'password',
            'test_user',
            $attemptDetails
        );
        
        $exceptionContext = $exception->getContext();
        $this->assertEquals('password', $exceptionContext['auth_method']);
        $this->assertEquals('test_user', $exceptionContext['user_identifier']);
        $this->assertArrayHasKey('attempt_details', $exceptionContext);
    }

    public function testDefaultErrorCodeGeneration(): void
    {
        $exception1 = new AuthenticationException('Error 1');
        $exception2 = new AuthenticationException('Error 2');
        
        $this->assertNotEmpty($exception1->getErrorCode());
        $this->assertNotEmpty($exception2->getErrorCode());
        $this->assertNotEquals($exception1->getErrorCode(), $exception2->getErrorCode());
    }

    public function testToArrayIncludesAllData(): void
    {
        $exception = new AuthenticationException(
            'Authentication error',
            'password',
            'testuser',
            ['attempts' => 3]
        );
        
        $array = $exception->toArray();
        
        $this->assertEquals('Authentication error', $array['message']);
        $this->assertEquals(401, $array['status_code']);
        $this->assertEquals('medium', $array['severity']);
        $this->assertArrayHasKey('authentication', $array);
        $this->assertEquals('password', $array['authentication']['auth_method']);
        $this->assertEquals('testuser', $array['authentication']['user_identifier']);
    }

    public function testSummaryContainsRelevantInfo(): void
    {
        $exception = new AuthenticationException(
            'Token expired',
            'jwt',
            'user123',
            ['token_type' => 'JWT', 'expires_at' => '2024-01-01']
        );
        
        $summary = $exception->getSummary();
        
        $this->assertStringContainsString('Token expired', $summary);
        $this->assertStringContainsString('medium', $summary);
    }

    public function testIsNotReportableByDefault(): void
    {
        $exception = new AuthenticationException('Auth error');
        
        $this->assertFalse($exception->isReportable());
        $this->assertFalse($exception->shouldReport());
    }

    public function testIsLoggableByDefault(): void
    {
        $exception = new AuthenticationException('Auth error');
        
        $this->assertTrue($exception->isLoggable());
    }

    public function testJsonSerialization(): void
    {
        $exception = new AuthenticationException(
            'JSON test',
            'password',
            'testuser',
            ['attempts' => 1]
        );
        
        $json = $exception->toJson();
        $data = json_decode($json, true);
        
        $this->assertIsArray($data);
        $this->assertEquals('JSON test', $data['message']);
        $this->assertEquals(401, $data['status_code']);
        $this->assertArrayHasKey('authentication', $data);
    }

    public function testCanCreateWithPreviousException(): void
    {
        $previous = new \Exception('Token validation failed');
        $exception = new AuthenticationException(
            'Authentication failed',
            'token',
            'user123',
            [],
            $previous
        );
        
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals('Authentication failed', $exception->getMessage());
    }

    public function testStaticFactoryMethods(): void
    {
        // Test invalidCredentials
        $exception = AuthenticationException::invalidCredentials('testuser', 'password');
        $this->assertStringContainsString('Invalid credentials', $exception->getMessage());
        $this->assertEquals('password', $exception->getAuthMethod());
        $this->assertEquals('testuser', $exception->getUserIdentifier());

        // Test missingAuthentication
        $exception = AuthenticationException::missingAuthentication('bearer', '/api/protected');
        $this->assertStringContainsString('Authentication required', $exception->getMessage());
        $this->assertEquals('bearer', $exception->getAuthMethod());

        // Test sessionExpired
        $exception = AuthenticationException::sessionExpired('user123');
        $this->assertStringContainsString('Session expired', $exception->getMessage());
        $this->assertEquals('session', $exception->getAuthMethod());
        $this->assertEquals('user123', $exception->getUserIdentifier());
    }
}