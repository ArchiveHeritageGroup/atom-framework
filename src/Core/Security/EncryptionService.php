<?php

declare(strict_types=1);

namespace AtomFramework\Core\Security;

/**
 * Core encryption service with dual V1/V2 algorithm support.
 *
 * V1 (legacy): AES-256-GCM via OpenSSL — whole-file, raw master key
 * V2 (current): XChaCha20-Poly1305 via libsodium — chunked streaming, HKDF-derived subkeys
 *
 * String encryption:
 *   V1: [IV(12)] + [TAG(16)] + [ciphertext]
 *   V2: [AHG2(4)] + [KEY_ID(4)] + [NONCE(24)] + [ciphertext+tag]
 *
 * File encryption:
 *   V1: [AHG-ENC-V1(10)] + [IV(12)] + [TAG(16)] + [ciphertext]
 *   V2: [AHG-ENC-V2(10)] + [KEY_ID(4)] + [CHUNK_SIZE(4)] + [STREAM_HEADER(24)] + [chunks...]
 *       Each chunk: plaintext_chunk_size + 17 bytes (ABYTES overhead)
 *       Last chunk tagged with SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
 */
class EncryptionService
{
    // ── V1 constants (backward compatibility) ───────────────────────────────
    private const V1_ALGORITHM = 'aes-256-gcm';
    private const V1_IV_LENGTH = 12;
    private const V1_TAG_LENGTH = 16;

    // ── V2 constants ────────────────────────────────────────────────────────
    private const V2_CHUNK_SIZE = 65536; // 64 KB plaintext chunks for streaming

    // ── File headers ────────────────────────────────────────────────────────
    private const FILE_HEADER_V1 = 'AHG-ENC-V1';
    private const FILE_HEADER_V2 = 'AHG-ENC-V2';
    private const HEADER_LENGTH = 10;

    // ── String format markers ───────────────────────────────────────────────
    private const STRING_V2_MARKER = 'AHG2'; // 4 bytes, distinguishes V2 from V1 strings

    // ── Streaming output chunk size ─────────────────────────────────────────
    private const OUTPUT_CHUNK_SIZE = 8192;

    // =========================================================================
    //  PUBLIC API — String Encryption
    // =========================================================================

    /**
     * Encrypt a string.
     *
     * Uses XChaCha20-Poly1305 (V2) when sodium is available, falls back to AES-256-GCM (V1).
     *
     * @param string      $plaintext Data to encrypt
     * @param string|null $key       32-byte key (defaults to HKDF-derived field subkey)
     *
     * @return string Binary ciphertext with version-specific format
     */
    public static function encrypt(string $plaintext, ?string $key = null): string
    {
        if (KeyManager::hasSodium()) {
            return self::encryptV2String($plaintext, $key);
        }

        return self::encryptV1String($plaintext, $key);
    }

    /**
     * Decrypt a string.
     *
     * Auto-detects V1 vs V2 format and uses the appropriate algorithm.
     *
     * @param string      $ciphertext Binary ciphertext (V1 or V2 format)
     * @param string|null $key        32-byte key (auto-selects appropriate key if null)
     *
     * @return string Decrypted plaintext
     */
    public static function decrypt(string $ciphertext, ?string $key = null): string
    {
        // V2 strings start with 'AHG2' marker
        if (strlen($ciphertext) >= 4 && substr($ciphertext, 0, 4) === self::STRING_V2_MARKER) {
            return self::decryptV2String($ciphertext, $key);
        }

        // V1 fallback: raw IV + TAG + ciphertext
        return self::decryptV1String($ciphertext, $key);
    }

    // =========================================================================
    //  PUBLIC API — File Encryption
    // =========================================================================

