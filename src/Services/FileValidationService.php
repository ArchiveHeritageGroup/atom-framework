<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * File Validation Service.
 *
 * Provides centralized file validation for uploads across the framework:
 * - Extension allowlist checking
 * - MIME type validation via finfo (magic bytes)
 * - File size enforcement
 * - Filename sanitization
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class FileValidationService
{
    /**
     * Default allowed extensions for archival uploads.
     */
    private const DEFAULT_ALLOWED_EXTENSIONS = [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp', 'svg',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
        // Audio
        'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a',
        // Video
        'mp4', 'avi', 'mov', 'mkv', 'webm', 'wmv',
        // Archives
        'zip', 'tar', 'gz', 'tgz',
        // 3D models
        'obj', 'gltf', 'glb', 'stl', 'fbx',
        // Archival formats
        'xml', 'ead', 'json', 'marc', 'mrc',
    ];

    /**
     * MIME types that map to allowed extensions.
     */
    private const MIME_EXTENSION_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/tiff' => ['tif', 'tiff'],
        'image/bmp' => ['bmp'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/rtf' => ['rtf'],
        'text/plain' => ['txt', 'csv', 'xml', 'ead', 'json', 'marc'],
        'text/csv' => ['csv'],
        'text/xml' => ['xml', 'ead'],
        'application/xml' => ['xml', 'ead'],
        'application/json' => ['json'],
        'audio/mpeg' => ['mp3'],
        'audio/wav' => ['wav'],
        'audio/ogg' => ['ogg'],
        'audio/flac' => ['flac'],
        'audio/aac' => ['aac'],
        'audio/mp4' => ['m4a'],
        'video/mp4' => ['mp4'],
        'video/x-msvideo' => ['avi'],
        'video/quicktime' => ['mov'],
        'video/x-matroska' => ['mkv'],
        'video/webm' => ['webm'],
        'video/x-ms-wmv' => ['wmv'],
        'application/zip' => ['zip'],
        'application/x-tar' => ['tar'],
        'application/gzip' => ['gz', 'tgz'],
        'application/octet-stream' => ['obj', 'gltf', 'glb', 'stl', 'fbx', 'mrc', 'bin'],
        'model/gltf-binary' => ['glb'],
        'model/gltf+json' => ['gltf'],
    ];

    /** @var int Default max file size: 100 MB */
    private const DEFAULT_MAX_SIZE = 104857600;

    /**
     * Validate an uploaded file.
     *
     * @param array $file    File array with keys: name, tmp_name, type, size (same as $_FILES entry)
     * @param array $options Optional overrides: allowed_extensions, max_size, validate_mime
     *
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public static function validateUpload(array $file, array $options = []): array
    {
        $errors = [];

        $name = $file['name'] ?? '';
        $tmpName = $file['tmp_name'] ?? '';
        $claimedMime = $file['type'] ?? '';
        $size = (int) ($file['size'] ?? 0);

        $allowedExtensions = $options['allowed_extensions'] ?? self::getAllowedExtensions();
        $maxSize = (int) ($options['max_size'] ?? self::getMaxSize());
        $validateMime = $options['validate_mime'] ?? true;

        // Extension check
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (empty($ext) || !in_array($ext, $allowedExtensions, true)) {
            $errors[] = "File extension '{$ext}' is not allowed.";
        }

        // Size check
        if ($size > $maxSize) {
            $errors[] = sprintf(
                'File size %s exceeds maximum allowed %s.',
                self::formatBytes($size),
                self::formatBytes($maxSize)
            );
        }

        // MIME validation via magic bytes
        if ($validateMime && !empty($tmpName) && file_exists($tmpName)) {
            $mimeResult = self::validateMime($tmpName, $claimedMime);
            if (!$mimeResult['valid']) {
                $errors = array_merge($errors, $mimeResult['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate MIME type using finfo magic bytes.
     *
     * @param string      $filePath    Path to the file on disk
     * @param string|null $claimedMime Optional claimed MIME to cross-check
     *
     * @return array ['valid' => bool, 'detected_mime' => string, 'errors' => string[]]
     */
    public static function validateMime(string $filePath, ?string $claimedMime = null): array
    {
        $errors = [];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($filePath);

        if ($detectedMime === false) {
            return [
                'valid' => false,
                'detected_mime' => '',
                'errors' => ['Unable to detect file MIME type.'],
            ];
        }

        // Check if detected MIME maps to any allowed extension
        $allowedExtensions = self::getAllowedExtensions();
        $mimeAllowed = false;

        if (isset(self::MIME_EXTENSION_MAP[$detectedMime])) {
            foreach (self::MIME_EXTENSION_MAP[$detectedMime] as $ext) {
                if (in_array($ext, $allowedExtensions, true)) {
                    $mimeAllowed = true;
                    break;
                }
            }
        }

        // application/octet-stream is generic — allow if the extension itself is allowed
        if (!$mimeAllowed && $detectedMime === 'application/octet-stream') {
            $mimeAllowed = true;
        }

        if (!$mimeAllowed) {
            $errors[] = "Detected MIME type '{$detectedMime}' is not allowed.";
        }

        // Cross-check: if claimed MIME differs significantly from detected, warn
        if ($claimedMime && $detectedMime !== 'application/octet-stream'
            && $claimedMime !== 'application/octet-stream'
            && $claimedMime !== $detectedMime
        ) {
            // Allow common mismatches (e.g., text/xml vs application/xml)
            $claimedBase = explode('/', $claimedMime)[0] ?? '';
            $detectedBase = explode('/', $detectedMime)[0] ?? '';

            if ($claimedBase !== $detectedBase) {
                $errors[] = sprintf(
                    "MIME mismatch: claimed '%s' but detected '%s'.",
                    $claimedMime,
                    $detectedMime
                );
            }
        }

        return [
            'valid' => empty($errors),
            'detected_mime' => $detectedMime,
            'errors' => $errors,
        ];
    }

    /**
     * Sanitize a filename for safe filesystem storage.
     *
     * Removes path traversal sequences, dangerous characters, and normalizes.
     *
     * @param string $filename Original filename
     *
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Strip any directory components
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Replace path traversal patterns
        $filename = str_replace(['../', '..\\', '..'], '', $filename);

        // Keep only safe characters: alphanumeric, dash, underscore, dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');

        // Collapse multiple underscores/dots
        $filename = preg_replace('/_{2,}/', '_', $filename);
        $filename = preg_replace('/\.{2,}/', '.', $filename);

        // Ensure non-empty
        if (empty($filename)) {
            $filename = 'unnamed_file';
        }

        return $filename;
    }

    /**
     * Get allowed file extensions from settings or defaults.
     *
     * @return string[]
     */
    public static function getAllowedExtensions(): array
    {
        try {
            if (class_exists('\\AtomExtensions\\Services\\AhgSettingsService')) {
                $custom = \AtomExtensions\Services\AhgSettingsService::get('file_allowed_extensions', '');
                if (!empty($custom)) {
                    $extensions = array_map('trim', explode(',', strtolower($custom)));
                    $extensions = array_filter($extensions);
                    if (!empty($extensions)) {
                        return $extensions;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fall through to defaults
        }

        return self::DEFAULT_ALLOWED_EXTENSIONS;
    }

    /**
     * Get max file size from settings or default.
     *
     * @return int Max size in bytes
     */
    public static function getMaxSize(): int
    {
        try {
            if (class_exists('\\AtomExtensions\\Services\\AhgSettingsService')) {
                $maxMb = \AtomExtensions\Services\AhgSettingsService::get('file_max_upload_mb', '');
                if (is_numeric($maxMb) && (int) $maxMb > 0) {
                    return (int) $maxMb * 1048576;
                }
            }
        } catch (\Exception $e) {
            // Fall through to default
        }

        return self::DEFAULT_MAX_SIZE;
    }

    /**
     * Validate base64-encoded content size before decoding.
     *
     * @param string $base64  Base64 string (without data URI prefix)
     * @param int    $maxSize Max decoded size in bytes
     *
     * @return array ['valid' => bool, 'estimated_size' => int, 'errors' => string[]]
     */
    public static function validateBase64Size(string $base64, ?int $maxSize = null): array
    {
        $maxSize = $maxSize ?? self::getMaxSize();

        // Estimate decoded size: base64 encodes 3 bytes as 4 chars
        $paddingCount = substr_count(substr($base64, -2), '=');
        $estimatedSize = (int) (strlen($base64) * 3 / 4) - $paddingCount;

        $errors = [];
        if ($estimatedSize > $maxSize) {
            $errors[] = sprintf(
                'Base64 content estimated at %s exceeds maximum %s.',
                self::formatBytes($estimatedSize),
                self::formatBytes($maxSize)
            );
        }

        return [
            'valid' => empty($errors),
            'estimated_size' => $estimatedSize,
            'errors' => $errors,
        ];
    }

    /**
     * Format bytes to human-readable string.
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
