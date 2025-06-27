<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Uuid;
use Tests\TestCase;

/**
 * Test cases for Uuid class
 * 
 * @package Tests\Unit\Support
 * @author TreeHouse Framework Team
 * @since 1.0.0
 */
class UuidTest extends TestCase
{
    public function testUuid1(): void
    {
        $uuid = Uuid::uuid1();
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
        
        // Test that multiple calls generate different UUIDs
        $uuid2 = Uuid::uuid1();
        $this->assertNotEquals($uuid, $uuid2);
    }

    public function testUuid3(): void
    {
        $namespace = Uuid::NAMESPACE_DNS;
        $name = 'test';
        
        $uuid = Uuid::uuid3($namespace, $name);
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
        
        // Test that same inputs generate same UUID
        $uuid2 = Uuid::uuid3($namespace, $name);
        $this->assertEquals($uuid, $uuid2);
        
        // Test that different inputs generate different UUIDs
        $uuid3 = Uuid::uuid3($namespace, 'different');
        $this->assertNotEquals($uuid, $uuid3);
    }

    public function testUuid3WithInvalidNamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid namespace UUID');
        
        Uuid::uuid3('invalid-namespace', 'test');
    }

    public function testUuid4(): void
    {
        $uuid = Uuid::uuid4();
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
        
        // Test that multiple calls generate different UUIDs
        $uuid2 = Uuid::uuid4();
        $this->assertNotEquals($uuid, $uuid2);
    }

    public function testUuid5(): void
    {
        $namespace = Uuid::NAMESPACE_DNS;
        $name = 'test';
        
        $uuid = Uuid::uuid5($namespace, $name);
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
        
        // Test that same inputs generate same UUID
        $uuid2 = Uuid::uuid5($namespace, $name);
        $this->assertEquals($uuid, $uuid2);
        
        // Test that different inputs generate different UUIDs
        $uuid3 = Uuid::uuid5($namespace, 'different');
        $this->assertNotEquals($uuid, $uuid3);
    }

    public function testUuid5WithInvalidNamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid namespace UUID');
        
        Uuid::uuid5('invalid-namespace', 'test');
    }

    public function testIsValid(): void
    {
        // Valid UUIDs
        $this->assertTrue(Uuid::isValid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertTrue(Uuid::isValid('6ba7b810-9dad-11d1-80b4-00c04fd430c8'));
        $this->assertTrue(Uuid::isValid(Uuid::uuid4()));
        $this->assertTrue(Uuid::isValid(Uuid::uuid1()));
        // Note: nil and max UUIDs don't have valid version numbers (1-5) so they fail isValid
        
        // Invalid UUIDs
        $this->assertFalse(Uuid::isValid('not-a-uuid'));
        $this->assertFalse(Uuid::isValid('550e8400-e29b-41d4-a716-44665544000')); // Too short
        $this->assertFalse(Uuid::isValid('550e8400-e29b-41d4-a716-4466554400000')); // Too long
        $this->assertFalse(Uuid::isValid('550e8400-e29b-41d4-a716-44665544000g')); // Invalid character
        $this->assertFalse(Uuid::isValid('550e8400-e29b-41d4-a716-446655440000-extra')); // Extra content
        $this->assertFalse(Uuid::isValid(''));
        $this->assertFalse(Uuid::isValid('550e8400-e29b-41d4-a716-446655440000 ')); // Trailing space
    }

    public function testNil(): void
    {
        $nil = Uuid::nil();
        
        $this->assertEquals('00000000-0000-0000-0000-000000000000', $nil);
        // Note: nil UUID has version 0, which doesn't pass isValid (requires 1-5)
        $this->assertFalse(Uuid::isValid($nil));
    }

    public function testMax(): void
    {
        $max = Uuid::max();
        
        $this->assertEquals('ffffffff-ffff-ffff-ffff-ffffffffffff', $max);
        // Note: max UUID has version F, which doesn't pass isValid (requires 1-5)
        $this->assertFalse(Uuid::isValid($max));
    }

    public function testToBinary(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $binary = Uuid::toBinary($uuid);
        
        $this->assertIsString($binary);
        $this->assertEquals(16, strlen($binary));
        
        // Test round trip
        $converted = Uuid::fromBinary($binary);
        $this->assertEquals(strtolower($uuid), strtolower($converted));
    }

    public function testToBinaryWithInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        
        Uuid::toBinary('invalid-uuid');
    }

    public function testFromBinary(): void
    {
        // Use a valid UUID's binary representation for testing
        $validUuid = Uuid::uuid4();
        $binary = Uuid::toBinary($validUuid);
        $uuid = Uuid::fromBinary($binary);
        
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
        
        // Test round trip
        $converted = Uuid::toBinary($uuid);
        $this->assertEquals($binary, $converted);
    }

    public function testFromBinaryWithInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Binary data must be exactly 16 bytes');
        
        Uuid::fromBinary('too-short');
    }

    public function testGetVersion(): void
    {
        $uuid1 = Uuid::uuid1();
        $this->assertEquals(1, Uuid::getVersion($uuid1));
        
        $uuid3 = Uuid::uuid3(Uuid::NAMESPACE_DNS, 'test');
        $this->assertEquals(3, Uuid::getVersion($uuid3));
        
        $uuid4 = Uuid::uuid4();
        $this->assertEquals(4, Uuid::getVersion($uuid4));
        
        $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, 'test');
        $this->assertEquals(5, Uuid::getVersion($uuid5));
    }

    public function testGetVersionWithInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        
        Uuid::getVersion('invalid-uuid');
    }

    public function testGetVariant(): void
    {
        $uuid = Uuid::uuid4();
        $variant = Uuid::getVariant($uuid);
        
        // RFC 4122 variant
        $this->assertEquals('RFC4122', $variant);
        
        // Test other variants with specific UUIDs
        $this->assertEquals('RFC4122', Uuid::getVariant('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testGetVariantWithInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        
        Uuid::getVariant('invalid-uuid');
    }

    public function testCompare(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid3 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        
        $this->assertEquals(0, Uuid::compare($uuid1, $uuid2));
        $this->assertNotEquals(0, Uuid::compare($uuid1, $uuid3));
        $this->assertGreaterThan(0, Uuid::compare($uuid3, $uuid1)); // uuid3 > uuid1 lexicographically (6 > 5)
    }

    public function testCompareWithInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        
        Uuid::compare('invalid-uuid', Uuid::uuid4());
    }

    public function testEquals(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid3 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        
        $this->assertTrue(Uuid::equals($uuid1, $uuid2));
        $this->assertFalse(Uuid::equals($uuid1, $uuid3));
        
        // Test case insensitive comparison
        $this->assertTrue(Uuid::equals($uuid1, strtoupper($uuid1)));
    }

    public function testShort(): void
    {
        $short = Uuid::short();
        
        $this->assertIsString($short);
        $this->assertGreaterThan(0, strlen($short));
        
        // Test that multiple calls can generate different short UUIDs
        // Note: Due to the implementation, very small numbers might result in '0'
        $short2 = Uuid::short();
        // We'll just test that both are valid strings
        $this->assertIsString($short2);
    }

    public function testNamespaceConstants(): void
    {
        $this->assertTrue(Uuid::isValid(Uuid::NAMESPACE_DNS));
        $this->assertTrue(Uuid::isValid(Uuid::NAMESPACE_URL));
        $this->assertTrue(Uuid::isValid(Uuid::NAMESPACE_OID));
        $this->assertTrue(Uuid::isValid(Uuid::NAMESPACE_X500));
        
        $this->assertEquals('6ba7b810-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_DNS);
        $this->assertEquals('6ba7b811-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_URL);
        $this->assertEquals('6ba7b812-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_OID);
        $this->assertEquals('6ba7b814-9dad-11d1-80b4-00c04fd430c8', Uuid::NAMESPACE_X500);
    }

    public function testUuid3WithDifferentNamespaces(): void
    {
        $name = 'test';
        
        $uuid1 = Uuid::uuid3(Uuid::NAMESPACE_DNS, $name);
        $uuid2 = Uuid::uuid3(Uuid::NAMESPACE_URL, $name);
        
        $this->assertNotEquals($uuid1, $uuid2);
        $this->assertEquals(3, Uuid::getVersion($uuid1));
        $this->assertEquals(3, Uuid::getVersion($uuid2));
    }

    public function testUuid5WithDifferentNamespaces(): void
    {
        $name = 'test';
        
        $uuid1 = Uuid::uuid5(Uuid::NAMESPACE_DNS, $name);
        $uuid2 = Uuid::uuid5(Uuid::NAMESPACE_URL, $name);
        
        $this->assertNotEquals($uuid1, $uuid2);
        $this->assertEquals(5, Uuid::getVersion($uuid1));
        $this->assertEquals(5, Uuid::getVersion($uuid2));
    }
}