    /**
     * Encrypt a file.
     *
     * V2 (sodium): Chunked streaming via secretstream — never loads full file into memory.
     * V1 (OpenSSL): Whole-file encryption as fallback.
     *
     * @param string      $inputPath  Source file (plaintext)
     * @param string      $outputPath Destination file (encrypted)
     * @param string|null $key        32-byte key (defaults to HKDF-derived file subkey)
     */
    public static function encryptFile(string $inputPath, string $outputPath, ?string $key = null): void
    {
        if (!file_exists($inputPath)) {
            throw new \RuntimeException("Input file not found: {$inputPath}");
        }

        if (KeyManager::hasSodium()) {
            self::encryptFileV2($inputPath, $outputPath, $key);
        } else {
            self::encryptFileV1($inputPath, $outputPath, $key);
        }
    }

    /**
     * Decrypt a file to another file.
     *
     * Auto-detects V1 vs V2 header and dispatches to the correct decryption path.
     *
     * @param string      $encryptedPath Source file (encrypted)
     * @param string      $outputPath    Destination file (plaintext)
     * @param string|null $key           32-byte key (auto-selects if null)
     */
    public static function decryptFile(string $encryptedPath, string $outputPath, ?string $key = null): void
    {
        if (!file_exists($encryptedPath)) {
            throw new \RuntimeException("Encrypted file not found: {$encryptedPath}");
        }

        $version = self::detectFileVersion($encryptedPath);

        if ($version === 2) {
            self::decryptFileV2($encryptedPath, $outputPath, $key);
        } else {
            self::decryptFileV1($encryptedPath, $outputPath, $key);
        }
    }

    /**
     * Streaming decryption via Generator.
     *
     * V2: True streaming — reads and decrypts one chunk at a time, constant memory.
     * V1: Loads full file, decrypts, then yields in chunks (legacy behavior).
     *
     * @param string      $encryptedPath Path to encrypted file
     * @param string|null $key           32-byte key (auto-selects if null)
     *
     * @return \Generator yields plaintext string chunks
     */
    public static function decryptFileStream(string $encryptedPath, ?string $key = null): \Generator
    {
        if (!file_exists($encryptedPath)) {
            throw new \RuntimeException("Encrypted file not found: {$encryptedPath}");
        }

        $version = self::detectFileVersion($encryptedPath);

        if ($version === 2) {
            yield from self::decryptFileStreamV2($encryptedPath, $key);
        } else {
            yield from self::decryptFileStreamV1($encryptedPath, $key);
        }
    }

