<?php

declare(strict_types=1);

namespace SoulCMS\Http;

/**
 * A validated upload.
 *
 * The client supplied name is never trusted: the extension is checked
 * against an allow list, the real content type is sniffed from the file
 * itself, the size is capped, and the stored name is generated. The
 * original name is only kept as a display label.
 */
final class UploadedFile
{
    private function __construct(
        public readonly string $originalName,
        public readonly string $storedName,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $temporaryPath,
    ) {
    }

    /**
     * @param array<string,mixed> $file the entry of $_FILES
     * @param list<string>        $allowedExtensions
     * @param list<string>        $allowedMimeTypes
     * @param int                 $maxBytes
     */
    public static function fromArray(
        array $file,
        array $allowedExtensions,
        array $allowedMimeTypes,
        int $maxBytes,
    ): ?self {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return null;
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');

        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            return null;
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > $maxBytes) {
            return null;
        }

        $originalName = self::sanitiseName((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        // The real type comes from the content, not from the client.
        $detected = self::detectMimeType($temporaryPath);

        if ($detected === null) {
            return null;
        }

        if ($allowedMimeTypes !== [] && !self::mimeMatches($detected, $extension, $allowedMimeTypes)) {
            return null;
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;

        return new self($originalName, $storedName, $extension, $detected, $size, $temporaryPath);
    }

    /**
     * A name that is safe to store and to display.
     */
    public static function sanitiseName(string $name): string
    {
        // Drop the directories, then anything that is not a name character.
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? 'file';
        $name = preg_replace('/[^\p{L}\p{N}._\- ]+/u', '-', $name) ?? 'file';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return mb_substr($name === '' ? 'file' : $name, 0, 180);
    }

    /**
     * Move the file into its final home.
     */
    public function store(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $target = rtrim($directory, '/') . '/' . $this->storedName;

        // move_uploaded_file() only accepts genuine uploads; for files that
        // were already on disk, rename() is the correct operation.
        if (is_uploaded_file($this->temporaryPath)) {
            return move_uploaded_file($this->temporaryPath, $target);
        }

        return @rename($this->temporaryPath, $target);
    }

    private static function detectMimeType(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $type = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($type) && $type !== '' ? $type : null;
    }

    /**
     * @param list<string> $allowed
     */
    private static function mimeMatches(string $detected, string $extension, array $allowed): bool
    {
        if (in_array($detected, $allowed, true)) {
            return true;
        }

        // A few types are reported inconsistently across platforms.
        $aliases = [
            'image/jpeg' => ['image/jpg'],
            'text/plain' => ['text/csv', 'application/csv'],
            'application/zip' => ['application/x-zip-compressed'],
        ];

        foreach ($aliases[$detected] ?? [] as $alias) {
            if (in_array($alias, $allowed, true)) {
                return true;
            }
        }

        // Fall back to the extension when the file is a text document.
        if (str_starts_with($detected, 'text/') && in_array($extension, ['txt', 'md', 'csv'], true)) {
            return true;
        }

        return false;
    }
}
