<?php

declare(strict_types=1);

namespace TreeHouse\Tests\Unit\Support;

use LengthOfRope\TreeHouse\Support\Env;
use PHPUnit\Framework\TestCase;

/**
 * Environment Variable Tests
 * 
 * Tests for the Env class that handles .env file loading and environment
 * variable access with type conversion.
 */
class EnvTest extends TestCase
{
    private string $tempEnvFile;
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Backup original environment
        $this->originalEnv = $_ENV;
        
        // Create temporary .env file
        $this->tempEnvFile = tempnam(sys_get_temp_dir(), 'test_env_');
        
        // Clear any existing cache
        Env::clearCache();
    }

    protected function tearDown(): void
    {
        // Restore original environment
        $_ENV = $this->originalEnv;
        
        // Clean up temporary file
        if (file_exists($this->tempEnvFile)) {
            unlink($this->tempEnvFile);
        }
        
        // Clear cache
        Env::clearCache();
        
        parent::tearDown();
    }

    public function testLoadEnvFile(): void
    {
        // Create test .env content
        $envContent = "TEST_VAR=test_value\nTEST_NUMBER=123\nTEST_BOOL=true";
        file_put_contents($this->tempEnvFile, $envContent);
        
        // Load the env file
        Env::load($this->tempEnvFile);
        
        // Test that variables are loaded
        $this->assertEquals('test_value', Env::get('TEST_VAR'));
        $this->assertEquals(123, Env::get('TEST_NUMBER'));
        $this->assertTrue(Env::get('TEST_BOOL'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertEquals('default_value', Env::get('NON_EXISTENT_VAR', 'default_value'));
        $this->assertNull(Env::get('NON_EXISTENT_VAR'));
    }

    public function testSetAndGet(): void
    {
        Env::set('CUSTOM_VAR', 'custom_value');
        $this->assertEquals('custom_value', Env::get('CUSTOM_VAR'));
    }

    public function testHas(): void
    {
        Env::set('EXISTING_VAR', 'value');
        $this->assertTrue(Env::has('EXISTING_VAR'));
        $this->assertFalse(Env::has('NON_EXISTENT_VAR'));
    }

    public function testTypeConversion(): void
    {
        $envContent = [
            'BOOL_TRUE=true',
            'BOOL_FALSE=false',
            'NULL_VAL=null',
            'EMPTY_VAL=empty',
            'INT_VAL=42',
            'FLOAT_VAL=3.14',
            'STRING_VAL=hello world'
        ];
        
        file_put_contents($this->tempEnvFile, implode("\n", $envContent));
        Env::load($this->tempEnvFile);
        
        $this->assertTrue(Env::get('BOOL_TRUE'));
        $this->assertFalse(Env::get('BOOL_FALSE'));
        $this->assertNull(Env::get('NULL_VAL'));
        $this->assertEquals('', Env::get('EMPTY_VAL'));
        $this->assertEquals(42, Env::get('INT_VAL'));
        $this->assertEquals(3.14, Env::get('FLOAT_VAL'));
        $this->assertEquals('hello world', Env::get('STRING_VAL'));
    }

    public function testQuotedValues(): void
    {
        $envContent = [
            'QUOTED_DOUBLE="quoted value"',
            'QUOTED_SINGLE=\'single quoted\'',
            'QUOTED_WITH_SPACES="  spaced  "',
            'ESCAPED_CHARS="line1\\nline2\\ttab"'
        ];
        
        file_put_contents($this->tempEnvFile, implode("\n", $envContent));
        Env::load($this->tempEnvFile);
        
        $this->assertEquals('quoted value', Env::get('QUOTED_DOUBLE'));
        $this->assertEquals('single quoted', Env::get('QUOTED_SINGLE'));
        $this->assertEquals('  spaced  ', Env::get('QUOTED_WITH_SPACES'));
        $this->assertEquals("line1\nline2\ttab", Env::get('ESCAPED_CHARS'));
    }

    public function testCommentsAndEmptyLines(): void
    {
        $envContent = [
            '# This is a comment',
            '',
            'VALID_VAR=valid_value',
            '# Another comment',
            '  # Indented comment',
            'ANOTHER_VAR=another_value'
        ];
        
        file_put_contents($this->tempEnvFile, implode("\n", $envContent));
        Env::load($this->tempEnvFile);
        
        $this->assertEquals('valid_value', Env::get('VALID_VAR'));
        $this->assertEquals('another_value', Env::get('ANOTHER_VAR'));
    }

    public function testReload(): void
    {
        // Initial content
        file_put_contents($this->tempEnvFile, 'VAR1=initial');
        Env::load($this->tempEnvFile);
        $this->assertEquals('initial', Env::get('VAR1'));
        
        // Update content
        file_put_contents($this->tempEnvFile, 'VAR1=updated');
        Env::reload($this->tempEnvFile);
        $this->assertEquals('updated', Env::get('VAR1'));
    }

    public function testAll(): void
    {
        $envContent = [
            'VAR1=value1',
            'VAR2=123',
            'VAR3=true'
        ];
        
        file_put_contents($this->tempEnvFile, implode("\n", $envContent));
        Env::load($this->tempEnvFile);
        
        $all = Env::all();
        $this->assertEquals('value1', $all['VAR1']);
        $this->assertEquals(123, $all['VAR2']);
        $this->assertTrue($all['VAR3']);
    }

    public function testGlobalEnvFunction(): void
    {
        // Test the global env() helper function
        Env::set('GLOBAL_TEST', 'global_value');
        $this->assertEquals('global_value', env('GLOBAL_TEST'));
        $this->assertEquals('default', env('NON_EXISTENT', 'default'));
    }
}