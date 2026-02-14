<?php

declare(strict_types=1);

namespace AtomFramework\Core\Security;

/**
 * Core AES-256-GCM encryption service.
 *
 * Provides string and file encryption/decryption with authenticated encryption.
 * Files use a magic header (AHG-ENC-V1) for detection.
 *
 * Encrypted file format:
 *   [AHG-ENC-V1]  10 bytes magic header
 *   [IV]           12 bytes (GCM nonce)
 *   [TAG]          16 bytes (GCM auth tag)
 *   [DATA]         variable length ciphertext
 */
class EncryptionService
{
    private const ALGORITHM = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const CHUNK_SIZE = 8192;
    private const FILE_HEADER = 'AHG-ENC-V1';
    private const HEADER_LENGTH = 10;

    /**
     * Encrypt a string.
     *
     * @param string      $plaintext Data to encrypt
     * @param string|null $key       32-byte key (defaults to master key)
     *
     * @return string Binary ciphertext: IV + TAG + encrypted data
     */
    public static function encrypt(string $plaintext, ?string $key = null): string
    {
        $key = $key ?? self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return $iv . $tag . $ciphertext;
    }

    /**
     * Decrypt a string.
     *
     * @param string      $ciphertext Binary: IV + TAG + encrypted data
     * @param string|null $key        32-byte key (defaults to master key)
     *
     * @return string Decrypted plaintext
     */
    public static function decrypt(string $ciphertext, ?string $key = null): string
    {
        $key = $key ?? self::getKey();

        $minLength = self::IV_LENGTH + self::TAG_LENGTH + 1;
        if (strlen($ciphertext) < $minLength) {
            throw new \RuntimeException('Ciphertext too short to be valid.');
        }

        $iv = substr($ciphertext, 0, self::IV_LENGTH);
        $tag = substr($ciphertext, self::IV_LENGTH, self::TAG_LENGTH);
        $encrypted = substr($ciphertext, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $encrypted,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: invalid key or corrupted data.');
        }

        return $plaintext;
    }

    /**
     * Encrypt a file on disk.
     *
     * Reads the entire source file, encrypts it with AES-256-GCM, and writes
     * the result to $outputPath with the AHG-ENC-V1 header.
     *
     * @param string      $inputPath  Source file (plaintext)
     * @param string      $outputPath Destination file (encrypted)
     * @param string|null $key        32-byte key (defaults to master key)
     */
    public static function encryptFile(string $inputPath, string $outputPath, ?string $key = null): void
    {
        if (!file_exists($inputPath)) {
            throw new \RuntimeException("Input file not found: {$inputPath}");
        }

        $key = $key ?? self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $plaintext = file_get_contents($inputPath);
        if ($plaintext === false) {
            throw new \RuntimeException("Cannot read input file: {$inputPath}");
        }

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('File encryption failed: ' . openssl_error_string());
        }

        $output = self::FILE_HEADER . $iv . $tag . $ciphertext;

        if (file_put_contents($outputPath, $output) === false) {
            throw new \RuntimeException("Cannot write encrypted file: {$outputPath}");
        }
    }

    /**
     * Decrypt a file from disk to another file.
     *
     * @param string      $encryptedPath Source file (encrypted with AHG-ENC-V1 header)
     * @param string      $outputPath    Destination file (plaintext)
     * @param string|null $key           32-byte key (defaults to master key)
     */
    public static function decryptFile(string $encryptedPath, string $outputPath, ?string $key = null): void
    {
        if (!file_exists($encryptedPath)) {
            throw new \RuntimeException("Encrypted file not found: {$encryptedPath}");
        }

        $key = $key ?? self::getKey();
        $data = file_get_contents($encryptedPath);

        if ($data === false) {
            throw new \RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        // Validate and strip magic header
        $header = substr($data, 0, self::HEADER_LENGTH);
        if ($header !== self::FILE_HEADER) {
            throw new \RuntimeException('File does not have AHG-ENC-V1 header. Not an encrypted file.');
        }

        $payload = substr($data, self::HEADER_LENGTH);

        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr($payload, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($payload, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('File decryption failed: invalid key or corrupted data.');
        }

        if (file_put_contents($outputPath, $plaintext) === false) {
            throw new \RuntimeException("Cannot write decrypted file: {$outputPath}");
        }
    }

    /**
     * Streaming decryption via Generator.
     *
     * Decrypts the full file into memory and yields in CHUNK_SIZE pieces.
     * Suitable for serving decrypted content over HTTP without writing to disk.
     *
     * @param string      $encryptedPath Path to encrypted file
     * @param string|null $key           32-byte key
     *
     * @return \Generator yields string chunks
     */
    public static function decryptFileStream(string $encryptedPath, ?string $key = null): \Generator
    {
        if (!file_exists($encryptedPath)) {
            throw new \RuntimeException("Encrypted file not found: {$encryptedPath}");
        }

        $key = $key ?? self::getKey();
        $data = file_get_contents($encryptedPath);

        if ($data === false) {
            throw new \RuntimeException("Cannot read encrypted file: {$encryptedPath}");
        }

        $header = substr($data, 0, self::HEADER_LENGTH);
        if ($header !== self::FILE_HEADER) {
            throw new \RuntimeException('Not an AHG-ENC-V1 encrypted file.');
        }

        $payload = substr($data, self::HEADER_LENGTH);
        $iv = substr($payload, 0, self::IV_LENGTH);
        $tag = substr($payload, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($payload, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Stream decryption failed: invalid key or corrupted data.');
        }

        $offset = 0;
        $length = strlen($plaintext);

        while ($offset < $length) {
            yield substr($plaintext, $offset, self::CHUNK_SIZE);
            $offset += self::CHUNK_SIZE;
        }
    }

    /**
     * Check if a file has the AHG-ENC-V1 magic header.
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

        return $header === self::FILE_HEADER;
    }

    /**
     * Get the master encryption key.
     */
    public static function getKey(): string
    {
        return KeyManager::loadKey();
    }

    /**
     * Get the file header constant (for external validation).
     */
    public static function getFileHeader(): string
    {
        return self::FILE_HEADER;
    }
}
