<?php

declare(strict_types=1);

namespace AtomFramework\Console\Commands\Security;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Core\Security\FileEncryptionService;
use AtomFramework\Core\Security\EncryptionService;
use AtomFramework\Core\Security\KeyManager;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * CLI command to batch-encrypt existing digital objects on disk.
 *
 * Usage:
 *   php bin/atom encryption:encrypt-files --limit=100
 *   php bin/atom encryption:encrypt-files --id=123        Encrypt specific DO
 *   php bin/atom encryption:encrypt-files --with-derivatives
 *   php bin/atom encryption:encrypt-files --upgrade-v2    Re-encrypt V1 files as V2
 */
class EncryptFilesCommand extends BaseCommand
{
    protected string $name = 'encryption:encrypt-files';
    protected string $description = 'Encrypt existing digital object files on disk (Layer 1)';

    protected function configure(): void
    {
        $this->addOption('limit', 'l', 'Maximum number of files to process', '100');
        $this->addOption('id', null, 'Encrypt a specific digital object by ID');
        $this->addOption('with-derivatives', 'd', 'Also encrypt derivatives (thumbnails, reference images)');
        $this->addOption('dry-run', null, 'Show what would be encrypted without making changes');
        $this->addOption('upgrade-v2', null, 'Re-encrypt V1 (AES-256-GCM) files as V2 (XChaCha20-Poly1305)');
    }

    protected function handle(): int
    {
        // Verify key exists
        if (!KeyManager::keyExists()) {
            $this->error('No encryption key found. Generate one first:');
            $this->info('  php bin/atom encryption:key --generate');

            return 1;
        }

        try {
            KeyManager::loadKey();
        } catch (\Exception $e) {
            $this->error('Invalid encryption key: ' . $e->getMessage());

            return 1;
        }

        $algo = KeyManager::hasSodium() ? 'V2 (XChaCha20-Poly1305)' : 'V1 (AES-256-GCM)';
        $this->info("Using algorithm: {$algo}");

        // V1 → V2 upgrade mode
        if ($this->option('upgrade-v2')) {
            return $this->upgradeV1ToV2();
        }

        // Single digital object mode
        if ($id = $this->option('id')) {
            return $this->encryptSingle((int) $id);
        }

        // Batch mode
        return $this->encryptBatch();
    }

    private function encryptSingle(int $id): int
    {
        $this->info("Encrypting digital object #{$id}...");

        if (FileEncryptionService::encryptDigitalObject($id)) {
            $this->success("  Master file encrypted.");

            if ($this->option('with-derivatives')) {
                $count = FileEncryptionService::encryptDerivatives($id);
                $this->success("  {$count} derivative(s) encrypted.");
            }

            return 0;
        }

        $this->error("  Failed to encrypt digital object #{$id}.");

        return 1;
    }

    private function encryptBatch(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Batch encrypting digital objects (limit: {$limit})...");

        if ($dryRun) {
            $this->warning('DRY RUN - no files will be modified.');
        }

        $startTime = microtime(true);

        if ($dryRun) {
            $webDir = $this->atomRoot;
            $objects = DB::table('digital_object')
                ->whereNotNull('path')
                ->whereNotNull('name')
                ->limit($limit)
                ->get(['id', 'path', 'name']);

            $wouldEncrypt = 0;
            $v1Count = 0;
            $v2Count = 0;
            $missing = 0;

            foreach ($objects as $do) {
                if (str_starts_with($do->path, '/uploads/')) {
                    $fullPath = $webDir . $do->path . '/' . $do->name;
                } else {
                    $fullPath = $webDir . '/uploads/' . trim($do->path, '/') . '/' . $do->name;
                }

                if (!file_exists($fullPath)) {
                    $missing++;
                } else {
                    $version = EncryptionService::detectFileVersion($fullPath);
                    if ($version === 2) {
                        $v2Count++;
                    } elseif ($version === 1) {
                        $v1Count++;
                    } else {
                        $wouldEncrypt++;
                        if ($this->verbose) {
                            $this->info("  Would encrypt: {$fullPath}");
                        }
                    }
                }
            }

            $this->info("Summary (dry run):");
            $this->info("  Would encrypt: {$wouldEncrypt}");
            $this->info("  Already V2:    {$v2Count}");
            $this->info("  Already V1:    {$v1Count}");
            $this->info("  Missing files: {$missing}");

            if ($v1Count > 0) {
                $this->warning("  Use --upgrade-v2 to re-encrypt {$v1Count} V1 file(s) as V2.");
            }

            return 0;
        }

        $result = FileEncryptionService::encryptExisting($limit, function (int $current, int $total, string $path) {
            if ($this->verbose) {
                $this->info("  [{$current}/{$total}] {$path}");
            }
        });

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->info('Encryption Results:');
        $this->info("  Encrypted: {$result['encrypted']}");
        $this->info("  Skipped:   {$result['skipped']}");
        $this->info("  Failed:    {$result['failed']}");
        $this->info("  Time:      {$elapsed}s");

        if (!empty($result['errors'])) {
            $this->line('');
            $this->warning('Errors:');
            foreach ($result['errors'] as $error) {
                $this->error("  {$error}");
            }
        }

        if ($result['encrypted'] > 0) {
            $this->success("Successfully encrypted {$result['encrypted']} file(s).");
        }

        return $result['failed'] > 0 ? 1 : 0;
    }

