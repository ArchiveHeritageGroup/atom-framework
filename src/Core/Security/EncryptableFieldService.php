<?php

declare(strict_types=1);

namespace AtomFramework\Core\Security;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Layer 2: Transparent database field encryption.
 *
 * Manages encryption/decryption of sensitive database columns by category.
 * Encrypted values are stored as base64-encoded ciphertext with a prefix marker.
 *
 * Categories:
 *   - contact_details: email, address, telephone, fax, contact person
 *   - financial_data: appraisal values
 *   - donor_information: actor history (when actor is donor)
 *   - personal_notes: note content
 *   - access_restrictions: rights notes
 */
class EncryptableFieldService
{
    private const ENCRYPTED_PREFIX = '{AHG-ENC}';

    /**
     * Field definitions by category.
     * Each entry: [table_name, column_name]
     */
    private const CATEGORIES = [
        'contact_details' => [
            ['contact_information', 'email'],
            ['contact_information', 'contact_person'],
            ['contact_information', 'street_address'],
            ['contact_information_i18n', 'city'],
            ['contact_information', 'telephone'],
            ['contact_information', 'fax'],
        ],
        'financial_data' => [
            ['accession_i18n', 'appraisal'],
        ],
        'donor_information' => [
            ['actor_i18n', 'history'],
        ],
        'personal_notes' => [
            ['note_i18n', 'content'],
        ],
        'access_restrictions' => [
            ['rights_i18n', 'rights_note'],
        ],
    ];

