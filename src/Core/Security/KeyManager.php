<?php

declare(strict_types=1);

namespace AtomFramework\Core\Security;

/**
 * Manages the AES-256 master encryption key.
 *
 * The key is stored on disk at /etc/atom/encryption.key (outside web root).
 * It must be 32 bytes (256 bits) of cryptographically random data.
 */
class KeyManager
{
    private const KEY_PATH = '/etc/atom/encryption.key';
    private const KEY_LENGTH = 32; // 256 bits

    /** @var string|null Cached key */
    private static ?string $cachedKey = null;

    /**
     * Load the master encryption key from disk.
     *
     * @throws \RuntimeException if key file is missing or invalid
     */
    public static function loadKey(): string
    {
        if (self::$cachedKey !== null) {
            return self::$cachedKey;
        }

        $path = self::getKeyPath();

        if (!file_exists($path)) {
            throw new \RuntimeException(
                "Encryption key not found at {$path}. "
                . 'Generate one with: php bin/atom encryption:key --generate'
            );
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read encryption key at {$path}. Check file permissions.");
        }

        // Key file may contain hex-encoded key (64 hex chars = 32 bytes)
        $raw = trim($raw);

        if (strlen($raw) === self::KEY_LENGTH * 2 && ctype_xdigit($raw)) {
            $key = hex2bin($raw);
        } elseif (strlen($raw) === self::KEY_LENGTH) {
            $key = $raw;
        } else {
            throw new \RuntimeException(
                "Invalid encryption key length. Expected 32 bytes (or 64 hex characters), got " . strlen($raw) . ' bytes.'
            );
        }

        if (!self::validateKey($key)) {
            throw new \RuntimeException('Encryption key validation failed.');
        }

        self::$cachedKey = $key;

        return $key;
    }

    /**
     * Generate a new random encryption key.
     *
     * @return string 32 bytes of random data
     */
    public static function generateKey(): string
    {
        return random_bytes(self::KEY_LENGTH);
    }

    /**
     * Write a key to the key file with secure permissions.
     *
     * @param string $key Raw 32-byte key
     *
     * @throws \RuntimeException if directory creation or write fails
     */
    public static function saveKey(string $key): void
    {
        if (!self::validateKey($key)) {
            throw new \InvalidArgumentException('Key must be exactly ' . self::KEY_LENGTH . ' bytes.');
        }

        $path = self::getKeyPath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException("Cannot create directory {$dir}");
            }
        }

        // Write hex-encoded key for human-readability
        $hexKey = bin2hex($key);

        if (file_put_contents($path, $hexKey) === false) {
            throw new \RuntimeException("Cannot write encryption key to {$path}");
        }

        // Restrict permissions: owner read-only
        chmod($path, 0600);

        // Clear cache so next load picks up the new key
        self::$cachedKey = null;
    }

    /**
     * Validate a key is the correct length.
     */
    public static function validateKey(string $key): bool
    {
        return strlen($key) === self::KEY_LENGTH;
    }

    /**
     * Check if the key file exists on disk.
     */
    public static function keyExists(): bool
    {
        return file_exists(self::getKeyPath());
    }

    /**
     * Get the path where the key is stored.
     */
    public static function getKeyPath(): string
    {
        return self::KEY_PATH;
    }

    /**
     * Clear the cached key (useful after key rotation).
     */
    public static function clearCache(): void
    {
        self::$cachedKey = null;
    }
}
