<?php

declare(strict_types=1);

namespace AtomFramework\Console\Commands\Security;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Core\Security\KeyManager;

/**
 * CLI command to generate and validate the encryption master key.
 *
 * Usage:
 *   php bin/atom encryption:key --generate   Generate a new key
 *   php bin/atom encryption:key --validate   Validate existing key
 */
class EncryptionKeyCommand extends BaseCommand
{
    protected string $name = 'encryption:key';
    protected string $description = 'Generate or validate the AES-256 encryption master key';

    protected function configure(): void
    {
        $this->addOption('generate', 'g', 'Generate a new encryption key');
        $this->addOption('validate', null, 'Validate the existing key');
        $this->addOption('force', 'f', 'Overwrite existing key (use with --generate)');
    }

    protected function handle(): int
    {
        if ($this->option('generate')) {
            return $this->generateKey();
        }

        if ($this->option('validate')) {
            return $this->validateKey();
        }

        // Default: show status
        return $this->showStatus();
    }

    private function generateKey(): int
    {
        $path = KeyManager::getKeyPath();

        if (KeyManager::keyExists() && !$this->option('force')) {
            $this->error("Key already exists at {$path}");
            $this->info('Use --force to overwrite the existing key.');
            $this->warning('WARNING: Overwriting the key will make all previously encrypted data unrecoverable!');

            return 1;
        }

        if (KeyManager::keyExists() && $this->option('force')) {
            $this->warning('WARNING: Overwriting encryption key. Previously encrypted data will be unrecoverable.');

            if (!$this->option('no-interaction')) {
                $confirm = $this->ask('Type YES to confirm key regeneration');
                if ($confirm !== 'YES') {
                    $this->info('Aborted.');

                    return 1;
                }
            }
        }

        try {
            $key = KeyManager::generateKey();
            KeyManager::saveKey($key);

            $this->success("Encryption key generated successfully.");
            $this->info("  Path: {$path}");
            $this->info("  Algorithm: AES-256-GCM");
            $this->info("  Key length: 256 bits (32 bytes)");
            $this->info("  Permissions: 0600 (owner read-only)");
            $this->line('');
            $this->warning('IMPORTANT: Back up this key securely. If lost, all encrypted data is unrecoverable.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Key generation failed: ' . $e->getMessage());

            return 1;
        }
    }

    private function validateKey(): int
    {
        $path = KeyManager::getKeyPath();

        if (!KeyManager::keyExists()) {
            $this->error("No key file found at {$path}");
            $this->info('Generate one with: php bin/atom encryption:key --generate');

            return 1;
        }

        try {
            $key = KeyManager::loadKey();
            $this->success("Key is valid.");
            $this->info("  Path: {$path}");
            $this->info("  Length: " . strlen($key) . " bytes (256 bits)");
            $this->info("  Permissions: " . substr(sprintf('%o', fileperms($path)), -4));

            // Test encrypt/decrypt round-trip
            $testData = 'AHG encryption validation test';
            $encrypted = \AtomFramework\Core\Security\EncryptionService::encrypt($testData, $key);
            $decrypted = \AtomFramework\Core\Security\EncryptionService::decrypt($encrypted, $key);

            if ($decrypted === $testData) {
                $this->success("  Round-trip test: PASSED");
            } else {
                $this->error("  Round-trip test: FAILED");

                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Key validation failed: ' . $e->getMessage());

            return 1;
        }
    }

    private function showStatus(): int
    {
        $path = KeyManager::getKeyPath();
        $this->info('Encryption Key Status');
        $this->info('=====================');
        $this->info("  Key path: {$path}");

        if (KeyManager::keyExists()) {
            $this->success("  Status: Key file exists");
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $this->info("  Permissions: {$perms}");

            if ($perms !== '0600') {
                $this->warning("  Warning: Permissions should be 0600 for security. Run: chmod 600 {$path}");
            }

            $mtime = filemtime($path);
            $this->info("  Last modified: " . date('Y-m-d H:i:s', $mtime));
        } else {
            $this->warning("  Status: No key file found");
            $this->info('  Generate with: php bin/atom encryption:key --generate');
        }

        return 0;
    }
}
