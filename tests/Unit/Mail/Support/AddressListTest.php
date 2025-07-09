<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Support;

use LengthOfRope\TreeHouse\Mail\Support\Address;
use LengthOfRope\TreeHouse\Mail\Support\AddressList;
use Tests\TestCase;
use InvalidArgumentException;

/**
 * AddressList Test
 * 
 * Tests for the email address list class
 */
class AddressListTest extends TestCase
{
    public function testCreateEmptyList(): void
    {
        $list = new AddressList();
        
        $this->assertTrue($list->isEmpty());
        $this->assertEquals(0, $list->count());
        $this->assertEquals(0, count($list));
    }

    public function testCreateListWithAddresses(): void
    {
        $addresses = [
            new Address('user1@example.com'),
            new Address('user2@example.com'),
        ];
        
        $list = new AddressList($addresses);
        
        $this->assertFalse($list->isEmpty());
        $this->assertEquals(2, $list->count());
    }

    public function testAddStringAddress(): void
    {
        $list = new AddressList();
        $result = $list->add('user@example.com');
        
        $this->assertSame($list, $result); // Test fluent interface
        $this->assertEquals(1, $list->count());
        $this->assertEquals('user@example.com', $list->first()->getEmail());
    }

    public function testAddAddressObject(): void
    {
        $list = new AddressList();
        $address = new Address('user@example.com', 'John Doe');
        $list->add($address);
        
        $this->assertEquals(1, $list->count());
        $this->assertSame($address, $list->first());
    }

    public function testRemoveAddress(): void
    {
        $list = new AddressList();
        $address = new Address('user@example.com');
        $list->add($address);
        $list->add('other@example.com');
        
        $this->assertEquals(2, $list->count());
        
        $result = $list->remove($address);
        $this->assertSame($list, $result); // Test fluent interface
        $this->assertEquals(1, $list->count());
        $this->assertEquals('other@example.com', $list->first()->getEmail());
    }

    public function testRemoveStringAddress(): void
    {
        $list = new AddressList();
        $list->add('user@example.com');
        $list->add('other@example.com');
        
        $list->remove('user@example.com');
        
        $this->assertEquals(1, $list->count());
        $this->assertEquals('other@example.com', $list->first()->getEmail());
    }

    public function testHasAddress(): void
    {
        $list = new AddressList();
        $address = new Address('user@example.com', 'John Doe');
        $list->add($address);
        
        $this->assertTrue($list->has($address));
        $this->assertTrue($list->has(new Address('user@example.com', 'John Doe')));
        $this->assertFalse($list->has('other@example.com'));
    }

    public function testAll(): void
    {
        $addresses = [
            new Address('user1@example.com'),
            new Address('user2@example.com'),
        ];
        
        $list = new AddressList($addresses);
        $all = $list->all();
        
        $this->assertEquals($addresses, $all);
    }

    public function testFirst(): void
    {
        $list = new AddressList();
        $this->assertNull($list->first());
        
        $address = new Address('user@example.com');
        $list->add($address);
        
        $this->assertSame($address, $list->first());
    }

    public function testLast(): void
    {
        $list = new AddressList();
        $this->assertNull($list->last());
        
        $address1 = new Address('user1@example.com');
        $address2 = new Address('user2@example.com');
        $list->add($address1);
        $list->add($address2);
        
        $this->assertSame($address2, $list->last());
    }

    public function testClear(): void
    {
        $list = new AddressList();
        $list->add('user1@example.com');
        $list->add('user2@example.com');
        
        $this->assertEquals(2, $list->count());
        
        $result = $list->clear();
        $this->assertSame($list, $result); // Test fluent interface
        $this->assertTrue($list->isEmpty());
        $this->assertEquals(0, $list->count());
    }

    public function testToString(): void
    {
        $list = new AddressList();
        $list->add(new Address('user1@example.com', 'John Doe'));
        $list->add(new Address('user2@example.com'));
        
        $expected = '"John Doe" <user1@example.com>, user2@example.com';
        $this->assertEquals($expected, $list->toString());
        $this->assertEquals($expected, (string) $list);
    }

    public function testToArray(): void
    {
        $list = new AddressList();
        $list->add(new Address('user1@example.com', 'John Doe'));
        $list->add(new Address('user2@example.com'));
        
        $expected = [
            ['email' => 'user1@example.com', 'name' => 'John Doe'],
            ['email' => 'user2@example.com', 'name' => null],
        ];
        
        $this->assertEquals($expected, $list->toArray());
    }