    /**
     * Re-encrypt V1 files as V2 (AES-256-GCM → XChaCha20-Poly1305 streaming).
     *
     * Process: decrypt V1 → re-encrypt as V2, one file at a time.
     */
    private function upgradeV1ToV2(): int
    {
        if (!KeyManager::hasSodium()) {
            $this->error('Sodium extension is required for V2 encryption. Install php-sodium.');

            return 1;
        }

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $webDir = $this->atomRoot;

        $this->info("Upgrading V1 files to V2 (limit: {$limit})...");

        $objects = DB::table('digital_object')
            ->whereNotNull('path')
            ->whereNotNull('name')
            ->limit($limit)
            ->get(['id', 'path', 'name']);

        $upgraded = 0;
        $skipped = 0;
        $failed = 0;
        $total = count($objects);

        foreach ($objects as $i => $do) {
            if (str_starts_with($do->path, '/uploads/')) {
                $fullPath = $webDir . $do->path . '/' . $do->name;
            } else {
                $fullPath = $webDir . '/uploads/' . trim($do->path, '/') . '/' . $do->name;
            }

            if (!file_exists($fullPath)) {
                $skipped++;

                continue;
            }

            $version = EncryptionService::detectFileVersion($fullPath);

            if ($version !== 1) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->info("  Would upgrade: {$fullPath}");
                $upgraded++;

                continue;
            }

            try {
                // Decrypt V1 to temp, then encrypt as V2
                $tempPlain = $fullPath . '.v2tmp';
                $tempEnc = $fullPath . '.v2enc';

                EncryptionService::decryptFile($fullPath, $tempPlain);
                EncryptionService::encryptFile($tempPlain, $tempEnc);

                // Atomic replace
                if (!rename($tempEnc, $fullPath)) {
                    throw new \RuntimeException('Cannot replace original file.');
                }

                @unlink($tempPlain);
                $upgraded++;

                if ($this->verbose) {
                    $this->info("  [" . ($i + 1) . "/{$total}] Upgraded: " . basename($fullPath));
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("  Failed: " . basename($fullPath) . " - " . $e->getMessage());
                // Cleanup temp files
                if (isset($tempPlain) && file_exists($tempPlain)) {
                    @unlink($tempPlain);
                }
                if (isset($tempEnc) && file_exists($tempEnc)) {
                    @unlink($tempEnc);
                }
            }
        }

        $this->line('');
        $this->info($dryRun ? 'Upgrade Summary (dry run):' : 'Upgrade Results:');
        $this->info("  Upgraded: {$upgraded}");
        $this->info("  Skipped:  {$skipped}");
        $this->info("  Failed:   {$failed}");

        if ($upgraded > 0 && !$dryRun) {
            $this->success("Successfully upgraded {$upgraded} file(s) from V1 to V2.");
        }

        return $failed > 0 ? 1 : 0;
    }
}
