<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Http;

use RuntimeException;

/**
 * Uploaded File Handler
 * 
 * Represents an uploaded file and provides methods for handling
 * file uploads securely and efficiently.
 * 
 * @package LengthOfRope\TreeHouse\Http
 * @author  Bas de Kort <bdekort@proton.me>
 * @since 1.0.0
 */
class UploadedFile
{
    /**
     * Temporary file path
     */
    protected string $tmpName;

    /**
     * Original filename
     */
    protected string $name;

    /**
     * MIME type
     */
    protected string $type;

    /**
     * Upload error code
     */
    protected int $error;

    /**
     * File size in bytes
     */
    protected int $size;

    /**
     * Whether the file has been moved
     */
    protected bool $moved = false;

    /**
     * Create a new UploadedFile instance
     * 
     * @param string $tmpName Temporary file path
     * @param string $name Original filename
     * @param string $type MIME type
     * @param int $error Upload error code
     * @param int $size File size in bytes
     */
    public function __construct(
        string $tmpName,
        string $name,
        string $type,
        int $error = UPLOAD_ERR_OK,
        int $size = 0
    ) {
        $this->tmpName = $tmpName;
        $this->name = $name;
        $this->type = $type;
        $this->error = $error;
        $this->size = $size;
    }

    /**
     * Get the original filename
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the client filename (alias for getName)
     * 
     * @return string
     */
    public function getClientOriginalName(): string
    {
        return $this->getName();
    }

    /**
     * Get the file extension
     * 
     * @return string
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Get the client MIME type
     * 
     * @return string
     */
    public function getClientMimeType(): string
    {
        return $this->type;
    }

    /**
     * Get the actual MIME type (detected from file content)
     * 
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mimeType = finfo_file($finfo, $this->tmpName);
        finfo_close($finfo);

        return $mimeType ?: null;
    }

    /**
     * Get the file size in bytes
     * 
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the upload error code
     * 
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Get the error message
     * 
     * @return string
     */
    public function getErrorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error'
        };
    }

    /**
     * Check if the upload was successful
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && 
               is_uploaded_file($this->tmpName) && 
               !$this->moved;
    }

    /**
     * Check if file has been moved
     * 
     * @return bool
     */
    public function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * Get the temporary file path
     * 
     * @return string
     */
    public function getTempName(): string
    {
        return $this->tmpName;
    }

    /**
     * Get file contents
     * 
     * @return string
     * @throws RuntimeException
     */
    public function getContent(): string
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot read invalid file');
        }

        $content = file_get_contents($this->tmpName);
        if ($content === false) {
            throw new RuntimeException('Failed to read file content');
        }

        return $content;
    }

    /**
     * Move the uploaded file to a new location
     * 
     * @param string $targetPath Target file path
     * @return bool
     * @throws RuntimeException
     */
    public function move(string $targetPath): bool
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot move invalid file: ' . $this->getErrorMessage());
        }

        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        // Create directory if it doesn't exist
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new RuntimeException('Failed to create target directory: ' . $targetDir);
            }
        }

        // Move the file
        if (!move_uploaded_file($this->tmpName, $targetPath)) {
            throw new RuntimeException('Failed to move uploaded file to: ' . $targetPath);
        }

        $this->moved = true;
        return true;
    }

    /**
     * Store the file with a generated filename
     * 
     * @param string $directory Target directory
     * @param string|null $filename Custom filename (without extension)
     * @return string The stored filename
     * @throws RuntimeException
     */
    public function store(string $directory, ?string $filename = null): string
    {
        if ($filename === null) {
            $filename = $this->generateFilename();
        }

        $extension = $this->getExtension();
        $fullFilename = $extension ? $filename . '.' . $extension : $filename;
        $targetPath = rtrim($directory, '/') . '/' . $fullFilename;

        $this->move($targetPath);
        return $fullFilename;
    }

    /**
     * Check if file is an image
     * 
     * @return bool
     */
    public function isImage(): bool
    {
        $mimeType = $this->getMimeType();
        return $mimeType !== null && str_starts_with($mimeType, 'image/');
    }

    /**
     * Get image dimensions (if file is an image)
     * 
     * @return array|null [width, height] or null if not an image
     */
    public function getImageDimensions(): ?array
    {
        if (!$this->isValid() || !$this->isImage()) {
            return null;
        }

        $dimensions = getimagesize($this->tmpName);
        return $dimensions ? [$dimensions[0], $dimensions[1]] : null;
    }

    /**
     * Validate file against allowed extensions
     * 
     * @param array $allowedExtensions Array of allowed extensions
     * @return bool
     */
    public function hasAllowedExtension(array $allowedExtensions): bool
    {
        $extension = $this->getExtension();
        return in_array($extension, array_map('strtolower', $allowedExtensions), true);
    }

    /**
     * Validate file against allowed MIME types
     * 
     * @param array $allowedMimeTypes Array of allowed MIME types
     * @return bool
     */
    public function hasAllowedMimeType(array $allowedMimeTypes): bool
    {
        $mimeType = $this->getMimeType();
        return $mimeType !== null && in_array($mimeType, $allowedMimeTypes, true);
    }

    /**
     * Check if file size is within limit
     * 
     * @param int $maxSize Maximum size in bytes
     * @return bool
     */
    public function isWithinSizeLimit(int $maxSize): bool
    {
        return $this->size <= $maxSize;
    }

    /**
     * Generate a unique filename
     * 
     * @return string
     */
    protected function generateFilename(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get file hash
     * 
     * @param string $algorithm Hash algorithm (md5, sha1, sha256, etc.)
     * @return string|null
     */
    public function getHash(string $algorithm = 'sha256'): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        return hash_file($algorithm, $this->tmpName) ?: null;
    }

    /**
     * Convert to string representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}