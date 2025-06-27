<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use LengthOfRope\TreeHouse\Security\Csrf;
use LengthOfRope\TreeHouse\Http\Session;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    private Csrf $csrf;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = $this->createMock(Session::class);
        $this->csrf = new Csrf($this->session);
    }

    public function testGenerateTokenCreatesValidToken(): void
    {
        $this->session->expects($this->once())
            ->method('set')
            ->with('_csrf_token', $this->isType('string'));

        $token = $this->csrf->generateToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testGenerateTokenWithCustomLength(): void
    {
        $this->session->expects($this->once())
            ->method('set')
            ->with('_csrf_token', $this->isType('string'));

        $token = $this->csrf->generateToken(16);

        $this->assertIsString($token);
        $this->assertEquals(32, strlen($token)); // 16 bytes = 32 hex chars
    }

    public function testGetTokenReturnsExistingToken(): void
    {
        $existingToken = 'existing-token-123';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($existingToken);

        $token = $this->csrf->getToken();

        $this->assertEquals($existingToken, $token);
    }

    public function testGetTokenGeneratesNewTokenWhenNoneExists(): void
    {
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn(null);

        $this->session->expects($this->once())
            ->method('set')
            ->with('_csrf_token', $this->isType('string'));

        $token = $this->csrf->getToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testVerifyTokenReturnsTrueForValidToken(): void
    {
        $validToken = 'valid-token-123';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($validToken);

        $this->assertTrue($this->csrf->verifyToken($validToken));
    }

    public function testVerifyTokenReturnsFalseForInvalidToken(): void
    {
        $sessionToken = 'session-token-123';
        $invalidToken = 'invalid-token-456';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($sessionToken);

        $this->assertFalse($this->csrf->verifyToken($invalidToken));
    }

    public function testVerifyTokenReturnsFalseWhenNoSessionToken(): void
    {
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn(null);

        $this->assertFalse($this->csrf->verifyToken('any-token'));
    }

    public function testVerifyTokenReturnsFalseForEmptyToken(): void
    {
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn('session-token');

        $this->assertFalse($this->csrf->verifyToken(''));
    }

    public function testVerifyTokenUsesTimingSafeComparison(): void
    {
        $sessionToken = 'session-token-123456789';
        
        $this->session->expects($this->exactly(2))
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($sessionToken);

        // Test with correct token
        $start1 = microtime(true);
        $result1 = $this->csrf->verifyToken($sessionToken);
        $time1 = microtime(true) - $start1;

        // Test with wrong token of same length
        $start2 = microtime(true);
        $result2 = $this->csrf->verifyToken('wrong-token-123456789');
        $time2 = microtime(true) - $start2;

        $this->assertTrue($result1);
        $this->assertFalse($result2);
        
        // Times should be relatively similar (within reasonable bounds)
        $this->assertLessThan(0.01, abs($time1 - $time2));
    }

    public function testRegenerateTokenCreatesNewToken(): void
    {
        $oldToken = 'old-token-123';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($oldToken);

        $this->session->expects($this->once())
            ->method('set')
            ->with('_csrf_token', $this->logicalAnd(
                $this->isType('string'),
                $this->logicalNot($this->equalTo($oldToken))
            ));

        $newToken = $this->csrf->regenerateToken();

        $this->assertIsString($newToken);
        $this->assertNotEquals($oldToken, $newToken);
    }

    public function testClearTokenRemovesTokenFromSession(): void
    {
        $this->session->expects($this->once())
            ->method('remove')
            ->with('_csrf_token');

        $this->csrf->clearToken();
    }

    public function testGetTokenFieldReturnsHtmlInput(): void
    {
        $token = 'test-token-123';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($token);

        $field = $this->csrf->getTokenField();

        $expectedField = '<input type="hidden" name="_csrf_token" value="' . $token . '">';
        $this->assertEquals($expectedField, $field);
    }

    public function testGetTokenFieldWithCustomName(): void
    {
        $token = 'test-token-123';
        $customName = 'custom_csrf';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($token);

        $field = $this->csrf->getTokenField($customName);

        $expectedField = '<input type="hidden" name="' . $customName . '" value="' . $token . '">';
        $this->assertEquals($expectedField, $field);
    }

    public function testGetTokenMetaReturnsHtmlMeta(): void
    {
        $token = 'test-token-123';
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($token);

        $meta = $this->csrf->getTokenMeta();

        $expectedMeta = '<meta name="csrf-token" content="' . $token . '">';
        $this->assertEquals($expectedMeta, $meta);
    }

    public function testVerifyRequestReturnsTrueForValidRequest(): void
    {
        $token = 'valid-token-123';
        $requestData = ['_csrf_token' => $token];
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($token);

        $this->assertTrue($this->csrf->verifyRequest($requestData));
    }

    public function testVerifyRequestReturnsFalseForInvalidRequest(): void
    {
        $sessionToken = 'session-token-123';
        $requestData = ['_csrf_token' => 'invalid-token'];
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($sessionToken);

        $this->assertFalse($this->csrf->verifyRequest($requestData));
    }

    public function testVerifyRequestReturnsFalseWhenTokenMissing(): void
    {
        $requestData = ['other_field' => 'value'];
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn('session-token');

        $this->assertFalse($this->csrf->verifyRequest($requestData));
    }

    public function testVerifyRequestWithCustomFieldName(): void
    {
        $token = 'valid-token-123';
        $customField = 'custom_csrf';
        $requestData = [$customField => $token];
        
        $this->session->expects($this->once())
            ->method('get')
            ->with('_csrf_token')
            ->willReturn($token);

        $this->assertTrue($this->csrf->verifyRequest($requestData, $customField));
    }

    public function testIsValidTokenLength(): void
    {
        // Test minimum length
        $this->assertFalse($this->csrf->isValidTokenLength('short'));
        
        // Test valid length
        $validToken = str_repeat('a', 32);
        $this->assertTrue($this->csrf->isValidTokenLength($validToken));
        
        // Test maximum reasonable length
        $longToken = str_repeat('a', 128);
        $this->assertTrue($this->csrf->isValidTokenLength($longToken));
        
        // Test excessively long token
        $tooLongToken = str_repeat('a', 1000);
        $this->assertFalse($this->csrf->isValidTokenLength($tooLongToken));
    }
}