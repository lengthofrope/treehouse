<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use LengthOfRope\TreeHouse\Http\Session;
use Tests\TestCase;
use RuntimeException;

/**
 * Session Test
 * 
 * @package Tests\Unit\Http
 */
class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock session for testing
        $this->session = new Session([
            'name' => 'test_session',
            'lifetime' => 3600,
            'path' => '/test',
            'domain' => 'test.com',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Clean up any active session before test
     */
    private function cleanupSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Clean up session more thoroughly
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Reset session data
        $_SESSION = [];
        
        // Clear any session cookies if they exist
        if (isset($_COOKIE[session_name()])) {
            unset($_COOKIE[session_name()]);
        }
        
        parent::tearDown();
    }

    public function testSessionConfiguration(): void
    {
        $this->cleanupSession();
        
        // Test session name setting
        $this->session->setName('custom_session');
        $this->assertEquals('custom_session', $this->session->getName());
    }

    public function testSessionIdManipulation(): void
    {
        $this->cleanupSession();
        
        // Set session ID before starting
        $testId = 'test_session_id_123';
        $result = $this->session->setId($testId);
        $this->assertTrue($result);
        
        // Start session and verify ID
        $this->session->start();
        $this->assertTrue($this->session->isStarted());
        
        $currentId = $this->session->getId();
        $this->assertIsString($currentId);
        $this->assertNotEmpty($currentId);
    }

    public function testBasicSessionOperations(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Test set and get
        $this->session->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->session->get('test_key'));
        
        // Test default value
        $this->assertEquals('default', $this->session->get('nonexistent', 'default'));
        
        // Test has
        $this->assertTrue($this->session->has('test_key'));
        $this->assertFalse($this->session->has('nonexistent'));
        
        // Test remove
        $this->session->remove('test_key');
        $this->assertFalse($this->session->has('test_key'));
    }

    public function testNestedSessionData(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Test nested data
        $this->session->set('user.name', 'John');
        $this->session->set('user.email', 'john@example.com');
        
        $this->assertEquals('John', $this->session->get('user.name'));
        $this->assertEquals('john@example.com', $this->session->get('user.email'));
        
        $this->assertTrue($this->session->has('user.name'));
        $this->assertTrue($this->session->has('user.email'));
    }

    public function testSessionClear(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        
        $this->assertTrue($this->session->has('key1'));
        $this->assertTrue($this->session->has('key2'));
        
        $this->session->clear();
        
        $this->assertFalse($this->session->has('key1'));
        $this->assertFalse($this->session->has('key2'));
    }

    public function testPullOperation(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        $this->session->set('pull_test', 'pull_value');
        
        // Pull should return value and remove it
        $value = $this->session->pull('pull_test');
        $this->assertEquals('pull_value', $value);
        $this->assertFalse($this->session->has('pull_test'));
        
        // Pull non-existent with default
        $defaultValue = $this->session->pull('nonexistent', 'default');
        $this->assertEquals('default', $defaultValue);
    }

    public function testIncrementDecrement(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Test increment
        $result = $this->session->increment('counter');
        $this->assertEquals(1, $result);
        $this->assertEquals(1, $this->session->get('counter'));
        
        $result = $this->session->increment('counter', 5);
        $this->assertEquals(6, $result);
        $this->assertEquals(6, $this->session->get('counter'));
        
        // Test decrement
        $result = $this->session->decrement('counter', 2);
        $this->assertEquals(4, $result);
        $this->assertEquals(4, $this->session->get('counter'));
    }

    public function testFlashData(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Set flash data
        $this->session->flash('message', 'Success!');
        $this->session->flash('error', 'Something went wrong');
        
        // Flash data should not be immediately available
        $this->assertNull($this->session->getFlash('message'));
        $this->assertFalse($this->session->hasFlash('message'));
        
        // Simulate request cycle by processing flash data
        $this->session->save();
        
        // Start new session to simulate next request
        $newSession = new Session(['name' => 'test_session']);
        $newSession->start();
        
        // Flash data should now be available
        $this->assertEquals('Success!', $newSession->getFlash('message'));
        $this->assertEquals('Something went wrong', $newSession->getFlash('error'));
        $this->assertTrue($newSession->hasFlash('message'));
        
        // Test default value
        $this->assertEquals('default', $newSession->getFlash('nonexistent', 'default'));
    }

    public function testKeepFlash(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Set up flash data
        $_SESSION['_flash']['old'] = [
            'message' => 'Keep this',
            'temp' => 'Remove this'
        ];
        
        // Keep specific flash data
        $this->session->keepFlash('message');
        
        // Process flash data
        $this->session->save();
        
        // Check that kept data is moved to new
        $this->assertArrayHasKey('message', $_SESSION['_flash']['new'] ?? []);
        $this->assertArrayNotHasKey('temp', $_SESSION['_flash']['new'] ?? []);
    }

    public function testReflash(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Set up flash data
        $_SESSION['_flash']['old'] = [
            'message' => 'Reflash this',
            'error' => 'And this too'
        ];
        $_SESSION['_flash']['new'] = [
            'info' => 'Keep this new one'
        ];
        
        // Reflash all old data
        $this->session->reflash();
        
        // Check that all old data is moved to new
        $expected = [
            'info' => 'Keep this new one',
            'message' => 'Reflash this',
            'error' => 'And this too'
        ];
        
        $this->assertEquals($expected, $_SESSION['_flash']['new']);
    }

    public function testCsrfToken(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Get token (should generate one)
        $token1 = $this->session->token();
        $this->assertIsString($token1);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
        
        // Get token again (should be same)
        $token2 = $this->session->token();
        $this->assertEquals($token1, $token2);
        
        // Validate token
        $this->assertTrue($this->session->validateToken($token1));
        $this->assertFalse($this->session->validateToken('invalid_token'));
        
        // Regenerate token
        $newToken = $this->session->regenerateToken();
        $this->assertNotEquals($token1, $newToken);
        $this->assertTrue($this->session->validateToken($newToken));
        $this->assertFalse($this->session->validateToken($token1));
    }

    public function testSessionRegeneration(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        $originalId = $this->session->getId();
        
        // Regenerate session ID
        $result = $this->session->regenerate();
        $this->assertTrue($result);
        
        $newId = $this->session->getId();
        $this->assertNotEquals($originalId, $newId);
    }

    public function testSessionDestroy(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        // Set some data
        $this->session->set('test', 'value');
        $this->assertTrue($this->session->has('test'));
        
        // Destroy session
        $result = $this->session->destroy();
        $this->assertTrue($result);
        $this->assertFalse($this->session->isStarted());
    }

    public function testOperationsWithoutStarting(): void
    {
        $this->cleanupSession();
        // Operations should automatically start session
        $this->session->set('auto_start', 'test');
        $this->assertTrue($this->session->isStarted());
        $this->assertEquals('test', $this->session->get('auto_start'));
    }

    public function testAllSessionData(): void
    {
        $this->cleanupSession();
        $this->session->start();
        
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        
        $all = $this->session->all();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
        $this->assertEquals('value1', $all['key1']);
        $this->assertEquals('value2', $all['key2']);
    }
}