    public function testFromArray(): void
    {
        $data = [
            ['email' => 'user1@example.com', 'name' => 'John Doe'],
            ['email' => 'user2@example.com', 'name' => null],
        ];
        
        $list = AddressList::fromArray($data);
        
        $this->assertEquals(2, $list->count());
        $this->assertEquals('user1@example.com', $list->first()->getEmail());
        $this->assertEquals('John Doe', $list->first()->getName());
        $this->assertEquals('user2@example.com', $list->last()->getEmail());
        $this->assertNull($list->last()->getName());
    }

    public function testParseString(): void
    {
        $input = 'user1@example.com, user2@example.com';
        $list = AddressList::parse($input);
        
        $this->assertEquals(2, $list->count());
        $this->assertEquals('user1@example.com', $list->first()->getEmail());
        $this->assertEquals('user2@example.com', $list->last()->getEmail());
    }

    public function testParseStringWithNames(): void
    {
        $input = '"John Doe" <user1@example.com>, user2@example.com';
        $list = AddressList::parse($input);
        
        $this->assertEquals(2, $list->count());
        $this->assertEquals('user1@example.com', $list->first()->getEmail());
        $this->assertEquals('John Doe', $list->first()->getName());
        $this->assertEquals('user2@example.com', $list->last()->getEmail());
        $this->assertNull($list->last()->getName());
    }

    public function testParseArray(): void
    {
        $input = ['user1@example.com', 'user2@example.com'];
        $list = AddressList::parse($input);
        
        $this->assertEquals(2, $list->count());
        $this->assertEquals('user1@example.com', $list->first()->getEmail());
        $this->assertEquals('user2@example.com', $list->last()->getEmail());
    }

    public function testParseAddress(): void
    {
        $address = new Address('user@example.com');
        $list = AddressList::parse($address);
        
        $this->assertEquals(1, $list->count());
        $this->assertSame($address, $list->first());
    }

    public function testParseAddressList(): void
    {
        $originalList = new AddressList();
        $originalList->add('user@example.com');
        
        $newList = AddressList::parse($originalList);
        
        $this->assertNotSame($originalList, $newList); // Should be a clone
        $this->assertEquals(1, $newList->count());
        $this->assertEquals('user@example.com', $newList->first()->getEmail());
    }

    public function testParseInvalidInput(): void
    {
        $this->expectException(\TypeError::class);
        
        // @phpstan-ignore-next-line
        AddressList::parse(123);
    }

    // ArrayAccess tests

    public function testArrayAccessOffsetExists(): void
    {
        $list = new AddressList();
        $list->add('user@example.com');
        
        $this->assertTrue(isset($list[0]));
        $this->assertFalse(isset($list[1]));
    }

    public function testArrayAccessOffsetGet(): void
    {
        $list = new AddressList();
        $address = new Address('user@example.com');
        $list->add($address);
        
        $this->assertSame($address, $list[0]);
        $this->assertNull($list[1]);
    }

    public function testArrayAccessOffsetSet(): void
    {
        $list = new AddressList();
        $address = new Address('user@example.com');
        
        $list[0] = $address;
        $this->assertSame($address, $list[0]);
        
        $list[] = 'user2@example.com';
        $this->assertEquals('user2@example.com', $list[1]->getEmail());
    }

    public function testArrayAccessOffsetUnset(): void
    {
        $list = new AddressList();
        $list->add('user1@example.com');
        $list->add('user2@example.com');
        
        $this->assertEquals(2, $list->count());
        
        unset($list[0]);
        
        $this->assertEquals(1, $list->count());
        $this->assertEquals('user2@example.com', $list[0]->getEmail());
    }

    // Iterator tests

    public function testIteration(): void
    {
        $list = new AddressList();
        $list->add('user1@example.com');
        $list->add('user2@example.com');
        
        $emails = [];
        foreach ($list as $index => $address) {
            $emails[$index] = $address->getEmail();
        }
        
        $expected = [
            0 => 'user1@example.com',
            1 => 'user2@example.com',
        ];
        
        $this->assertEquals($expected, $emails);
    }

    public function testIterationEmpty(): void
    {
        $list = new AddressList();
        
        $count = 0;
        foreach ($list as $address) {
            $count++;
        }
        
        $this->assertEquals(0, $count);
    }
}