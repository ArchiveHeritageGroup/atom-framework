<?php

declare(strict_types=1);

namespace AtomFramework\Console\Commands\Security;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Core\Security\EncryptableFieldService;
use AtomFramework\Core\Security\EncryptionService;
use AtomFramework\Core\Security\FileEncryptionService;
use AtomFramework\Core\Security\KeyManager;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * CLI command to display encryption status dashboard.
 *
 * Usage:
 *   php bin/atom encryption:status
 */
class EncryptionStatusCommand extends BaseCommand
{
    protected string $name = 'encryption:status';
    protected string $description = 'Display encryption status dashboard';

    protected function handle(): int
    {
        $this->info('');
        $this->info('=======================================');
        $this->info('  AtoM Heratio Encryption Dashboard');
        $this->info('=======================================');
        $this->info('');

        $this->showKeyStatus();
        $this->showSettingsStatus();
        $this->showFileEncryptionStatus();
        $this->showFieldEncryptionStatus();
        $this->showAuditSummary();

        return 0;
    }

    private function showKeyStatus(): void
    {
        $this->info('MASTER KEY');
        $this->info('----------');

        $path = KeyManager::getKeyPath();
        $this->info("  Path: {$path}");

        if (!KeyManager::keyExists()) {
            $this->warning("  Status: NOT CONFIGURED");
            $this->info("  Generate with: php bin/atom encryption:key --generate");
            $this->line('');

            return;
        }

        try {
            $key = KeyManager::loadKey();
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $mtime = filemtime($path);
            $age = round((time() - $mtime) / 86400);

            $this->success("  Status: Valid");
            $this->info("  Algorithm: AES-256-GCM");
            $this->info("  Permissions: {$perms}" . ($perms !== '0600' ? ' (WARNING: should be 0600)' : ''));
            $this->info("  Created: " . date('Y-m-d H:i:s', $mtime) . " ({$age} days ago)");
        } catch (\Exception $e) {
            $this->error("  Status: INVALID - " . $e->getMessage());
        }

        $this->line('');
    }

    private function showSettingsStatus(): void
    {
        $this->info('SETTINGS');
        $this->info('--------');

        $settings = [
            'encryption_enabled' => 'Master toggle',
            'encryption_encrypt_derivatives' => 'Encrypt derivatives',
            'encryption_field_contact_details' => 'Field: Contact details',
            'encryption_field_financial_data' => 'Field: Financial data',
            'encryption_field_donor_information' => 'Field: Donor information',
            'encryption_field_personal_notes' => 'Field: Personal notes',
            'encryption_field_access_restrictions' => 'Field: Access restrictions',
        ];

        foreach ($settings as $key => $label) {
            try {
                $value = DB::table('ahg_settings')
                    ->where('setting_key', $key)
                    ->value('setting_value');

                $status = ($value === 'true' || $value === '1') ? 'ON' : 'OFF';
                $icon = $status === 'ON' ? '[ON ]' : '[OFF]';
                $this->info("  {$icon} {$label}");
            } catch (\Exception $e) {
                $this->info("  [???] {$label} (table not found)");
            }
        }

        $this->line('');
    }

    private function showFileEncryptionStatus(): void
    {
        $this->info('FILE ENCRYPTION (Layer 1)');
        $this->info('------------------------');

        try {
            $totalDOs = DB::table('digital_object')
                ->whereNotNull('path')
                ->whereNotNull('name')
                ->count();

            // Sample check: read first N files to estimate encryption rate
            $sampleSize = min($totalDOs, 50);
            $objects = DB::table('digital_object')
                ->whereNotNull('path')
                ->whereNotNull('name')
                ->limit($sampleSize)
                ->get(['path', 'name']);

            $webDir = $this->atomRoot;
            $encrypted = 0;
            $plaintext = 0;
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
                    $encrypted++;
                } else {
                    $plaintext++;
                }
            }

            $this->info("  Total digital objects: {$totalDOs}");
            $this->info("  Sample ({$sampleSize} files):");
            $this->info("    Encrypted: {$encrypted}");
            $this->info("    Plaintext: {$plaintext}");
            $this->info("    Missing:   {$missing}");

            if ($totalDOs > 0 && $sampleSize > 0) {
                $pct = round(($encrypted / $sampleSize) * 100, 1);
                $this->info("  Estimated encryption rate: {$pct}%");
            }
        } catch (\Exception $e) {
            $this->warning("  Cannot query digital objects: " . $e->getMessage());
        }

        $this->line('');
    }

    private function showFieldEncryptionStatus(): void
    {
        $this->info('FIELD ENCRYPTION (Layer 2)');
        $this->info('-------------------------');

        $categories = EncryptableFieldService::getCategories();

        foreach ($categories as $category) {
            $fields = EncryptableFieldService::getCategoryFields($category);
            $encCount = 0;

            foreach ($fields as [$table, $column]) {
                if (EncryptableFieldService::isFieldEncrypted($table, $column)) {
                    $encCount++;
                }
            }

            $total = count($fields);
            $status = $encCount === 0 ? 'Plaintext' : ($encCount === $total ? 'Encrypted' : "Partial ({$encCount}/{$total})");
            $icon = $encCount === $total && $total > 0 ? '[ENC]' : '[   ]';
            $this->info("  {$icon} {$category}: {$status} ({$total} fields)");
        }

        $this->line('');
    }

    private function showAuditSummary(): void
    {
        $this->info('AUDIT LOG');
        $this->info('---------');

        try {
            $total = DB::table('ahg_encryption_audit')->count();
            $this->info("  Total operations: {$total}");

            if ($total > 0) {
                $byAction = DB::table('ahg_encryption_audit')
                    ->selectRaw('action, COUNT(*) as cnt')
                    ->groupBy('action')
                    ->pluck('cnt', 'action')
                    ->toArray();

                foreach ($byAction as $action => $count) {
                    $this->info("    {$action}: {$count}");
                }

                $latest = DB::table('ahg_encryption_audit')
                    ->orderByDesc('created_at')
                    ->first(['action', 'target_type', 'status', 'created_at']);

                if ($latest) {
                    $this->info("  Last operation: {$latest->action} ({$latest->target_type}) at {$latest->created_at} - {$latest->status}");
                }
            }
        } catch (\Exception $e) {
            $this->info("  Audit table not found. Run encryption_tables.sql to create it.");
        }

        $this->line('');
    }
}
