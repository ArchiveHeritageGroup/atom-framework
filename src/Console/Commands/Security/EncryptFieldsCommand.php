<?php

declare(strict_types=1);

namespace AtomFramework\Console\Commands\Security;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Core\Security\EncryptableFieldService;
use AtomFramework\Core\Security\KeyManager;

/**
 * CLI command to encrypt or decrypt database field categories.
 *
 * Usage:
 *   php bin/atom encryption:encrypt-fields --category=contact_details
 *   php bin/atom encryption:encrypt-fields --category=contact_details --reverse
 *   php bin/atom encryption:encrypt-fields --list
 */
class EncryptFieldsCommand extends BaseCommand
{
    protected string $name = 'encryption:encrypt-fields';
    protected string $description = 'Encrypt or decrypt sensitive database field categories (Layer 2)';

    protected function configure(): void
    {
        $this->addOption('category', 'c', 'Category to encrypt (contact_details, financial_data, donor_information, personal_notes, access_restrictions)');
        $this->addOption('reverse', 'r', 'Decrypt instead of encrypt');
        $this->addOption('list', null, 'List all categories and their status');
        $this->addOption('all', null, 'Process all enabled categories');
    }

    protected function handle(): int
    {
        // Verify key exists
        if (!KeyManager::keyExists()) {
            $this->error('No encryption key found. Generate one first:');
            $this->info('  php bin/atom encryption:key --generate');

            return 1;
        }

        if ($this->option('list')) {
            return $this->listCategories();
        }

        if ($this->option('all')) {
            return $this->processAll();
        }

        $category = $this->option('category');
        if (!$category) {
            $this->error('Specify a category with --category or use --list to see available categories.');

            return 1;
        }

        $reverse = (bool) $this->option('reverse');

        return $this->processCategory($category, $reverse);
    }

    private function listCategories(): int
    {
        $this->info('Encryption Field Categories');
        $this->info('===========================');

        $categories = EncryptableFieldService::getCategories();

        $rows = [];
        foreach ($categories as $category) {
            $fields = EncryptableFieldService::getCategoryFields($category);
            $enabled = EncryptableFieldService::isCategoryEnabled($category);

            $fieldCount = count($fields);
            $encryptedCount = 0;

            foreach ($fields as [$table, $column]) {
                if (EncryptableFieldService::isFieldEncrypted($table, $column)) {
                    $encryptedCount++;
                }
            }

            $status = $encryptedCount > 0
                ? ($encryptedCount === $fieldCount ? 'Encrypted' : "Partial ({$encryptedCount}/{$fieldCount})")
                : 'Plaintext';

            $rows[] = [
                $category,
                $enabled ? 'Yes' : 'No',
                $fieldCount,
                $status,
            ];
        }

        $this->table(['Category', 'Enabled', 'Fields', 'Status'], $rows);

        $this->line('');
        $this->info('Field Details:');
        foreach ($categories as $category) {
            $fields = EncryptableFieldService::getCategoryFields($category);
            $this->info("  {$category}:");
            foreach ($fields as [$table, $column]) {
                $encrypted = EncryptableFieldService::isFieldEncrypted($table, $column);
                $icon = $encrypted ? '[ENC]' : '[   ]';
                $this->info("    {$icon} {$table}.{$column}");
            }
        }

        return 0;
    }

    private function processCategory(string $category, bool $reverse): int
    {
        $fields = EncryptableFieldService::getCategoryFields($category);
        if (empty($fields)) {
            $this->error("Unknown category: {$category}");
            $this->info('Use --list to see available categories.');

            return 1;
        }

        $action = $reverse ? 'Decrypting' : 'Encrypting';
        $fieldCount = count($fields);

        $this->info("{$action} category '{$category}' ({$fieldCount} fields)...");

        $progress = function (string $table, string $column, int $current, int $total) {
            if ($this->verbose || $current === 1 || $current === $total || $current % 100 === 0) {
                $this->info("  {$table}.{$column}: {$current}/{$total}");
            }
        };

        if ($reverse) {
            $result = EncryptableFieldService::decryptCategory($category, $progress);
            $processed = $result['decrypted'];
        } else {
            $result = EncryptableFieldService::encryptCategory($category, $progress);
            $processed = $result['encrypted'];
        }

        $this->line('');
        $this->info('Results:');
        $this->info("  Processed: {$processed}");
        $this->info("  Skipped:   {$result['skipped']}");
        $this->info("  Failed:    {$result['failed']}");

        if (!empty($result['errors'])) {
            $this->line('');
            $this->warning('Errors:');
            foreach ($result['errors'] as $error) {
                $this->error("  {$error}");
            }
        }

        if ($processed > 0) {
            $word = $reverse ? 'decrypted' : 'encrypted';
            $this->success("Successfully {$word} {$processed} value(s) in category '{$category}'.");
        }

        return $result['failed'] > 0 ? 1 : 0;
    }

    private function processAll(): int
    {
        $reverse = (bool) $this->option('reverse');
        $action = $reverse ? 'Decrypting' : 'Encrypting';
        $categories = EncryptableFieldService::getCategories();
        $hasErrors = false;

        foreach ($categories as $category) {
            if (!EncryptableFieldService::isCategoryEnabled($category) && !$reverse) {
                $this->info("Skipping '{$category}' (not enabled in settings).");

                continue;
            }

            $this->line('');
            $exitCode = $this->processCategory($category, $reverse);
            if ($exitCode !== 0) {
                $hasErrors = true;
            }
        }

        return $hasErrors ? 1 : 0;
    }
}
