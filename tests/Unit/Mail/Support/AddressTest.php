<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Support;

use LengthOfRope\TreeHouse\Mail\Support\Address;
use Tests\TestCase;
use InvalidArgumentException;

/**
 * Address Test
 * 
 * Tests for the email address class
 */
class AddressTest extends TestCase
{
    public function testCreateSimpleAddress(): void
    {
        $address = new Address('user@example.com');
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertNull($address->getName());
    }

    public function testCreateAddressWithName(): void
    {
        $address = new Address('user@example.com', 'John Doe');
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertEquals('John Doe', $address->getName());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address: invalid-email');
        
        new Address('invalid-email');
    }

    public function testSetName(): void
    {
        $address = new Address('user@example.com');
        $result = $address->setName('Jane Doe');
        
        $this->assertSame($address, $result); // Test fluent interface
        $this->assertEquals('Jane Doe', $address->getName());
    }

    public function testToStringWithoutName(): void
    {
        $address = new Address('user@example.com');
        
        $this->assertEquals('user@example.com', $address->toString());
        $this->assertEquals('user@example.com', (string) $address);
    }

    public function testToStringWithName(): void
    {
        $address = new Address('user@example.com', 'John Doe');
        
        $expected = '"John Doe" <user@example.com>';
        $this->assertEquals($expected, $address->toString());
        $this->assertEquals($expected, (string) $address);
    }

    public function testParseSimpleEmail(): void
    {
        $address = Address::parse('user@example.com');
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertNull($address->getName());
    }

    public function testParseEmailWithName(): void
    {
        $address = Address::parse('"John Doe" <user@example.com>');
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertEquals('John Doe', $address->getName());
    }

    public function testParseEmailWithNameWithoutQuotes(): void
    {
        $address = Address::parse('John Doe <user@example.com>');
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertEquals('John Doe', $address->getName());
    }

    public function testParseEmailWithEmptyName(): void
    {
        $address = Address::parse('"" <user@example.com>');
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertNull($address->getName());
    }

    public function testToArray(): void
    {
        $address = new Address('user@example.com', 'John Doe');
        $array = $address->toArray();
        
        $expected = [
            'email' => 'user@example.com',
            'name' => 'John Doe',
        ];
        
        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithoutName(): void
    {
        $address = new Address('user@example.com');
        $array = $address->toArray();
        
        $expected = [
            'email' => 'user@example.com',
            'name' => null,
        ];
        
        $this->assertEquals($expected, $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'email' => 'user@example.com',
            'name' => 'John Doe',
        ];
        
        $address = Address::fromArray($data);
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertEquals('John Doe', $address->getName());
    }

    public function testFromArrayWithoutName(): void
    {
        $data = [
            'email' => 'user@example.com',
        ];
        
        $address = Address::fromArray($data);
        
        $this->assertEquals('user@example.com', $address->getEmail());
        $this->assertNull($address->getName());
    }

    public function testEquals(): void
    {
        $address1 = new Address('user@example.com', 'John Doe');
        $address2 = new Address('user@example.com', 'John Doe');
        $address3 = new Address('user@example.com', 'Jane Doe');
        $address4 = new Address('other@example.com', 'John Doe');
        
        $this->assertTrue($address1->equals($address2));
        $this->assertFalse($address1->equals($address3));
        $this->assertFalse($address1->equals($address4));
    }

    public function testEqualsWithoutName(): void
    {
        $address1 = new Address('user@example.com');
        $address2 = new Address('user@example.com');
        $address3 = new Address('user@example.com', 'John Doe');
        
        $this->assertTrue($address1->equals($address2));
        $this->assertFalse($address1->equals($address3));
    }

    public function testValidEmailFormats(): void
    {
        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.com',
            'user_name@example.com',
            'user123@example.com',
            'test@sub.domain.com',
        ];
        
        foreach ($validEmails as $email) {
            $address = new Address($email);
            $this->assertEquals($email, $address->getEmail());
        }
    }

    public function testInvalidEmailFormats(): void
    {
        $invalidEmails = [
            'invalid',
            '@example.com',
            'user@',
            'user..name@example.com',
            'user@.com',
            'user@com',
        ];
        
        foreach ($invalidEmails as $email) {
            try {
                new Address($email);
                $this->fail("Expected InvalidArgumentException for email: {$email}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid email address', $e->getMessage());
            }
        }
    }
}