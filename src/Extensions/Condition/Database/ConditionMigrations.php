<?php

declare(strict_types=1);

namespace AtoM\Framework\Extensions\Condition\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Condition Extension Migrations
 * 
 * Creates database tables for enhanced condition reporting features including
 * structured events, conservation linking, vocabularies, and risk tracking.
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ConditionMigrations
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('condition_migrations');
            $logPath = '/var/log/atom/condition_migrations.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_writable($logDir)) {
                self::$logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
            }
        }
        return self::$logger;
    }

    /**
     * Run all migrations
     */
    public static function migrate(): array
    {
        $results = [];

        $results['condition_event'] = self::createConditionEventTable();
        $results['condition_conservation_link'] = self::createConservationLinkTable();
        $results['condition_vocabulary_term'] = self::createVocabularyTermTable();
        $results['condition_assessment_schedule'] = self::createAssessmentScheduleTable();
        $results['spectrum_conservation_treatment'] = self::createConservationTreatmentTable();

        return $results;
    }

    /**
     * Create condition event table
     * Stores structured condition events derived from annotations
     */
    private static function createConditionEventTable(): bool
    {
        $tableName = 'condition_event';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `condition_check_id` INT UNSIGNED NOT NULL,
                    `photo_id` INT UNSIGNED NULL,
                    `object_id` INT NOT NULL,
                    `event_type` VARCHAR(50) NOT NULL DEFAULT 'observation' COMMENT 'observation, damage, treatment_needed',
                    `damage_type` VARCHAR(100) NULL COMMENT 'From controlled vocabulary',
                    `severity` ENUM('critical', 'severe', 'moderate', 'minor', 'stable') DEFAULT 'moderate',
                    `location_on_object` JSON NULL COMMENT 'Zone, position, coordinates',
                    `description` TEXT NULL,
                    `materials_affected` JSON NULL COMMENT 'List of affected materials',
                    `treatment_priority` INT NOT NULL DEFAULT 50 COMMENT '0-200 calculated priority',
                    `treatment_status` VARCHAR(50) NULL COMMENT 'pending, linked, completed',
                    `assessor_id` INT NULL,
                    `assessor_name` VARCHAR(255) NULL,
                    `assessment_date` DATE NOT NULL,
                    `annotation_id` VARCHAR(100) NULL COMMENT 'Reference to original annotation',
                    `metadata` JSON NULL COMMENT 'Additional structured data',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_check` (`condition_check_id`),
                    KEY `idx_photo` (`photo_id`),
                    KEY `idx_object` (`object_id`),
                    KEY `idx_severity` (`severity`),
                    KEY `idx_priority` (`treatment_priority`),
                    KEY `idx_status` (`treatment_status`),
                    KEY `idx_date` (`assessment_date`),
                    CONSTRAINT `fk_ce_check` FOREIGN KEY (`condition_check_id`) 
                        REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_ce_photo` FOREIGN KEY (`photo_id`) 
                        REFERENCES `spectrum_condition_photo` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            self::getLogger()->info("Created table {$tableName}");
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error("Failed to create {$tableName}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create conservation link table
     * Links condition events to conservation treatments
     */
    private static function createConservationLinkTable(): bool
    {
        $tableName = 'condition_conservation_link';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `condition_event_id` INT UNSIGNED NOT NULL,
                    `conservation_treatment_id` INT UNSIGNED NOT NULL,
                    `link_type` VARCHAR(50) NOT NULL DEFAULT 'treatment' COMMENT 'treatment, inspection, report',
                    `notes` TEXT NULL,
                    `created_by` INT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_event` (`condition_event_id`),
                    KEY `idx_treatment` (`conservation_treatment_id`),
                    CONSTRAINT `fk_ccl_event` FOREIGN KEY (`condition_event_id`) 
                        REFERENCES `condition_event` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            self::getLogger()->info("Created table {$tableName}");
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error("Failed to create {$tableName}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create vocabulary term table
     * Custom vocabulary terms for condition reporting
     */
    private static function createVocabularyTermTable(): bool
    {
        $tableName = 'condition_vocabulary_term';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `vocabulary_type` VARCHAR(50) NOT NULL COMMENT 'damage_type, material, location, etc.',
                    `term_key` VARCHAR(100) NOT NULL,
                    `label` VARCHAR(255) NOT NULL,
                    `category` VARCHAR(100) NULL,
                    `parent_term_id` INT UNSIGNED NULL,
                    `metadata` JSON NULL COMMENT 'color, severity_default, etc.',
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_by` INT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_term` (`vocabulary_type`, `term_key`),
                    KEY `idx_type` (`vocabulary_type`),
                    KEY `idx_category` (`category`),
                    KEY `idx_active` (`active`),
                    KEY `idx_parent` (`parent_term_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            self::getLogger()->info("Created table {$tableName}");
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error("Failed to create {$tableName}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create assessment schedule table
     * Tracks scheduled/upcoming condition assessments
     */
    private static function createAssessmentScheduleTable(): bool
    {
        $tableName = 'condition_assessment_schedule';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `object_id` INT NOT NULL,
                    `next_assessment_date` DATE NOT NULL,
                    `assessment_type` VARCHAR(50) DEFAULT 'routine' COMMENT 'routine, followup, urgent',
                    `priority` INT NOT NULL DEFAULT 50,
                    `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
                    `reminder_sent_at` DATETIME NULL,
                    `assigned_to` INT NULL COMMENT 'User ID of assigned assessor',
                    `notes` TEXT NULL,
                    `created_by` INT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_object` (`object_id`),
                    KEY `idx_date` (`next_assessment_date`),
                    KEY `idx_type` (`assessment_type`),
                    KEY `idx_reminder` (`reminder_sent`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            self::getLogger()->info("Created table {$tableName}");
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error("Failed to create {$tableName}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create conservation treatment table
     * Spectrum 5.0 Conservation Treatment procedure
     */
    private static function createConservationTreatmentTable(): bool
    {
        $tableName = 'spectrum_conservation_treatment';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `object_id` INT NOT NULL,
                    `treatment_number` VARCHAR(50) NOT NULL,
                    `treatment_type` VARCHAR(100) NOT NULL COMMENT 'cleaning, stabilization, repair, etc.',
                    `treatment_date` DATE NOT NULL,
                    `completion_date` DATE NULL,
                    `status` ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
                    `description` TEXT NOT NULL,
                    `materials_used` JSON NULL,
                    `before_condition` TEXT NULL,
                    `after_condition` TEXT NULL,
                    `conservator_id` INT NULL,
                    `conservator_name` VARCHAR(255) NULL,
                    `supervisor_id` INT NULL,
                    `cost_estimate` DECIMAL(12,2) NULL,
                    `actual_cost` DECIMAL(12,2) NULL,
                    `notes` TEXT NULL,
                    `attachments` JSON NULL COMMENT 'List of attached documents/images',
                    `created_by` INT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_number` (`treatment_number`),
                    KEY `idx_object` (`object_id`),
                    KEY `idx_type` (`treatment_type`),
                    KEY `idx_date` (`treatment_date`),
                    KEY `idx_status` (`status`),
                    KEY `idx_conservator` (`conservator_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            self::getLogger()->info("Created table {$tableName}");
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error("Failed to create {$tableName}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if table exists
     */
    private static function tableExists(string $tableName): bool
    {
        try {
            $result = DB::select("SHOW TABLES LIKE ?", [$tableName]);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Drop all condition extension tables
     */
    public static function rollback(): array
    {
        $tables = [
            'condition_conservation_link',
            'condition_event',
            'condition_vocabulary_term',
            'condition_assessment_schedule',
            'spectrum_conservation_treatment',
        ];

        $results = [];

        foreach ($tables as $table) {
            try {
                DB::statement("DROP TABLE IF EXISTS `{$table}`");
                $results[$table] = true;
                self::getLogger()->info("Dropped table {$table}");
            } catch (\Exception $e) {
                $results[$table] = false;
                self::getLogger()->error("Failed to drop {$table}", ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }
}
