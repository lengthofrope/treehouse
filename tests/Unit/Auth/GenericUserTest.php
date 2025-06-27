<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use LengthOfRope\TreeHouse\Auth\GenericUser;
use Tests\TestCase;

/**
 * GenericUser Tests
 *
 * @package Tests\Unit\Auth
 */
class GenericUserTest extends TestCase
{
    protected GenericUser $user;
    protected array $userData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'hashed_password',
            'remember_token' => 'remember_token_123'
        ];
        
        $this->user = new GenericUser($this->userData);
    }

    public function testConstructorSetsAttributes(): void
    {
        $this->assertEquals($this->userData, $this->user->getAttributes());
    }

    public function testGetAuthIdentifier(): void
    {
        $this->assertEquals(1, $this->user->getAuthIdentifier());
    }

    public function testGetAuthIdentifierReturnsNullWhenNoId(): void
    {
        $user = new GenericUser(['name' => 'John']);
        $this->assertNull($user->getAuthIdentifier());
    }

    public function testGetAuthPassword(): void
    {
        $this->assertEquals('hashed_password', $this->user->getAuthPassword());
    }

    public function testGetAuthPasswordReturnsEmptyStringWhenNoPassword(): void
    {
        $user = new GenericUser(['name' => 'John']);
        $this->assertEquals('', $user->getAuthPassword());
    }

    public function testGetRememberToken(): void
    {
        $this->assertEquals('remember_token_123', $this->user->getRememberToken());
    }

    public function testGetRememberTokenReturnsNullWhenNoToken(): void
    {
        $user = new GenericUser(['name' => 'John']);
        $this->assertNull($user->getRememberToken());
    }

    public function testSetRememberToken(): void
    {
        $newToken = 'new_token_456';
        $this->user->setRememberToken($newToken);
        $this->assertEquals($newToken, $this->user->getRememberToken());
    }

    public function testGetAttribute(): void
    {
        $this->assertEquals('John Doe', $this->user->getAttribute('name'));
        $this->assertEquals('default', $this->user->getAttribute('nonexistent', 'default'));
    }

    public function testSetAttribute(): void
    {
        $this->user->setAttribute('age', 30);
        $this->assertEquals(30, $this->user->getAttribute('age'));
    }

    public function testHasAttribute(): void
    {
        $this->assertTrue($this->user->hasAttribute('name'));
        $this->assertFalse($this->user->hasAttribute('nonexistent'));
    }

    public function testSetAttributes(): void
    {
        $newAttributes = ['id' => 2, 'name' => 'Jane Doe'];
        $this->user->setAttributes($newAttributes);
        $this->assertEquals($newAttributes, $this->user->getAttributes());
    }

    public function testToArray(): void
    {
        $this->assertEquals($this->userData, $this->user->toArray());
    }

    public function testToJson(): void
    {
        $expectedJson = json_encode($this->userData);
        $this->assertEquals($expectedJson, $this->user->toJson());
    }

    public function testMagicGet(): void
    {
        $this->assertEquals('John Doe', $this->user->name);
        $this->assertEquals('john@example.com', $this->user->email);
    }

    public function testMagicSet(): void
    {
        $this->user->age = 25;
        $this->assertEquals(25, $this->user->age);
    }

    public function testMagicIsset(): void
    {
        $this->assertTrue(isset($this->user->name));
        $this->assertFalse(isset($this->user->nonexistent));
    }

    public function testMagicUnset(): void
    {
        unset($this->user->name);
        $this->assertFalse($this->user->hasAttribute('name'));
    }

    public function testToString(): void
    {
        $expectedJson = json_encode($this->userData);
        $this->assertEquals($expectedJson, (string) $this->user);
    }
}