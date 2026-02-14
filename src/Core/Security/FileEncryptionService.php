<?php

declare(strict_types=1);

namespace AtomFramework\Core\Security;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Layer 1: Digital object file encryption.
 *
 * Encrypts uploaded files (masters + derivatives) in-place on disk.
 * Uses EncryptionService for the actual AES-256-GCM operations.
 */
class FileEncryptionService
{
    /**
     * Resolve the web directory (sf_web_dir equivalent).
     * Works in both Symfony web context and CLI context.
     */
    private static function getWebDir(): string
    {
        if (class_exists('\\sfConfig') && \sfConfig::get('sf_web_dir')) {
            return \sfConfig::get('sf_web_dir');
        }

        // CLI fallback: ATOM_ROOT is the web dir
        if (defined('ATOM_ROOT')) {
            return ATOM_ROOT;
        }

        return dirname(dirname(dirname(dirname(__DIR__))));
    }

    /**
     * Check if file encryption is enabled in settings.
     */
    public static function isEnabled(): bool
    {
        try {
            $row = DB::table('ahg_settings')
                ->where('setting_key', 'encryption_enabled')
                ->value('setting_value');

            return $row === 'true' || $row === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if derivative encryption is enabled.
     */
    public static function encryptDerivativesEnabled(): bool
    {
        try {
            $row = DB::table('ahg_settings')
                ->where('setting_key', 'encryption_encrypt_derivatives')
                ->value('setting_value');

            // Default to true if not set
            return $row === null || $row === 'true' || $row === '1';
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Encrypt a file in-place after upload.
     *
     * @param string $filePath Absolute path to the plaintext file
     *
     * @return bool true on success
     */
    public static function encryptUpload(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            error_log("FileEncryptionService: File not found: {$filePath}");

            return false;
        }

        // Skip if already encrypted
        if (EncryptionService::isEncryptedFile($filePath)) {
            return true;
        }

        try {
            $tempPath = $filePath . '.enc';
            EncryptionService::encryptFile($filePath, $tempPath);

            // Atomic replace: rename encrypted file over original
            if (!rename($tempPath, $filePath)) {
                @unlink($tempPath);
                throw new \RuntimeException("Cannot replace original file with encrypted version.");
            }

            self::logAudit('encrypt', 'file', $filePath);

            return true;
        } catch (\Exception $e) {
            error_log("FileEncryptionService: Encrypt failed for {$filePath}: " . $e->getMessage());
            // Clean up temp file if it exists
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return false;
        }
    }

    /**
     * Decrypt a file to a temporary location for streaming/viewing.
     *
     * @param string $filePath Absolute path to encrypted file
     *
     * @return string Path to temporary decrypted file (caller must unlink)
     */
    public static function decryptToTemp(string $filePath): string
    {
        $tempDir = sys_get_temp_dir() . '/ahg_decrypt';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        $tempPath = $tempDir . '/' . bin2hex(random_bytes(8)) . '_' . basename($filePath);

        EncryptionService::decryptFile($filePath, $tempPath);

        // Register shutdown function to clean up temp file after request
        register_shutdown_function(function () use ($tempPath) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        });

        return $tempPath;
    }

    /**
     * Get a streaming generator for an encrypted file.
     *
     * @param string $filePath Absolute path to encrypted file
     *
     * @return \Generator yields plaintext chunks
     */
    public static function decryptForStream(string $filePath): \Generator
    {
        return EncryptionService::decryptFileStream($filePath);
    }

    /**
     * Encrypt a specific digital object's master file.
     *
     * @param int $digitalObjectId The digital object ID
     *
     * @return bool true on success
     */
    public static function encryptDigitalObject(int $digitalObjectId): bool
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        if (!$do) {
            return false;
        }

        $webDir = self::getWebDir();
        $path = $do->path;
        $name = $do->name;

        if (!$path || !$name) {
            return false;
        }

        if (str_starts_with($path, '/uploads/')) {
            $fullPath = $webDir . $path . '/' . $name;
        } else {
            $fullPath = $webDir . '/uploads/' . trim($path, '/') . '/' . $name;
        }

        if (!file_exists($fullPath)) {
            error_log("FileEncryptionService: DO {$digitalObjectId} file not found: {$fullPath}");

            return false;
        }

        return self::encryptUpload($fullPath);
    }

    /**
     * Encrypt all derivatives (thumbnails, reference images) for a parent digital object.
     *
     * @param int $parentId The parent digital object ID
     *
     * @return int Number of derivatives encrypted
     */
    public static function encryptDerivatives(int $parentId): int
    {
        if (!self::encryptDerivativesEnabled()) {
            return 0;
        }

        $derivatives = DB::table('digital_object')
            ->where('parent_id', $parentId)
            ->get(['id', 'path', 'name']);

        $webDir = self::getWebDir();
        $count = 0;

        foreach ($derivatives as $do) {
            if (!$do->path || !$do->name) {
                continue;
            }

            if (str_starts_with($do->path, '/uploads/')) {
                $fullPath = $webDir . $do->path . '/' . $do->name;
            } else {
                $fullPath = $webDir . '/uploads/' . trim($do->path, '/') . '/' . $do->name;
            }

            if (file_exists($fullPath) && self::encryptUpload($fullPath)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Batch-encrypt existing unencrypted digital objects on disk.
     *
     * @param int           $limit    Max files to process
     * @param callable|null $progress Callback: function(int $current, int $total, string $path)
     *
     * @return array{encrypted: int, skipped: int, failed: int, errors: string[]}
     */
    public static function encryptExisting(int $limit = 100, ?callable $progress = null): array
    {
        $result = ['encrypted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $objects = DB::table('digital_object')
            ->whereNotNull('path')
            ->whereNotNull('name')
            ->limit($limit)
            ->get(['id', 'path', 'name', 'parent_id']);

        $webDir = self::getWebDir();
        $total = count($objects);
        $current = 0;

        foreach ($objects as $do) {
            $current++;

            if (str_starts_with($do->path, '/uploads/')) {
                $fullPath = $webDir . $do->path . '/' . $do->name;
            } else {
                $fullPath = $webDir . '/uploads/' . trim($do->path, '/') . '/' . $do->name;
            }

            if (!file_exists($fullPath)) {
                $result['skipped']++;

                continue;
            }

            if (EncryptionService::isEncryptedFile($fullPath)) {
                $result['skipped']++;

                continue;
            }

            if ($progress) {
                $progress($current, $total, $fullPath);
            }

            if (self::encryptUpload($fullPath)) {
                $result['encrypted']++;
            } else {
                $result['failed']++;
                $result['errors'][] = "Failed: {$fullPath}";
            }
        }

        return $result;
    }

    /**
     * Log an encryption operation to the audit table.
     */
    private static function logAudit(string $action, string $targetType, ?string $targetId = null): void
    {
        try {
            $userId = null;
            if (class_exists('\\sfContext') && \sfContext::hasInstance()) {
                $user = \sfContext::getInstance()->getUser();
                if ($user && method_exists($user, 'getUserID')) {
                    $userId = $user->getUserID();
                }
            }

            DB::table('ahg_encryption_audit')->insert([
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'user_id' => $userId,
                'status' => 'success',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Audit logging should never break encryption operations
            error_log("Encryption audit log error: " . $e->getMessage());
        }
    }
}
