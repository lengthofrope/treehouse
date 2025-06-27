<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\Rules\Confirmed;
use LengthOfRope\TreeHouse\Validation\Rules\FileRule;
use LengthOfRope\TreeHouse\Validation\Rules\Image;
use LengthOfRope\TreeHouse\Validation\Rules\Mimes;
use LengthOfRope\TreeHouse\Validation\Rules\Size;
use LengthOfRope\TreeHouse\Http\UploadedFile;
use Tests\TestCase;

/**
 * Missing Validation Rules Tests
 *
 * Tests for validation rules that were not covered in the main ValidationRulesTest
 *
 * @package Tests\Unit\Validation\Rules
 */
class MissingValidationRulesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/treehouse_validation_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        
        parent::tearDown();
    }

    // ==================================================
    // Confirmed Rule Tests
    // ==================================================

    public function testConfirmedRuleWithPasswordConfirmation()
    {
        $rule = new Confirmed();
        
        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ];

        $this->assertTrue($rule->passes('secret123', [], $data));
    }

    public function testConfirmedRuleWithMismatchedConfirmation()
    {
        $rule = new Confirmed();
        
        $data = [
            'password' => 'secret123',
            'password_confirmation' => 'different123'
        ];

        $this->assertFalse($rule->passes('secret123', [], $data));
    }

    public function testConfirmedRuleWithEmailConfirmation()
    {
        $rule = new Confirmed();
        
        $data = [
            'email' => 'user@example.com',
            'email_confirmation' => 'user@example.com'
        ];

        $this->assertTrue($rule->passes('user@example.com', [], $data));
    }

    public function testConfirmedRuleWithMismatchedEmailConfirmation()
    {
        $rule = new Confirmed();
        
        $data = [
            'email' => 'user@example.com',
            'email_confirmation' => 'different@example.com'
        ];

        $this->assertFalse($rule->passes('user@example.com', [], $data));
    }

    public function testConfirmedRuleWithConfirmPasswordPattern()
    {
        $rule = new Confirmed();
        
        $data = [
            'new_password' => 'newsecret123',
            'confirm_password' => 'newsecret123'
        ];

        $this->assertTrue($rule->passes('newsecret123', [], $data));
    }

    public function testConfirmedRuleWithConfirmEmailPattern()
    {
        $rule = new Confirmed();
        
        $data = [
            'user_email' => 'test@example.com',
            'confirm_email' => 'test@example.com'
        ];

        $this->assertTrue($rule->passes('test@example.com', [], $data));
    }

    public function testConfirmedRuleWithNoConfirmationField()
    {
        $rule = new Confirmed();
        
        $data = [
            'password' => 'secret123'
            // No confirmation field
        ];

        $this->assertFalse($rule->passes('secret123', [], $data));
    }

    public function testConfirmedRuleAllowsEmptyValues()
    {
        $rule = new Confirmed();
        
        $data = [
            'password_confirmation' => 'secret123'
        ];

        // Test null
        $this->assertTrue($rule->passes(null, [], $data));
        
        // Test empty string
        $this->assertTrue($rule->passes('', [], $data));
    }

    public function testConfirmedRuleMessage()
    {
        $rule = new Confirmed();
        $message = $rule->message('password');
        
        $this->assertStringContainsString('password', $message);
        $this->assertStringContainsString('confirmation', $message);
        $this->assertStringContainsString('does not match', $message);
    }

    // ==================================================
    // FileRule Tests
    // ==================================================

    public function testFileRuleWithValidUploadedFile()
    {
        $rule = new FileRule();
        
        // Create a test file
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'test content');
        
        // Create UploadedFile instance
        $uploadedFile = new UploadedFile(
            $testFile,
            'test.txt',
            'text/plain',
            UPLOAD_ERR_OK,
            12
        );
        
        // Mock isValid method by creating a custom UploadedFile
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(true);
        
        $this->assertTrue($rule->passes($mockFile));
    }

    public function testFileRuleWithInvalidUploadedFile()
    {
        $rule = new FileRule();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(false);
        
        $this->assertFalse($rule->passes($mockFile));
    }

    public function testFileRuleWithNonFileValue()
    {
        $rule = new FileRule();
        
        $this->assertFalse($rule->passes('not a file'));
        $this->assertFalse($rule->passes(123));
        $this->assertFalse($rule->passes([]));
        $this->assertFalse($rule->passes(new \stdClass()));
    }

    public function testFileRuleAllowsEmptyValues()
    {
        $rule = new FileRule();
        
        $this->assertTrue($rule->passes(null));
        $this->assertTrue($rule->passes(''));
    }

    public function testFileRuleMessage()
    {
        $rule = new FileRule();
        $message = $rule->message('document');
        
        $this->assertStringContainsString('document', $message);
        $this->assertStringContainsString('valid file', $message);
    }

    // ==================================================
    // Image Rule Tests
    // ==================================================

    public function testImageRuleWithValidImageFile()
    {
        $rule = new Image();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(true);
        $mockFile->method('getMimeType')->willReturn('image/jpeg');
        
        $this->assertTrue($rule->passes($mockFile));
    }

    public function testImageRuleWithDifferentImageTypes()
    {
        $rule = new Image();
        
        $imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/svg+xml', 'image/webp'];
        
        foreach ($imageTypes as $mimeType) {
            $mockFile = $this->createMock(UploadedFile::class);
            $mockFile->method('isValid')->willReturn(true);
            $mockFile->method('getMimeType')->willReturn($mimeType);
            
            $this->assertTrue($rule->passes($mockFile), "Failed for MIME type: {$mimeType}");
        }
    }

    public function testImageRuleWithNonImageFile()
    {
        $rule = new Image();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(true);
        $mockFile->method('getMimeType')->willReturn('text/plain');
        
        $this->assertFalse($rule->passes($mockFile));
    }

    public function testImageRuleWithInvalidFile()
    {
        $rule = new Image();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(false);
        
        $this->assertFalse($rule->passes($mockFile));
    }

    public function testImageRuleWithNonFileValue()
    {
        $rule = new Image();
        
        $this->assertFalse($rule->passes('not a file'));
        $this->assertFalse($rule->passes(123));
        $this->assertFalse($rule->passes([]));
    }

    public function testImageRuleAllowsEmptyValues()
    {
        $rule = new Image();
        
        $this->assertTrue($rule->passes(null));
        $this->assertTrue($rule->passes(''));
    }

    public function testImageRuleMessage()
    {
        $rule = new Image();
        $message = $rule->message('avatar');
        
        $this->assertStringContainsString('avatar', $message);
        $this->assertStringContainsString('image', $message);
    }

    // ==================================================
    // Mimes Rule Tests
    // ==================================================

    public function testMimesRuleWithValidMimeType()
    {
        $rule = new Mimes();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(true);
        $mockFile->method('getMimeType')->willReturn('application/pdf');
        
        $this->assertTrue($rule->passes($mockFile, ['application/pdf', 'text/plain']));
    }

    public function testMimesRuleWithInvalidMimeType()
    {
        $rule = new Mimes();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(true);
        $mockFile->method('getMimeType')->willReturn('application/exe');
        
        $this->assertFalse($rule->passes($mockFile, ['application/pdf', 'text/plain']));
    }

    public function testMimesRuleWithMultipleMimeTypes()
    {
        $rule = new Mimes();
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        foreach ($allowedTypes as $mimeType) {
            $mockFile = $this->createMock(UploadedFile::class);
            $mockFile->method('isValid')->willReturn(true);
            $mockFile->method('getMimeType')->willReturn($mimeType);
            
            $this->assertTrue($rule->passes($mockFile, $allowedTypes), "Failed for MIME type: {$mimeType}");
        }
    }

    public function testMimesRuleWithInvalidFile()
    {
        $rule = new Mimes();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(false);
        
        $this->assertFalse($rule->passes($mockFile, ['application/pdf']));
    }

    public function testMimesRuleWithNonFileValue()
    {
        $rule = new Mimes();
        
        $this->assertFalse($rule->passes('not a file', ['application/pdf']));
        $this->assertFalse($rule->passes(123, ['application/pdf']));
        $this->assertFalse($rule->passes([], ['application/pdf']));
    }

    public function testMimesRuleWithEmptyParameters()
    {
        $rule = new Mimes();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('isValid')->willReturn(true);
        $mockFile->method('getMimeType')->willReturn('application/pdf');
        
        $this->assertFalse($rule->passes($mockFile, []));
    }

    public function testMimesRuleAllowsEmptyValues()
    {
        $rule = new Mimes();
        
        $this->assertTrue($rule->passes(null, ['application/pdf']));
        $this->assertTrue($rule->passes('', ['application/pdf']));
    }

    public function testMimesRuleMessage()
    {
        $rule = new Mimes();
        $message = $rule->message('document', ['application/pdf', 'text/plain']);
        
        $this->assertStringContainsString('document', $message);
        $this->assertStringContainsString('application/pdf', $message);
        $this->assertStringContainsString('text/plain', $message);
    }

    // ==================================================
    // Size Rule Tests
    // ==================================================

    public function testSizeRuleWithFileSize()
    {
        $rule = new Size();
        
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('getSize')->willReturn(1024);
        
        $this->assertTrue($rule->passes($mockFile, ['1024']));
        $this->assertFalse($rule->passes($mockFile, ['512']));
        $this->assertFalse($rule->passes($mockFile, ['2048']));
    }

    public function testSizeRuleWithStringLength()
    {
        $rule = new Size();
        
        $this->assertTrue($rule->passes('hello', ['5']));
        $this->assertFalse($rule->passes('hello', ['3']));
        $this->assertFalse($rule->passes('hello', ['10']));
    }

    public function testSizeRuleWithArrayCount()
    {
        $rule = new Size();
        
        $this->assertTrue($rule->passes(['a', 'b', 'c'], ['3']));
        $this->assertFalse($rule->passes(['a', 'b', 'c'], ['2']));
        $this->assertFalse($rule->passes(['a', 'b', 'c'], ['5']));
    }

    public function testSizeRuleWithEmptyParameters()
    {
        $rule = new Size();
        
        $this->assertFalse($rule->passes('test', []));
        $this->assertFalse($rule->passes(['a', 'b'], []));
    }

    public function testSizeRuleWithInvalidValue()
    {
        $rule = new Size();
        
        $this->assertFalse($rule->passes(123, ['5'])); // Not string, array, or UploadedFile
        $this->assertFalse($rule->passes(new \stdClass(), ['5']));
        $this->assertFalse($rule->passes(true, ['5']));
    }

    public function testSizeRuleAllowsEmptyValues()
    {
        $rule = new Size();
        
        $this->assertTrue($rule->passes(null, ['5']));
        $this->assertTrue($rule->passes('', ['5']));
    }

    public function testSizeRuleWithMultibyteString()
    {
        $rule = new Size();
        
        // Test with multibyte characters
        $multibyteString = 'héllo'; // 5 characters, but may be more bytes
        $this->assertTrue($rule->passes($multibyteString, ['5']));
        
        $emojiString = '🙂🙂🙂'; // 3 emoji characters
        $this->assertTrue($rule->passes($emojiString, ['3']));
    }

    public function testSizeRuleMessage()
    {
        $rule = new Size();
        $message = $rule->message('password', ['8']);
        
        $this->assertStringContainsString('password', $message);
        $this->assertStringContainsString('8', $message);
        $this->assertStringContainsString('exactly', $message);
    }

    public function testSizeRuleMessageWithoutParameters()
    {
        $rule = new Size();
        $message = $rule->message('field', []);
        
        $this->assertStringContainsString('field', $message);
        $this->assertStringContainsString('N/A', $message);
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}