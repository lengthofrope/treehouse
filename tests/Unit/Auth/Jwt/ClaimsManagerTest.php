<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use LengthOfRope\TreeHouse\Auth\Jwt\ClaimsManager;
use LengthOfRope\TreeHouse\Errors\Exceptions\InvalidArgumentException;

/**
 * JWT Claims Manager Test
 *
 * Tests the JWT claims management including standard claims,
 * custom claims, validation, and timing checks.
 *
 * @package Tests\Unit\Auth\Jwt
 */
class ClaimsManagerTest extends TestCase
{
    public function testConstructorWithEmptyClaims(): void
    {
        $claims = new ClaimsManager();
        $this->assertEquals([], $claims->getAllClaims());
    }

    public function testConstructorWithInitialClaims(): void
    {
        $initialClaims = [
            'iss' => 'test-issuer',
            'sub' => 'test-subject',
            'custom' => 'value',
        ];

        $claims = new ClaimsManager($initialClaims);
        $this->assertEquals($initialClaims, $claims->getAllClaims());
    }

    public function testSetAndGetClaim(): void
    {
        $claims = new ClaimsManager();
        
        $claims->setClaim('test', 'value');
        $this->assertEquals('value', $claims->getClaim('test'));
        $this->assertTrue($claims->hasClaim('test'));
    }

    public function testGetClaimWithDefault(): void
    {
        $claims = new ClaimsManager();
        
        $this->assertEquals('default', $claims->getClaim('non_existent', 'default'));
        $this->assertNull($claims->getClaim('non_existent'));
    }

    public function testSetClaims(): void
    {
        $claims = new ClaimsManager();
        
        $newClaims = [
            'iss' => 'issuer',
            'sub' => 'subject',
            'custom' => 'value',
        ];

        $claims->setClaims($newClaims);
        $this->assertEquals($newClaims, $claims->getAllClaims());
    }

    public function testRemoveClaim(): void
    {
        $claims = new ClaimsManager(['test' => 'value']);
        
        $this->assertTrue($claims->hasClaim('test'));
        $claims->removeClaim('test');
        $this->assertFalse($claims->hasClaim('test'));
    }

    public function testStandardClaimSettersAndGetters(): void
    {
        $claims = new ClaimsManager();
        
        // Test issuer
        $claims->setIssuer('test-issuer');
        $this->assertEquals('test-issuer', $claims->getIssuer());
        
        // Test subject
        $claims->setSubject('test-subject');
        $this->assertEquals('test-subject', $claims->getSubject());
        
        // Test audience (string)
        $claims->setAudience('test-audience');
        $this->assertEquals(['test-audience'], $claims->getAudience());
        
        // Test audience (array)
        $claims->setAudience(['aud1', 'aud2']);
        $this->assertEquals(['aud1', 'aud2'], $claims->getAudience());
        
        // Test expiration
        $exp = time() + 3600;
        $claims->setExpiration($exp);
        $this->assertEquals($exp, $claims->getExpiration());
        
        // Test not before
        $nbf = time();
        $claims->setNotBefore($nbf);
        $this->assertEquals($nbf, $claims->getNotBefore());
        
        // Test issued at
        $iat = time();
        $claims->setIssuedAt($iat);
        $this->assertEquals($iat, $claims->getIssuedAt());
        
        // Test JWT ID
        $claims->setJwtId('test-jti');
        $this->assertEquals('test-jti', $claims->getJwtId());
    }

    public function testNullStandardClaimGetters(): void
    {
        $claims = new ClaimsManager();
        
        $this->assertNull($claims->getIssuer());
        $this->assertNull($claims->getSubject());
        $this->assertNull($claims->getAudience());
        $this->assertNull($claims->getExpiration());
        $this->assertNull($claims->getNotBefore());
        $this->assertNull($claims->getIssuedAt());
        $this->assertNull($claims->getJwtId());
    }

    public function testIsExpired(): void
    {
        $claims = new ClaimsManager();
        
        // Test with no expiration
        $this->assertFalse($claims->isExpired());
        
        // Test with future expiration
        $claims->setExpiration(time() + 3600);
        $this->assertFalse($claims->isExpired());
        
        // Test with past expiration
        $claims->setExpiration(time() - 3600);
        $this->assertTrue($claims->isExpired());
        
        // Test with leeway
        $claims->setExpiration(time() - 10);
        $this->assertFalse($claims->isExpired(20)); // 20 second leeway
        $this->assertTrue($claims->isExpired(5));   // 5 second leeway
    }

    public function testIsNotYetValid(): void
    {
        $claims = new ClaimsManager();
        
        // Test with no not-before
        $this->assertFalse($claims->isNotYetValid());
        
        // Test with past not-before
        $claims->setNotBefore(time() - 3600);
        $this->assertFalse($claims->isNotYetValid());
        
        // Test with future not-before
        $claims->setNotBefore(time() + 3600);
        $this->assertTrue($claims->isNotYetValid());
        
        // Test with leeway
        $claims->setNotBefore(time() + 10);
        $this->assertFalse($claims->isNotYetValid(20)); // 20 second leeway
        $this->assertTrue($claims->isNotYetValid(5));   // 5 second leeway
    }

