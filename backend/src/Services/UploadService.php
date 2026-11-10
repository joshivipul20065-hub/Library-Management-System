<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use finfo;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/** Validates image uploads by content (not client-supplied type) and stores them under random names. */
final class UploadService
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly string $directory, private readonly string $publicPrefix = '/uploads/covers')
    {
    }

    public function storeImage(?UploadedFileInterface $file, string $field = 'cover'): string
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('An image file is required', [$field => 'Upload a JPEG, PNG or WebP image']);
        }
        if (($file->getSize() ?? PHP_INT_MAX) > self::MAX_BYTES) {
            throw new ValidationException('Image is too large', [$field => 'Maximum size is 2 MB']);
        }

        $contents = (string) $file->getStream();
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (!is_string($mime) || !isset(self::TYPES[$mime]) || @getimagesizefromstring($contents) === false) {
            throw new ValidationException('Invalid image', [$field => 'Only JPEG, PNG or WebP images are allowed']);
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Upload directory is not writable');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        if (file_put_contents($this->directory . '/' . $name, $contents) === false) {
            throw new RuntimeException('Could not store uploaded file');
        }

        return $this->publicPrefix . '/' . $name;
    }

    /** Deletes a file previously returned by storeImage(); external URLs (e.g. Open Library) are ignored. */
    public function delete(?string $publicUrl): void
    {
        $prefix = $this->publicPrefix . '/';
        if ($publicUrl === null || !str_starts_with($publicUrl, $prefix)) {
            return;
        }
        $name = substr($publicUrl, strlen($prefix));
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) === 1 && is_file($this->directory . '/' . $name)) {
            unlink($this->directory . '/' . $name);
        }
    }
}
