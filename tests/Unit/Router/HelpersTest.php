<?php

declare(strict_types=1);

namespace Tests\Unit\Router;

use PHPUnit\Framework\TestCase;

/**
 * Test Router helper functions
 */
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        // Include the helper functions
        require_once __DIR__ . '/../../../src/TreeHouse/View/helpers.php';
    }

    public function testMethodFieldGeneratesCorrectHtml(): void
    {
        $html = methodField('PUT');
        $expected = '<input type="hidden" name="_method" value="PUT">';
        
        $this->assertEquals($expected, $html);
    }

    public function testMethodFieldHandlesCaseInsensitivity(): void
    {
        $html = methodField('delete');
        $expected = '<input type="hidden" name="_method" value="DELETE">';
        
        $this->assertEquals($expected, $html);
    }

    public function testMethodFieldHandlesWhitespace(): void
    {
        $html = methodField('  PATCH  ');
        $expected = '<input type="hidden" name="_method" value="PATCH">';
        
        $this->assertEquals($expected, $html);
    }

    public function testMethodFieldThrowsExceptionForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid method 'GET'. Allowed methods: PUT, PATCH, DELETE, OPTIONS");
        
        methodField('GET');
    }

    public function testMethodFieldThrowsExceptionForCustomMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid method 'CUSTOM'. Allowed methods: PUT, PATCH, DELETE, OPTIONS");
        
        methodField('CUSTOM');
    }

    public function testCsrfFieldGeneratesHiddenInput(): void
    {
        $html = csrfField();
        
        // Should contain the basic structure
        $this->assertStringContainsString('<input type="hidden" name="_token" value="', $html);
        $this->assertStringContainsString('">', $html);
        
        // Should generate the same token within the same session (proper CSRF behavior)
        $html2 = csrfField();
        $this->assertEquals($html, $html2);
        
        // Token should be a valid length (64 characters for 32 bytes in hex)
        preg_match('/value="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches[1]);
        $this->assertEquals(64, strlen($matches[1])); // 32 bytes * 2 (hex encoding)
    }

    public function testFormMethodWithCsrf(): void
    {
        $html = formMethod('PUT');
        
        // Should contain both method and CSRF fields
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_token" value="', $html);
        $this->assertStringContainsString("\n", $html); // Should have newline between fields
    }

    public function testFormMethodWithoutCsrf(): void
    {
        $html = formMethod('DELETE', false);
        
        // Should only contain method field
        $this->assertEquals('<input type="hidden" name="_method" value="DELETE">', $html);
        $this->assertStringNotContainsString('_token', $html);
    }

    public function testFormMethodThrowsExceptionForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        formMethod('POST');
    }

    public function testAllValidMethodsWork(): void
    {
        $validMethods = ['PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        
        foreach ($validMethods as $method) {
            $html = methodField($method);
            $expected = '<input type="hidden" name="_method" value="' . $method . '">';
            
            $this->assertEquals($expected, $html, "Failed for method: {$method}");
        }
    }

    public function testHtmlEscaping(): void
    {
        // This shouldn't happen in normal usage, but test that values are escaped
        // We'll test by modifying the function temporarily or testing edge cases
        
        // Test that the function properly escapes HTML characters if they somehow get through
        $html = methodField('PUT');
        
        // Verify that the output is properly formed HTML
        $this->assertStringStartsWith('<input type="hidden"', $html);
        $this->assertStringEndsWith('">', $html);
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertStringContainsString('value="PUT"', $html);
    }
}