    /**
     * Check if a file is encrypted (V1 or V2).
     */
    public static function isEncryptedFile(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) < self::HEADER_LENGTH) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, self::HEADER_LENGTH);
        fclose($handle);

        return $header === self::FILE_HEADER_V1 || $header === self::FILE_HEADER_V2;
    }

    /**
     * Get the raw master encryption key.
     */
    public static function getKey(): string
    {
        return KeyManager::loadKey();
    }

    /**
     * Get the current file header version string.
     */
    public static function getFileHeader(): string
    {
        return KeyManager::hasSodium() ? self::FILE_HEADER_V2 : self::FILE_HEADER_V1;
    }

    /**
     * Detect the encryption version of a file by reading its header.
     *
     * @return int 1 for V1, 2 for V2, 0 for unknown
     */
    public static function detectFileVersion(string $filePath): int
    {
        if (!file_exists($filePath) || filesize($filePath) < self::HEADER_LENGTH) {
            return 0;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return 0;
        }

        $header = fread($handle, self::HEADER_LENGTH);
        fclose($handle);

        if ($header === self::FILE_HEADER_V2) {
            return 2;
        }
        if ($header === self::FILE_HEADER_V1) {
            return 1;
        }

        return 0;
    }

    // =========================================================================
    //  V2 String: XChaCha20-Poly1305
    // =========================================================================

    /**
     * Encrypt a string with XChaCha20-Poly1305 (V2).
     *
     * Format: [AHG2(4)] + [KEY_ID(4 LE)] + [NONCE(24)] + [ciphertext+tag]
     */
    private static function encryptV2String(string $plaintext, ?string $key = null): string
    {
        $key = $key ?? KeyManager::deriveKey(KeyManager::PURPOSE_FIELD);
        $keyId = KeyManager::getKeyId();
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES); // 24 bytes

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::STRING_V2_MARKER . pack('V', $keyId), // AAD: marker + key_id
            $nonce,
            $key
        );

        return self::STRING_V2_MARKER
            . pack('V', $keyId)
            . $nonce
            . $ciphertext;
    }

    /**
     * Decrypt a V2 string (XChaCha20-Poly1305).
     */
    private static function decryptV2String(string $data, ?string $key = null): string
    {
        // Parse header: [AHG2(4)] + [KEY_ID(4)] + [NONCE(24)] + [ciphertext+tag]
        // marker(4) + keyId(4) + nonce(24) + tag(16) = 48 minimum (empty plaintext)
        $minLength = 4 + 4 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
            + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

        if (strlen($data) < $minLength) {
            throw new \RuntimeException('V2 ciphertext too short.');
        }

        $marker = substr($data, 0, 4);
        $keyIdRaw = substr($data, 4, 4);
        $nonce = substr($data, 8, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = substr($data, 8 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $key = $key ?? KeyManager::deriveKey(KeyManager::PURPOSE_FIELD);

        // AAD must match what was used during encryption
        $aad = $marker . $keyIdRaw;

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            $aad,
            $nonce,
            $key
        );

        if ($plaintext === false) {
            throw new \RuntimeException('V2 decryption failed: invalid key or corrupted data.');
        }

        return $plaintext;
    }

    // =========================================================================
    //  V1 String: AES-256-GCM (backward compatibility)
    // =========================================================================

    /**
     * Encrypt a string with AES-256-GCM (V1 fallback).
     *
     * Format: [IV(12)] + [TAG(16)] + [ciphertext]
     */
    private static function encryptV1String(string $plaintext, ?string $key = null): string
    {
        $key = $key ?? KeyManager::loadKey();
        $iv = random_bytes(self::V1_IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::V1_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::V1_TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('V1 encryption failed: ' . openssl_error_string());
        }

        return $iv . $tag . $ciphertext;
    }

    /**
     * Decrypt a V1 string (AES-256-GCM).
     * Uses the raw master key (not HKDF-derived) for backward compatibility.
     */
    private static function decryptV1String(string $ciphertext, ?string $key = null): string
    {
        // V1 used raw master key, not derived subkey
        $key = $key ?? KeyManager::loadKey();

        $minLength = self::V1_IV_LENGTH + self::V1_TAG_LENGTH + 1;
        if (strlen($ciphertext) < $minLength) {
            throw new \RuntimeException('V1 ciphertext too short.');
        }

        $iv = substr($ciphertext, 0, self::V1_IV_LENGTH);
        $tag = substr($ciphertext, self::V1_IV_LENGTH, self::V1_TAG_LENGTH);
        $encrypted = substr($ciphertext, self::V1_IV_LENGTH + self::V1_TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $encrypted,
            self::V1_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('V1 decryption failed: invalid key or corrupted data.');
        }

        return $plaintext;
    }

    // =========================================================================
    //  V2 File: Sodium Secretstream (chunked streaming)
    // =========================================================================

    /**
     * Encrypt a file using sodium secretstream (V2).
     *
     * Reads plaintext in 64 KB chunks, encrypts each with per-chunk AEAD.
     * Never loads the full file into memory.
     *
     * Format:
     *   [AHG-ENC-V2(10)] + [KEY_ID(4 LE)] + [CHUNK_SIZE(4 LE)] + [STREAM_HEADER(24)]
     *   [CHUNK_1] ... [CHUNK_N]
     *   Each chunk = plaintext_chunk_size + ABYTES (17) bytes
     *   Last chunk tagged with TAG_FINAL
     */
    private static function encryptFileV2(string $inputPath, string $outputPath, ?string $key = null): void
    {
        $key = $key ?? KeyManager::deriveKey(KeyManager::PURPOSE_FILE);
        $keyId = KeyManager::getKeyId();
        $chunkSize = self::V2_CHUNK_SIZE;

        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

        $in = fopen($inputPath, 'rb');
        if (!$in) {
            throw new \RuntimeException("Cannot open input file: {$inputPath}");
        }

        $out = fopen($outputPath, 'wb');
        if (!$out) {
            fclose($in);
            throw new \RuntimeException("Cannot open output file: {$outputPath}");
        }

        try {
            // Write V2 file header (10 + 4 + 4 + 24 = 42 bytes)
            fwrite($out, self::FILE_HEADER_V2);       // 10 bytes magic
            fwrite($out, pack('V', $keyId));           // 4 bytes key ID (uint32 LE)
            fwrite($out, pack('V', $chunkSize));       // 4 bytes chunk size (uint32 LE)
            fwrite($out, $header);                     // 24 bytes secretstream header

            $fileSize = filesize($inputPath);
            $bytesRead = 0;

            while (true) {
                $chunk = fread($in, $chunkSize);

                if ($chunk === false || $chunk === '') {
                    // Empty file edge case: write a final empty chunk
                    if ($bytesRead === 0) {
                        $encChunk = sodium_crypto_secretstream_xchacha20poly1305_push(
                            $state,
                            '',
                            '',
                            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                        );
                        fwrite($out, $encChunk);
                    }

                    break;
                }

                $bytesRead += strlen($chunk);
                $isLast = feof($in);
                $tag = $isLast
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                $encChunk = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $chunk,
                    '',
                    $tag
                );
                fwrite($out, $encChunk);

                if ($isLast) {
                    break;
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Decrypt a V2 file to another file (chunked streaming).
     */
    private static function decryptFileV2(string $encryptedPath, string $outputPath, ?string $key = null): void
    {
        $in = fopen($encryptedPath, 'rb');
        if (!$in) {
            throw new \RuntimeException("Cannot open encrypted file: {$encryptedPath}");
        }

        $out = fopen($outputPath, 'wb');
        if (!$out) {
            fclose($in);
            throw new \RuntimeException("Cannot open output file: {$outputPath}");
        }

        try {
            // Read V2 header
            $magic = fread($in, self::HEADER_LENGTH);
            if ($magic !== self::FILE_HEADER_V2) {
                throw new \RuntimeException('Not a V2 encrypted file.');
            }

            $keyIdRaw = fread($in, 4);
            $chunkSizeRaw = fread($in, 4);
            $streamHeader = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if (strlen($streamHeader) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new \RuntimeException('Truncated V2 file header.');
            }

            ['1' => $chunkSize] = unpack('V', $chunkSizeRaw);
            $key = $key ?? KeyManager::deriveKey(KeyManager::PURPOSE_FILE);

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($streamHeader, $key);

            // Each encrypted chunk = plaintext chunk size + ABYTES overhead
            $encChunkSize = $chunkSize + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            while (true) {
                $encChunk = fread($in, $encChunkSize);

                if ($encChunk === false || $encChunk === '') {
                    break;
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $encChunk);

                if ($result === false) {
                    throw new \RuntimeException('V2 chunk decryption failed: corrupted data or wrong key.');
                }

                [$plainChunk, $tag] = $result;
                fwrite($out, $plainChunk);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Streaming decryption generator for V2 files.
     *
     * True streaming: reads one encrypted chunk at a time, decrypts it, and yields
     * the plaintext. Memory usage is bounded by chunk size (64 KB default).
     */
    private static function decryptFileStreamV2(string $encryptedPath, ?string $key = null): \Generator
    {
        $in = fopen($encryptedPath, 'rb');
        if (!$in) {
            throw new \RuntimeException("Cannot open encrypted file: {$encryptedPath}");
        }

        try {
            // Read V2 header
            $magic = fread($in, self::HEADER_LENGTH);
            if ($magic !== self::FILE_HEADER_V2) {
                throw new \RuntimeException('Not a V2 encrypted file.');
            }

            fread($in, 4); // key_id (not used for decryption, key already selected)
            $chunkSizeRaw = fread($in, 4);
            $streamHeader = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if (strlen($streamHeader) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new \RuntimeException('Truncated V2 file header.');
            }

            ['1' => $chunkSize] = unpack('V', $chunkSizeRaw);
            $key = $key ?? KeyManager::deriveKey(KeyManager::PURPOSE_FILE);

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($streamHeader, $key);
            $encChunkSize = $chunkSize + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            while (true) {
                $encChunk = fread($in, $encChunkSize);

                if ($encChunk === false || $encChunk === '') {
                    break;
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $encChunk);

                if ($result === false) {
                    throw new \RuntimeException('V2 stream decryption failed: corrupted data or wrong key.');
                }

                [$plainChunk, $tag] = $result;
                yield $plainChunk;

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            fclose($in);
        }
    }

    // =========================================================================
    //  V1 File: AES-256-GCM whole-file (backward compatibility)
    // =========================================================================

    /**
     * Encrypt a file with AES-256-GCM (V1 fallback).
     * Loads entire file into memory. Only used when sodium is unavailable.
     */
    private static function encryptFileV1(string $inputPath, string $outputPath, ?string $key = null): void
    {
        $key = $key ?? KeyManager::loadKey();
        $iv = random_bytes(self::V1_IV_LENGTH);
        $tag = '';

        $plaintext = file_get_contents($inputPath);
        if ($plaintext === false) {
            throw new \RuntimeException("Cannot read input file: {$inputPath}");
        }

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::V1_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::V1_TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('V1 file encryption failed: ' . openssl_error_string());
        }

        $output = self::FILE_HEADER_V1 . $iv . $tag . $ciphertext;

        if (file_put_contents($outputPath, $output) === false) {
            throw new \RuntimeException("Cannot write encrypted file: {$outputPath}");
        }
    }

    /**
     * Decrypt a V1 file.
     * Uses raw master key for backward compatibility.
     */
    private static function decryptFileV1(string $encryptedPath, string $outputPath, ?string $key = null): void
    {
        $key = $key ?? KeyManager::loadKey();
        $data = file_get_contents($encryptedPath);

        if ($data === false) {
            throw new \RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        $header = substr($data, 0, self::HEADER_LENGTH);
        if ($header !== self::FILE_HEADER_V1) {
            throw new \RuntimeException('Not a V1 encrypted file.');
        }

        $payload = substr($data, self::HEADER_LENGTH);
        $iv = substr($payload, 0, self::V1_IV_LENGTH);
        $tag = substr($payload, self::V1_IV_LENGTH, self::V1_TAG_LENGTH);
        $ciphertext = substr($payload, self::V1_IV_LENGTH + self::V1_TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::V1_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('V1 file decryption failed: invalid key or corrupted data.');
        }

        if (file_put_contents($outputPath, $plaintext) === false) {
            throw new \RuntimeException("Cannot write decrypted file: {$outputPath}");
        }
    }

    /**
     * Streaming decryption generator for V1 files.
     * Loads full file into memory (V1 limitation), then yields in chunks.
     */
    private static function decryptFileStreamV1(string $encryptedPath, ?string $key = null): \Generator
    {
        $key = $key ?? KeyManager::loadKey();
        $data = file_get_contents($encryptedPath);

        if ($data === false) {
            throw new \RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        $header = substr($data, 0, self::HEADER_LENGTH);
        if ($header !== self::FILE_HEADER_V1) {
            throw new \RuntimeException('Not a V1 encrypted file.');
        }

        $payload = substr($data, self::HEADER_LENGTH);
        $iv = substr($payload, 0, self::V1_IV_LENGTH);
        $tag = substr($payload, self::V1_IV_LENGTH, self::V1_TAG_LENGTH);
        $ciphertext = substr($payload, self::V1_IV_LENGTH + self::V1_TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::V1_ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('V1 stream decryption failed: invalid key or corrupted data.');
        }

        $offset = 0;
        $length = strlen($plaintext);

        while ($offset < $length) {
            yield substr($plaintext, $offset, self::OUTPUT_CHUNK_SIZE);
            $offset += self::OUTPUT_CHUNK_SIZE;
        }
    }
}
