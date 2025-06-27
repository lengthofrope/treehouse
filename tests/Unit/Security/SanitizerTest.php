<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use LengthOfRope\TreeHouse\Security\Sanitizer;
use Tests\TestCase;

class SanitizerTest extends TestCase
{
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new Sanitizer();
    }

    public function testSanitizeStringRemovesHtmlTags(): void
    {
        $input = '<script>alert("xss")</script>Hello World<b>Bold</b>';
        $expected = 'alert("xss")Hello WorldBold';
        
        $result = $this->sanitizer->sanitizeString($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeStringTrimsWhitespace(): void
    {
        $input = '  Hello World  ';
        $expected = 'Hello World';
        
        $result = $this->sanitizer->sanitizeString($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeStringHandlesEmptyString(): void
    {
        $result = $this->sanitizer->sanitizeString('');
        
        $this->assertEquals('', $result);
    }

    public function testSanitizeStringHandlesNull(): void
    {
        $result = $this->sanitizer->sanitizeString(null);
        
        $this->assertEquals('', $result);
    }

    public function testSanitizeEmailReturnsValidEmail(): void
    {
        $input = 'test@example.com';
        $result = $this->sanitizer->sanitizeEmail($input);
        
        $this->assertEquals($input, $result);
    }

    public function testSanitizeEmailRemovesInvalidCharacters(): void
    {
        $input = 'test<script>@example.com';
        $expected = 'testscript@example.com';
        
        $result = $this->sanitizer->sanitizeEmail($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeEmailReturnsEmptyForInvalidEmail(): void
    {
        $input = 'not-an-email';
        $result = $this->sanitizer->sanitizeEmail($input);
        
        $this->assertEquals('', $result);
    }

    public function testSanitizeUrlReturnsValidUrl(): void
    {
        $input = 'https://example.com/path?param=value';
        $result = $this->sanitizer->sanitizeUrl($input);
        
        $this->assertEquals($input, $result);
    }

    public function testSanitizeUrlReturnsEmptyForInvalidUrl(): void
    {
        $input = 'not-a-url';
        $result = $this->sanitizer->sanitizeUrl($input);
        
        $this->assertEquals('', $result);
    }

    public function testSanitizeUrlBlocksJavascriptProtocol(): void
    {
        $input = 'javascript:alert("xss")';
        $result = $this->sanitizer->sanitizeUrl($input);
        
        $this->assertEquals('', $result);
    }

    public function testSanitizeUrlBlocksDataProtocol(): void
    {
        $input = 'data:text/html,<script>alert("xss")</script>';
        $result = $this->sanitizer->sanitizeUrl($input);
        
        $this->assertEquals('', $result);
    }

    public function testSanitizeUrlAllowsHttpAndHttps(): void
    {
        $httpUrl = 'http://example.com';
        $httpsUrl = 'https://example.com';
        
        $this->assertEquals($httpUrl, $this->sanitizer->sanitizeUrl($httpUrl));
        $this->assertEquals($httpsUrl, $this->sanitizer->sanitizeUrl($httpsUrl));
    }

    public function testSanitizeIntegerReturnsValidInteger(): void
    {
        $input = '123';
        $result = $this->sanitizer->sanitizeInteger($input);
        
        $this->assertEquals(123, $result);
    }

    public function testSanitizeIntegerReturnsZeroForInvalidInput(): void
    {
        $input = 'not-a-number';
        $result = $this->sanitizer->sanitizeInteger($input);
        
        $this->assertEquals(0, $result);
    }

    public function testSanitizeIntegerHandlesNegativeNumbers(): void
    {
        $input = '-123';
        $result = $this->sanitizer->sanitizeInteger($input);
        
        $this->assertEquals(-123, $result);
    }

    public function testSanitizeFloatReturnsValidFloat(): void
    {
        $input = '123.45';
        $result = $this->sanitizer->sanitizeFloat($input);
        
        $this->assertEquals(123.45, $result);
    }

    public function testSanitizeFloatReturnsZeroForInvalidInput(): void
    {
        $input = 'not-a-number';
        $result = $this->sanitizer->sanitizeFloat($input);
        
        $this->assertEquals(0.0, $result);
    }

    public function testSanitizeBooleanReturnsTrueForTruthyValues(): void
    {
        $truthyValues = ['1', 'true', 'yes', 'on', 1, true];
        
        foreach ($truthyValues as $value) {
            $result = $this->sanitizer->sanitizeBoolean($value);
            $this->assertTrue($result, "Failed for value: " . var_export($value, true));
        }
    }

    public function testSanitizeBooleanReturnsFalseForFalsyValues(): void
    {
        $falsyValues = ['0', 'false', 'no', 'off', '', null, 0, false];
        
        foreach ($falsyValues as $value) {
            $result = $this->sanitizer->sanitizeBoolean($value);
            $this->assertFalse($result, "Failed for value: " . var_export($value, true));
        }
    }

    public function testSanitizeArraySanitizesAllElements(): void
    {
        $input = [
            'name' => '<script>alert("xss")</script>John',
            'email' => 'john<script>@example.com',
            'age' => '25abc',
            'active' => '1'
        ];
        
        $rules = [
            'name' => 'string',
            'email' => 'email',
            'age' => 'integer',
            'active' => 'boolean'
        ];
        
        $result = $this->sanitizer->sanitizeArray($input, $rules);
        
        $this->assertEquals('alert("xss")John', $result['name']);
        $this->assertEquals('johnscript@example.com', $result['email']);
        $this->assertEquals(25, $result['age']);
        $this->assertTrue($result['active']);
    }

    public function testSanitizeArrayIgnoresUnknownFields(): void
    {
        $input = [
            'name' => 'John',
            'unknown_field' => 'value'
        ];
        
        $rules = [
            'name' => 'string'
        ];
        
        $result = $this->sanitizer->sanitizeArray($input, $rules);
        
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('unknown_field', $result);
    }

    public function testSanitizeArrayHandlesMissingFields(): void
    {
        $input = [];
        
        $rules = [
            'name' => 'string',
            'email' => 'email'
        ];
        
        $result = $this->sanitizer->sanitizeArray($input, $rules);
        
        $this->assertEquals('', $result['name']);
        $this->assertEquals('', $result['email']);
    }

    public function testRemoveXssAttemptsRemovesScriptTags(): void
    {
        $input = '<script>alert("xss")</script>Hello';
        $expected = 'Hello';
        
        $result = $this->sanitizer->removeXssAttempts($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testRemoveXssAttemptsRemovesEventHandlers(): void
    {
        $input = '<div onclick="alert(\'xss\')">Hello</div>';
        $expected = '<div>Hello</div>';
        
        $result = $this->sanitizer->removeXssAttempts($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testRemoveXssAttemptsRemovesJavascriptUrls(): void
    {
        $input = '<a href="javascript:alert(\'xss\')">Link</a>';
        $expected = '<a href="">Link</a>';
        
        $result = $this->sanitizer->removeXssAttempts($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testEscapeHtmlConvertsSpecialCharacters(): void
    {
        $input = '<script>alert("xss")</script>';
        $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        
        $result = $this->sanitizer->escapeHtml($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testEscapeHtmlHandlesAmpersands(): void
    {
        $input = 'Tom & Jerry';
        $expected = 'Tom &amp; Jerry';
        
        $result = $this->sanitizer->escapeHtml($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testEscapeAttributeEscapesQuotes(): void
    {
        $input = 'value with "quotes" and \'apostrophes\'';
        $expected = 'value with &quot;quotes&quot; and &#039;apostrophes&#039;';
        
        $result = $this->sanitizer->escapeAttribute($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeFilenameRemovesInvalidCharacters(): void
    {
        $input = '../../../etc/passwd';
        $expected = 'passwd';
        
        $result = $this->sanitizer->sanitizeFilename($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeFilenameRemovesSpecialCharacters(): void
    {
        $input = 'file<>:"|?*name.txt';
        $expected = 'filename.txt';
        
        $result = $this->sanitizer->sanitizeFilename($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeFilenameHandlesEmptyResult(): void
    {
        $input = '../../../';
        $expected = 'untitled';
        
        $result = $this->sanitizer->sanitizeFilename($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizePathRemovesTraversalAttempts(): void
    {
        $input = '../../../etc/passwd';
        $expected = 'etc/passwd';
        
        $result = $this->sanitizer->sanitizePath($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizePathNormalizesSlashes(): void
    {
        $input = 'path\\to\\file';
        $expected = 'path/to/file';
        
        $result = $this->sanitizer->sanitizePath($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeSqlRemovesSqlKeywords(): void
    {
        $input = "'; DROP TABLE users; --";
        $expected = "'; DROP TABLE users; --";
        
        $result = $this->sanitizer->sanitizeSql($input);
        
        // Should escape dangerous characters
        $this->assertStringNotContainsString("';", $result);
    }

}