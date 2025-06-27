<?php

declare(strict_types=1);

namespace LengthOfRope\TreeHouse\Validation\Rules;

use LengthOfRope\TreeHouse\Validation\RuleInterface;
use LengthOfRope\TreeHouse\Http\UploadedFile;

/**
 * Image Validation Rule
 *
 * Validates that a field contains a valid uploaded image file.
 * Checks for common image MIME types.
 *
 * @package LengthOfRope\TreeHouse\Validation\Rules
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */
class Image implements RuleInterface
{
    /**
     * Acceptable image MIME types
     *
     * @var array<string>
     */
    protected array $imageMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/svg+xml',
        'image/webp'
    ];

    /**
     * Validate the given value
     *
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (unused)
     * @param array $data All validation data for context (unused)
     * @return bool True if validation passes
     */
    public function passes(mixed $value, array $parameters = [], array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow empty values
        }

        if (!($value instanceof UploadedFile)) {
            return false;
        }

        if (!$value->isValid()) {
            return false;
        }

        $mimeType = $value->getMimeType();
        return in_array($mimeType, $this->imageMimeTypes, true);
    }

    /**
     * Get the validation error message
     *
     * @param string $field Field name being validated
     * @param array $parameters Rule parameters (unused)
     * @return string Error message
     */
    public function message(string $field, array $parameters = []): string
    {
        return "The {$field} field must be an image.";
    }
}
