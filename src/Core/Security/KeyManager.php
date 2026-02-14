<?php

declare(strict_types=1);

namespace AtomFramework\Core\Security;

/**
 * Manages the master encryption key with key-ID tracking and purpose-specific subkey derivation.
 *
 * The master key is stored at /etc/atom/encryption.key (outside web root).
 * Subkeys are derived via HKDF for separate purposes:
 *   - "file-encryption"  → Layer 1 (digital objects on disk)
 *   - "field-encryption"  → Layer 2 (database column encryption)
 *   - "hmac-index"        → Blind index generation
 *
 * Key file format (V2):
 *   Line 1: hex-encoded 32-byte master key (64 hex chars)
 *   Line 2: key_id (uint32, decimal string)
 *
 * V1 format (backward-compatible): just the hex key, key_id defaults to 1.
 */
class KeyManager
{
    private const KEY_PATH = '/etc/atom/encryption.key';
    private const KEY_LENGTH = 32; // 256 bits

    // HKDF context labels for purpose-specific subkeys
    public const PURPOSE_FILE = 'file-encryption';
    public const PURPOSE_FIELD = 'field-encryption';
    public const PURPOSE_HMAC = 'hmac-index';

    /** @var string|null Cached raw master key */
    private static ?string $cachedKey = null;

    /** @var int|null Cached key ID */
    private static ?int $cachedKeyId = null;

    /** @var array<string, string> Cached derived subkeys by purpose */
    private static array $subkeyCache = [];

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

        $lines = array_map('trim', explode("\n", trim($raw)));
        $keyLine = $lines[0];

        // Parse key_id from second line (V2 format), default to 1
        self::$cachedKeyId = isset($lines[1]) && ctype_digit($lines[1]) ? (int) $lines[1] : 1;

        // Key may be hex-encoded (64 hex chars = 32 bytes) or raw binary
        if (strlen($keyLine) === self::KEY_LENGTH * 2 && ctype_xdigit($keyLine)) {
            $key = hex2bin($keyLine);
        } elseif (strlen($keyLine) === self::KEY_LENGTH) {
            $key = $keyLine;
        } else {
            throw new \RuntimeException(
                "Invalid encryption key length. Expected 32 bytes (or 64 hex chars), got " . strlen($keyLine) . ' bytes.'
            );
        }

        if (!self::validateKey($key)) {
            throw new \RuntimeException('Encryption key validation failed.');
        }

        self::$cachedKey = $key;

        return $key;
    }

    /**
     * Get the current key ID.
     */
    public static function getKeyId(): int
    {
        if (self::$cachedKeyId === null) {
            self::loadKey();
        }

        return self::$cachedKeyId ?? 1;
    }

    /**
     * Derive a purpose-specific subkey via HKDF.
     *
     * Uses HKDF-SHA256 to derive a 32-byte subkey from the master key,
     * scoped to a specific purpose. This means file encryption and field
     * encryption use different effective keys, limiting blast radius.
     *
     * @param string $purpose One of PURPOSE_FILE, PURPOSE_FIELD, PURPOSE_HMAC
     */
    public static function deriveKey(string $purpose): string
    {
        if (isset(self::$subkeyCache[$purpose])) {
            return self::$subkeyCache[$purpose];
        }

        $masterKey = self::loadKey();

        // HKDF-SHA256: extract + expand
        // PHP 8.1+ has hash_hkdf(), but we support 8.0 as well
        if (function_exists('hash_hkdf')) {
            $derived = hash_hkdf('sha256', $masterKey, self::KEY_LENGTH, $purpose, '');
        } else {
            // Manual HKDF
            $prk = hash_hmac('sha256', $masterKey, '', true); // extract with empty salt
            $derived = hash_hmac('sha256', $purpose . "\x01", $prk, true); // expand
        }

        if (strlen($derived) !== self::KEY_LENGTH) {
            $derived = substr($derived, 0, self::KEY_LENGTH);
        }

        self::$subkeyCache[$purpose] = $derived;

        return $derived;
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
     * Write a key (with key_id) to the key file with secure permissions.
     *
     * @param string $key   Raw 32-byte key
     * @param int    $keyId Key identifier for rotation tracking
     *
     * @throws \RuntimeException if directory creation or write fails
     */
    public static function saveKey(string $key, int $keyId = 1): void
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

        // V2 format: hex key on line 1, key_id on line 2
        $content = bin2hex($key) . "\n" . $keyId;

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Cannot write encryption key to {$path}");
        }

        // Restrict permissions: owner read-only
        chmod($path, 0600);

        // Clear all caches
        self::clearCache();
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
     * Check if libsodium is available.
     */
    public static function hasSodium(): bool
    {
        return extension_loaded('sodium')
            && function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push');
    }

    /**
     * Clear all cached keys and derived subkeys.
     */
    public static function clearCache(): void
    {
        // Securely wipe cached key material
        if (self::$cachedKey !== null) {
            sodium_memzero(self::$cachedKey);
        }
        foreach (self::$subkeyCache as &$sk) {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($sk);
            }
        }
        unset($sk);

        self::$cachedKey = null;
        self::$cachedKeyId = null;
        self::$subkeyCache = [];
    }
}
