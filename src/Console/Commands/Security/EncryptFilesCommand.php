<?php

declare(strict_types=1);

namespace AtomFramework\Console\Commands\Security;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Core\Security\FileEncryptionService;
use AtomFramework\Core\Security\EncryptionService;
use AtomFramework\Core\Security\KeyManager;

/**
 * CLI command to batch-encrypt existing digital objects on disk.
 *
 * Usage:
 *   php bin/atom encryption:encrypt-files --limit=100
 *   php bin/atom encryption:encrypt-files --id=123        Encrypt specific DO
 *   php bin/atom encryption:encrypt-files --with-derivatives
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
            // In dry-run mode, just count what would be processed
            $webDir = $this->atomRoot;
            $objects = \Illuminate\Database\Capsule\Manager::table('digital_object')
                ->whereNotNull('path')
                ->whereNotNull('name')
                ->limit($limit)
                ->get(['id', 'path', 'name']);

            $wouldEncrypt = 0;
            $alreadyEncrypted = 0;
            $missing = 0;

            foreach ($objects as $do) {
                if (str_starts_with($do->path, '/uploads/')) {
                    $fullPath = $webDir . $do->path . '/' . $do->name;
                } else {
                    $fullPath = $webDir . '/uploads/' . trim($do->path, '/') . '/' . $do->name;
                }

                if (!file_exists($fullPath)) {
                    $missing++;
                } elseif (EncryptionService::isEncryptedFile($fullPath)) {
                    $alreadyEncrypted++;
                } else {
                    $wouldEncrypt++;
                    if ($this->verbose) {
                        $this->info("  Would encrypt: {$fullPath}");
                    }
                }
            }

            $this->info("Summary (dry run):");
            $this->info("  Would encrypt: {$wouldEncrypt}");
            $this->info("  Already encrypted: {$alreadyEncrypted}");
            $this->info("  Missing files: {$missing}");

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
}
