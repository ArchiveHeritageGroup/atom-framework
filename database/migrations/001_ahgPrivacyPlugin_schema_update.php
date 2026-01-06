<?php
/**
 * Add missing columns and tables to privacy plugin
 */
use Illuminate\Database\Capsule\Manager as DB;

return new class {
    public function up(): void
    {
        // privacy_processing_activity columns
        $processingColumns = [
            'jurisdiction' => "VARCHAR(30) NOT NULL DEFAULT 'popia' AFTER id",
            'description' => "TEXT NULL AFTER name",
            'third_countries' => "JSON NULL AFTER recipients",
            'dpia_date' => "DATE NULL AFTER dpia_completed",
            'owner' => "VARCHAR(255) NULL AFTER status",
            'next_review_date' => "DATE NULL AFTER owner",
            'lawful_basis_code' => "VARCHAR(50) NULL AFTER lawful_basis"
        ];

        foreach ($processingColumns as $column => $definition) {
            $this->addColumnIfNotExists('privacy_processing_activity', $column, $definition);
        }

        // privacy_consent_record columns
        $consentColumns = [
            'status' => "VARCHAR(50) NULL DEFAULT 'active'",
            'withdrawn_date' => "DATE NULL"
        ];

        foreach ($consentColumns as $column => $definition) {
            $this->addColumnIfNotExists('privacy_consent_record', $column, $definition);
        }

        // Create missing i18n tables
        $this->createTableIfNotExists('privacy_dsar_i18n', "
            CREATE TABLE `privacy_dsar_i18n` (
                `id` INT UNSIGNED NOT NULL,
                `culture` VARCHAR(16) NOT NULL DEFAULT 'en',
                `description` TEXT NULL,
                `notes` TEXT NULL,
                `response_summary` TEXT NULL,
                PRIMARY KEY (`id`, `culture`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->createTableIfNotExists('privacy_breach_i18n', "
            CREATE TABLE `privacy_breach_i18n` (
                `id` INT UNSIGNED NOT NULL,
                `culture` VARCHAR(16) NOT NULL DEFAULT 'en',
                `description` TEXT NULL,
                `impact_assessment` TEXT NULL,
                `remediation_notes` TEXT NULL,
                PRIMARY KEY (`id`, `culture`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->createTableIfNotExists('privacy_processing_activity_i18n', "
            CREATE TABLE `privacy_processing_activity_i18n` (
                `id` INT UNSIGNED NOT NULL,
                `culture` VARCHAR(16) NOT NULL DEFAULT 'en',
                `description` TEXT NULL,
                `purpose_details` TEXT NULL,
                PRIMARY KEY (`id`, `culture`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->createTableIfNotExists('privacy_officer', "
            CREATE TABLE `privacy_officer` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `role` VARCHAR(100) NULL DEFAULT 'Information Officer',
                `registration_number` VARCHAR(100) NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "  Privacy schema update complete\n";
    }

    protected function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            try {
                DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                echo "  Added {$table}.{$column}\n";
            } catch (\Exception $e) {
                echo "  {$table}.{$column}: " . $e->getMessage() . "\n";
            }
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND column_name = ?
        ", [$table, $column]);
        
        return $result[0]->cnt > 0;
    }

    protected function createTableIfNotExists(string $table, string $sql): void
    {
        if (!$this->tableExists($table)) {
            try {
                DB::statement($sql);
                echo "  Created table: {$table}\n";
            } catch (\Exception $e) {
                echo "  Table {$table}: " . $e->getMessage() . "\n";
            }
        }
    }

    protected function tableExists(string $table): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as cnt FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = ?
        ", [$table]);
        
        return $result[0]->cnt > 0;
    }
};