    /**
     * Check if a specific category is enabled for encryption.
     */
    public static function isCategoryEnabled(string $category): bool
    {
        try {
            $key = 'encryption_field_' . $category;
            $row = DB::table('ahg_settings')
                ->where('setting_key', $key)
                ->value('setting_value');

            return $row === 'true' || $row === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the field definitions for a category.
     *
     * @return array<array{0: string, 1: string}> [[table, column], ...]
     */
    public static function getCategoryFields(string $category): array
    {
        return self::CATEGORIES[$category] ?? [];
    }

    /**
     * Get all available categories.
     *
     * @return string[]
     */
    public static function getCategories(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /**
     * Encrypt all fields in a category.
     *
     * @param string        $category Category name
     * @param callable|null $progress Callback: function(string $table, string $column, int $current, int $total)
     *
     * @return array{encrypted: int, skipped: int, failed: int, errors: string[]}
     */
    public static function encryptCategory(string $category, ?callable $progress = null): array
    {
        $result = ['encrypted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $fields = self::getCategoryFields($category);

        if (empty($fields)) {
            $result['errors'][] = "Unknown category: {$category}";

            return $result;
        }

        foreach ($fields as [$table, $column]) {
            try {
                $fieldResult = self::encryptField($table, $column, $progress);
                $result['encrypted'] += $fieldResult['encrypted'];
                $result['skipped'] += $fieldResult['skipped'];
                $result['failed'] += $fieldResult['failed'];
                $result['errors'] = array_merge($result['errors'], $fieldResult['errors']);

                // Track encryption state
                self::markFieldEncrypted($table, $column, $category);
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = "{$table}.{$column}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Decrypt all fields in a category (reverse encryption).
     *
     * @param string        $category Category name
     * @param callable|null $progress Callback
     *
     * @return array{decrypted: int, skipped: int, failed: int, errors: string[]}
     */
    public static function decryptCategory(string $category, ?callable $progress = null): array
    {
        $result = ['decrypted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $fields = self::getCategoryFields($category);

        if (empty($fields)) {
            $result['errors'][] = "Unknown category: {$category}";

            return $result;
        }

        foreach ($fields as [$table, $column]) {
            try {
                $fieldResult = self::decryptField($table, $column, $progress);
                $result['decrypted'] += $fieldResult['decrypted'];
                $result['skipped'] += $fieldResult['skipped'];
                $result['failed'] += $fieldResult['failed'];
                $result['errors'] = array_merge($result['errors'], $fieldResult['errors']);

                // Update tracking
                self::markFieldDecrypted($table, $column);
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = "{$table}.{$column}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Check if a specific field is currently encrypted.
     */
    public static function isFieldEncrypted(string $table, string $column): bool
    {
        try {
            return (bool) DB::table('ahg_encrypted_fields')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->where('is_encrypted', 1)
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Decrypt a single value if it's encrypted.
     *
     * @param string $value Raw database value
     *
     * @return string Decrypted value (or original if not encrypted)
     */
    public static function decryptValue(string $value): string
    {
        if (!str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        $encoded = substr($value, strlen(self::ENCRYPTED_PREFIX));
        $ciphertext = base64_decode($encoded, true);

        if ($ciphertext === false) {
            return $value; // Not valid base64, return as-is
        }

        try {
            return EncryptionService::decrypt($ciphertext);
        } catch (\Exception $e) {
            error_log("Field decryption failed: " . $e->getMessage());

            return $value; // Return encrypted value rather than crashing
        }
    }

    /**
     * Encrypt a plaintext value for storage.
     *
     * @param string $value Plaintext value
     *
     * @return string Prefixed base64-encoded ciphertext
     */
    public static function encryptValue(string $value): string
    {
        // Don't double-encrypt
        if (str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        $ciphertext = EncryptionService::encrypt($value);

        return self::ENCRYPTED_PREFIX . base64_encode($ciphertext);
    }

    /**
     * Check if a value is encrypted (has the prefix marker).
     */
    public static function isEncryptedValue(string $value): bool
    {
        return str_starts_with($value, self::ENCRYPTED_PREFIX);
    }

    /**
     * Encrypt all rows of a specific table.column.
     */
    private static function encryptField(string $table, string $column, ?callable $progress = null): array
    {
        $result = ['encrypted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        // Determine primary key column
        $pkColumn = self::getPrimaryKey($table);

        $rows = DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select([$pkColumn, $column])
            ->get();

        $total = count($rows);
        $current = 0;

        foreach ($rows as $row) {
            $current++;
            $value = $row->$column;

            if ($progress) {
                $progress($table, $column, $current, $total);
            }

            // Skip already-encrypted values
            if (str_starts_with($value, self::ENCRYPTED_PREFIX)) {
                $result['skipped']++;

                continue;
            }

            try {
                $encrypted = self::encryptValue($value);
                DB::table($table)
                    ->where($pkColumn, $row->$pkColumn)
                    ->update([$column => $encrypted]);
                $result['encrypted']++;
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = "{$table}.{$column} (PK={$row->$pkColumn}): " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Decrypt all rows of a specific table.column.
     */
    private static function decryptField(string $table, string $column, ?callable $progress = null): array
    {
        $result = ['decrypted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $pkColumn = self::getPrimaryKey($table);

        $rows = DB::table($table)
            ->whereNotNull($column)
            ->where($column, 'LIKE', self::ENCRYPTED_PREFIX . '%')
            ->select([$pkColumn, $column])
            ->get();

        $total = count($rows);
        $current = 0;

        foreach ($rows as $row) {
            $current++;
            $value = $row->$column;

            if ($progress) {
                $progress($table, $column, $current, $total);
            }

            if (!str_starts_with($value, self::ENCRYPTED_PREFIX)) {
                $result['skipped']++;

                continue;
            }

            try {
                $decrypted = self::decryptValue($value);
                DB::table($table)
                    ->where($pkColumn, $row->$pkColumn)
                    ->update([$column => $decrypted]);
                $result['decrypted']++;
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = "{$table}.{$column} (PK={$row->$pkColumn}): " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Determine the primary key column for a table.
     */
    private static function getPrimaryKey(string $table): string
    {
        // i18n tables have composite PK (id, culture), use 'id'
        // contact_information uses 'id'
        return 'id';
    }

    /**
     * Mark a field as encrypted in the tracking table.
     */
    private static function markFieldEncrypted(string $table, string $column, string $category): void
    {
        try {
            DB::table('ahg_encrypted_fields')->updateOrInsert(
                ['table_name' => $table, 'column_name' => $column],
                [
                    'category' => $category,
                    'is_encrypted' => 1,
                    'encrypted_at' => date('Y-m-d H:i:s'),
                    'algorithm' => KeyManager::hasSodium() ? 'xchacha20-poly1305' : 'aes-256-gcm',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            error_log("Cannot update encryption tracking: " . $e->getMessage());
        }
    }

    /**
     * Mark a field as decrypted in the tracking table.
     */
    private static function markFieldDecrypted(string $table, string $column): void
    {
        try {
            DB::table('ahg_encrypted_fields')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->update([
                    'is_encrypted' => 0,
                    'encrypted_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Exception $e) {
            error_log("Cannot update encryption tracking: " . $e->getMessage());
        }
    }
}
