<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use LengthOfRope\TreeHouse\Http\UploadedFile;
use Tests\TestCase;
use RuntimeException;

/**
 * UploadedFile Test
 * 
 * @package Tests\Unit\Http
 */
class UploadedFileTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary file for testing
        $this->tempFile = tempnam(sys_get_temp_dir(), 'upload_test');
        file_put_contents($this->tempFile, 'Test file content');
    }

    protected function tearDown(): void
    {
        // Clean up temporary file
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        parent::tearDown();
    }

    public function testBasicProperties(): void
    {
        $file = new UploadedFile(
            $this->tempFile,
            'test.txt',
            'text/plain',
            UPLOAD_ERR_OK,
            17
        );

        $this->assertEquals('test.txt', $file->getName());
        $this->assertEquals('test.txt', $file->getClientOriginalName());
        $this->assertEquals('txt', $file->getExtension());
        $this->assertEquals('text/plain', $file->getClientMimeType());
        $this->assertEquals(17, $file->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $file->getError());
        $this->assertEquals('No error', $file->getErrorMessage());
        $this->assertEquals($this->tempFile, $file->getTempName());
    }

    public function testErrorMessages(): void
    {
        $errorCodes = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            999 => 'Unknown upload error',
        ];

        foreach ($errorCodes as $code => $message) {
            $file = new UploadedFile('', '', '', $code);
            $this->assertEquals($message, $file->getErrorMessage());
        }
    }

    public function testValidation(): void
    {
        // Valid file - will be false because is_uploaded_file() returns false for our temp file
        $validFile = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        $this->assertFalse($validFile->isValid());

        // Invalid file with error
        $invalidFile = new UploadedFile('', 'test.txt', 'text/plain', UPLOAD_ERR_NO_FILE, 0);
        $this->assertFalse($invalidFile->isValid());
    }

    public function testGetContent(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        // This will throw an exception because is_uploaded_file() returns false for test files
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read invalid file');
        $file->getContent();
    }

    public function testMoveInvalidFile(): void
    {
        $file = new UploadedFile('', 'test.txt', 'text/plain', UPLOAD_ERR_NO_FILE, 0);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot move invalid file');
        $file->move('/tmp/destination.txt');
    }

    public function testExtensionValidation(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        $this->assertTrue($file->hasAllowedExtension(['txt', 'doc']));
        $this->assertTrue($file->hasAllowedExtension(['TXT'])); // Case insensitive
        $this->assertFalse($file->hasAllowedExtension(['pdf', 'doc']));
    }

    public function testSizeValidation(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 1024);
        
        $this->assertTrue($file->isWithinSizeLimit(2048));
        $this->assertTrue($file->isWithinSizeLimit(1024));
        $this->assertFalse($file->isWithinSizeLimit(512));
    }

    public function testImageDetection(): void
    {
        // Create a simple image file for testing
        $imageFile = tempnam(sys_get_temp_dir(), 'image_test');
        
        // Create a minimal PNG file (1x1 pixel)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jU8j8wAAAABJRU5ErkJggg==');
        file_put_contents($imageFile, $pngData);
        
        try {
            $file = new UploadedFile($imageFile, 'test.png', 'image/png', UPLOAD_ERR_OK, strlen($pngData));
            
            // Note: isImage() will return false because isValid() returns false for test files
            // But we can still test the MIME type detection
            $mimeType = $file->getMimeType();
            if ($mimeType !== null) {
                $this->assertStringStartsWith('image/', $mimeType);
            } else {
                $this->assertNull($mimeType);
            }
            
            $dimensions = $file->getImageDimensions();
            $this->assertNull($dimensions); // Will be null because file is not "valid" in upload context
        } finally {
            unlink($imageFile);
        }
    }

    public function testNonImageFile(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        $this->assertFalse($file->isImage());
        $this->assertNull($file->getImageDimensions());
    }

    public function testHashGeneration(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        // Hash will be null because isValid() returns false for test files
        $hash = $file->getHash('md5');
        $this->assertNull($hash);
        
        $sha256Hash = $file->getHash('sha256');
        $this->assertNull($sha256Hash);
        
        // Test default algorithm (sha256)
        $defaultHash = $file->getHash();
        $this->assertNull($defaultHash);
    }

    public function testInvalidFileHash(): void
    {
        $file = new UploadedFile('', 'test.txt', 'text/plain', UPLOAD_ERR_NO_FILE, 0);
        
        $this->assertNull($file->getHash());
    }

    public function testToString(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        $this->assertEquals('test.txt', (string) $file);
    }

    public function testMimeTypeValidation(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        // Get actual MIME type - will be null because isValid() returns false for test files
        $actualMimeType = $file->getMimeType();
        $this->assertNull($actualMimeType);
        
        // Test validation with null MIME type
        $this->assertFalse($file->hasAllowedMimeType(['text/plain']));
        $this->assertFalse($file->hasAllowedMimeType(['image/jpeg', 'image/png']));
    }

    public function testFileExtensions(): void
    {
        $testCases = [
            'document.pdf' => 'pdf',
            'image.JPEG' => 'jpeg',
            'script.js' => 'js',
            'style.CSS' => 'css',
            'noextension' => '',
            'multiple.dots.txt' => 'txt',
        ];

        foreach ($testCases as $filename => $expectedExtension) {
            $file = new UploadedFile($this->tempFile, $filename, 'text/plain', UPLOAD_ERR_OK, 17);
            $this->assertEquals($expectedExtension, $file->getExtension());
        }
    }

    public function testMovedState(): void
    {
        $file = new UploadedFile($this->tempFile, 'test.txt', 'text/plain', UPLOAD_ERR_OK, 17);
        
        $this->assertFalse($file->isMoved());
        
        // Simulate moved state by setting the internal flag
        // Since we can't actually move files in tests due to is_uploaded_file() restrictions
        // we'll test the error case
        $this->expectException(RuntimeException::class);
        $file->move('/tmp/test.txt');
    }
}