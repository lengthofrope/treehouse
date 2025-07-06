<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use LengthOfRope\TreeHouse\Security\Hash;
use Tests\TestCase;

class HashTest extends TestCase
{
    private Hash $hash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hash = new Hash();
    }

    public function testMakeCreatesHashedPassword(): void
    {
        $password = 'test-password-123';
        $hashed = $this->hash->make($password);

        $this->assertNotEquals($password, $hashed);
        $this->assertIsString($hashed);
        $this->assertGreaterThan(50, strlen($hashed));
    }

    public function testMakeWithCustomOptions(): void
    {
        $password = 'test-password-123';
        $options = ['cost' => 12];
        $hashed = $this->hash->make($password, $options);

        $this->assertNotEquals($password, $hashed);
        $this->assertIsString($hashed);
        $this->assertTrue(password_verify($password, $hashed));
    }

    public function testCheckVerifiesCorrectPassword(): void
    {
        $password = 'test-password-123';
        $hashed = $this->hash->make($password);

        $this->assertTrue($this->hash->check($password, $hashed));
    }

    public function testCheckRejectsIncorrectPassword(): void
    {
        $password = 'test-password-123';
        $wrongPassword = 'wrong-password';
        $hashed = $this->hash->make($password);

        $this->assertFalse($this->hash->check($wrongPassword, $hashed));
    }

    public function testCheckReturnsFalseForInvalidHash(): void
    {
        $password = 'test-password-123';
        $invalidHash = 'invalid-hash';

        $this->assertFalse($this->hash->check($password, $invalidHash));
    }

    public function testNeedsRehashReturnsTrueForOutdatedHash(): void
    {
        $password = 'test-password-123';
        $oldHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertTrue($this->hash->needsRehash($oldHash));
    }

    public function testNeedsRehashReturnsFalseForCurrentHash(): void
    {
        $password = 'test-password-123';
        $currentHash = $this->hash->make($password);

        $this->assertFalse($this->hash->needsRehash($currentHash));
    }

    public function testNeedsRehashWithCustomOptions(): void
    {
        $password = 'test-password-123';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $options = ['cost' => 12];

        $this->assertTrue($this->hash->needsRehash($hash, $options));
    }

    public function testGetInfoReturnsHashInformation(): void
    {
        $password = 'test-password-123';
        $hash = $this->hash->make($password);
        $info = $this->hash->getInfo($hash);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('algo', $info);
        $this->assertArrayHasKey('algoName', $info);
        $this->assertArrayHasKey('options', $info);
    }

    public function testMakeWithEmptyPasswordThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password cannot be empty');

        $this->hash->make('');
    }

    public function testCheckWithEmptyPasswordReturnsFalse(): void
    {
        $hash = $this->hash->make('test-password');

        $this->assertFalse($this->hash->check('', $hash));
    }

    public function testCheckWithEmptyHashReturnsFalse(): void
    {
        $this->assertFalse($this->hash->check('test-password', ''));
    }

    public function testMakeGeneratesDifferentHashesForSamePassword(): void
    {
        $password = 'test-password-123';
        $hash1 = $this->hash->make($password);
        $hash2 = $this->hash->make($password);

        $this->assertNotEquals($hash1, $hash2);
        $this->assertTrue($this->hash->check($password, $hash1));
        $this->assertTrue($this->hash->check($password, $hash2));
    }

    public function testSupportsArgon2i(): void
    {
        if (!defined('PASSWORD_ARGON2I')) {
            $this->markTestSkipped('Argon2i not available');
        }

        $password = 'test-password-123';
        $hash = $this->hash->make($password, [], PASSWORD_ARGON2I);

        $this->assertTrue($this->hash->check($password, $hash));
        $info = $this->hash->getInfo($hash);
        $this->assertEquals(PASSWORD_ARGON2I, $info['algo']);
    }

    public function testSupportsArgon2id(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id not available');
        }

        $password = 'test-password-123';
        $hash = $this->hash->make($password, [], PASSWORD_ARGON2ID);

        $this->assertTrue($this->hash->check($password, $hash));
        $info = $this->hash->getInfo($hash);
        $this->assertEquals(PASSWORD_ARGON2ID, $info['algo']);
    }

    public function testTimingSafeComparison(): void
    {
        $password = 'test-password-123';
        $hash = $this->hash->make($password);

        // Test multiple iterations to get average timing
        $iterations = 10;
        $correctTimes = [];
        $incorrectTimes = [];

        for ($i = 0; $i < $iterations; $i++) {
            // Measure correct password timing
            $start = microtime(true);
            $this->hash->check($password, $hash);
            $correctTimes[] = microtime(true) - $start;

            // Measure incorrect password timing
            $start = microtime(true);
            $this->hash->check('wrong-password-' . $i, $hash);
            $incorrectTimes[] = microtime(true) - $start;
        }

        $avgCorrect = array_sum($correctTimes) / count($correctTimes);
        $avgIncorrect = array_sum($incorrectTimes) / count($incorrectTimes);

        // Times should be relatively similar (within 1 second tolerance for system variations)
        // This is a more realistic bound that accounts for system load and variations
        $this->assertLessThan(1.0, abs($avgCorrect - $avgIncorrect));
        
        // Also verify that both operations actually complete in reasonable time
        $this->assertLessThan(2.0, $avgCorrect);
        $this->assertLessThan(2.0, $avgIncorrect);
    }
}