    public function testValidateRequiredClaims(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'issuer',
            'sub' => 'subject',
            'exp' => time() + 3600,
        ]);
        
        // Should pass with existing claims
        $claims->validateRequiredClaims(['iss', 'sub']);
        
        // Should throw exception for missing claims
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required claims: aud, iat');
        
        $claims->validateRequiredClaims(['iss', 'sub', 'aud', 'iat']);
    }

    public function testValidateTiming(): void
    {
        $now = time();
        $claims = new ClaimsManager([
            'exp' => $now + 3600,
            'nbf' => $now - 10,
            'iat' => $now,
        ]);
        
        // Should pass with valid timing
        $claims->validateTiming();
        $claims->validateTiming(30); // With leeway
        
        // Assert that we can get the claims after validation
        $this->assertEquals($now + 3600, $claims->getExpiration());
        $this->assertEquals($now - 10, $claims->getNotBefore());
    }

    public function testValidateTimingExpired(): void
    {
        $claims = new ClaimsManager([
            'exp' => time() - 3600,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired');
        
        $claims->validateTiming();
    }

    public function testValidateTimingNotYetValid(): void
    {
        $claims = new ClaimsManager([
            'nbf' => time() + 3600,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token is not yet valid');
        
        $claims->validateTiming();
    }

    public function testGetCustomClaims(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'issuer',
            'sub' => 'subject',
            'custom1' => 'value1',
            'custom2' => 'value2',
        ]);
        
        $customClaims = $claims->getCustomClaims();
        $this->assertEquals(['custom1' => 'value1', 'custom2' => 'value2'], $customClaims);
    }

    public function testGetStandardClaims(): void
    {
        $claims = new ClaimsManager([
            'iss' => 'issuer',
            'sub' => 'subject',
            'custom1' => 'value1',
            'custom2' => 'value2',
        ]);
        
        $standardClaims = $claims->getStandardClaims();
        $this->assertEquals(['iss' => 'issuer', 'sub' => 'subject'], $standardClaims);
    }

    public function testFromArray(): void
    {
        $data = [
            'iss' => 'issuer',
            'sub' => 'subject',
            'custom' => 'value',
        ];
        
        $claims = ClaimsManager::fromArray($data);
        $this->assertEquals($data, $claims->getAllClaims());
    }

    public function testToJson(): void
    {
        $data = [
            'iss' => 'issuer',
            'sub' => 'subject',
            'custom' => 'value',
        ];
        
        $claims = new ClaimsManager($data);
        $json = $claims->toJson();
        
        $this->assertJson($json);
        $this->assertEquals($data, json_decode($json, true));
    }

    public function testClaimValidation(): void
    {
        $claims = new ClaimsManager();
        
        // Test empty claim name
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Claim name cannot be empty');
        
        $claims->setClaim('', 'value');
    }

    public function testTimestampClaimValidation(): void
    {
        $claims = new ClaimsManager();
        
        // Test invalid expiration (not integer)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'exp' must be a positive integer timestamp");
        
        $claims->setClaim('exp', 'invalid');
    }

    public function testTimestampClaimValidationNegative(): void
    {
        $claims = new ClaimsManager();
        
        // Test invalid expiration (negative)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'exp' must be a positive integer timestamp");
        
        $claims->setExpiration(-1);
    }

    public function testStringClaimValidation(): void
    {
        $claims = new ClaimsManager();
        
        // Test invalid issuer (not string)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'iss' must be a non-empty string");
        
        $claims->setIssuer('');
    }

    public function testStringClaimValidationNonString(): void
    {
        $claims = new ClaimsManager();
        
        // Test invalid subject (not string)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'sub' must be a non-empty string");
        
        $claims->setClaim('sub', 123);
    }

    public function testAudienceClaimValidation(): void
    {
        $claims = new ClaimsManager();
        
        // Test invalid audience (not string or array)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Claim 'aud' must be a string or array");
        
        $claims->setClaim('aud', 123);
    }

    public function testAudienceClaimValidationEmptyArray(): void
    {
        $claims = new ClaimsManager();
        
        // Test invalid audience (empty array)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Audience array cannot be empty");
        
        $claims->setAudience([]);
    }

    public function testAudienceNormalization(): void
    {
        $claims = new ClaimsManager();
        
        // Test string audience is normalized to array
        $claims->setAudience('test-audience');
        $this->assertEquals(['test-audience'], $claims->getAudience());
        
        // Test array audience remains array
        $claims->setAudience(['aud1', 'aud2']);
        $this->assertEquals(['aud1', 'aud2'], $claims->getAudience());
    }

    public function testChainableMethods(): void
    {
        $claims = new ClaimsManager();
        
        $result = $claims
            ->setIssuer('issuer')
            ->setSubject('subject')
            ->setAudience('audience')
            ->setClaim('custom', 'value');
        
        $this->assertSame($claims, $result);
        $this->assertEquals('issuer', $claims->getIssuer());
        $this->assertEquals('subject', $claims->getSubject());
        $this->assertEquals(['audience'], $claims->getAudience());
        $this->assertEquals('value', $claims->getClaim('custom'));
    }

    public function testToJsonFailure(): void
    {
        $claims = new ClaimsManager();
        
        // Create a claim that cannot be JSON encoded (resource)
        $resource = fopen('php://memory', 'r');
        $claims->setClaim('resource', $resource);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to encode claims to JSON');
        
        $claims->toJson();
        
        fclose($resource);
    }
}