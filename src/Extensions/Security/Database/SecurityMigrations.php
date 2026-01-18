<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Security\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Security Extension Migrations
 * 
 * Creates database tables for enhanced security features including
 * compliance reporting, retention schedules, and justification templates.
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class SecurityMigrations
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('security_migrations');
            $logPath = '/var/log/atom/security_migrations.log';
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

        $results['security_access_condition_link'] = self::createAccessConditionLinkTable();
        $results['security_retention_schedule'] = self::createRetentionScheduleTable();
        $results['security_compliance_log'] = self::createComplianceLogTable();
        $results['access_justification_template'] = self::createJustificationTemplateTable();
        $results['access_request_justification'] = self::createRequestJustificationTable();

        return $results;
    }

    /**
     * Create access condition link table
     * Links security classifications to ISAD access conditions
     */
    private static function createAccessConditionLinkTable(): bool
    {
        $tableName = 'security_access_condition_link';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `object_id` INT NOT NULL,
                    `classification_id` INT UNSIGNED NOT NULL,
                    `access_conditions` TEXT NULL,
                    `reproduction_conditions` TEXT NULL,
                    `narssa_ref` VARCHAR(100) NULL COMMENT 'NARSSA disposal authority reference',
                    `retention_period` VARCHAR(50) NULL,
                    `updated_by` INT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_object` (`object_id`),
                    KEY `idx_classification` (`classification_id`),
                    CONSTRAINT `fk_sacl_object` FOREIGN KEY (`object_id`) 
                        REFERENCES `information_object` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_sacl_classification` FOREIGN KEY (`classification_id`) 
                        REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
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
     * Create retention schedule table
     * Defines retention and declassification rules per classification level
     */
    private static function createRetentionScheduleTable(): bool
    {
        $tableName = 'security_retention_schedule';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `classification_id` INT UNSIGNED NOT NULL,
                    `retention_years` INT NOT NULL DEFAULT 10,
                    `action` ENUM('declassify', 'review', 'destroy', 'archive') NOT NULL DEFAULT 'review',
                    `declassify_to_id` INT UNSIGNED NULL COMMENT 'Target classification after declassification',
                    `legal_basis` VARCHAR(500) NULL COMMENT 'Legal reference for retention period',
                    `narssa_schedule` VARCHAR(100) NULL COMMENT 'NARSSA disposal schedule reference',
                    `review_frequency_months` INT NULL DEFAULT 12,
                    `auto_process` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Auto-process on due date',
                    `notification_days` INT NOT NULL DEFAULT 30 COMMENT 'Days before due date to notify',
                    `updated_by` INT NULL,
                    `updated_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_classification` (`classification_id`),
                    KEY `idx_declassify_to` (`declassify_to_id`),
                    CONSTRAINT `fk_srs_classification` FOREIGN KEY (`classification_id`) 
                        REFERENCES `security_classification` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_srs_declassify_to` FOREIGN KEY (`declassify_to_id`) 
                        REFERENCES `security_classification` (`id`) ON DELETE SET NULL
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
     * Create compliance log table
     * Audit trail for all compliance-related actions
     */
    private static function createComplianceLogTable(): bool
    {
        $tableName = 'security_compliance_log';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `object_id` INT NULL,
                    `action` VARCHAR(100) NOT NULL,
                    `user_id` INT NULL,
                    `details` JSON NULL,
                    `ip_address` VARCHAR(45) NULL,
                    `user_agent` VARCHAR(500) NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_object` (`object_id`),
                    KEY `idx_user` (`user_id`),
                    KEY `idx_action` (`action`),
                    KEY `idx_created` (`created_at`)
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
     * Create justification template table
     * PAIA/POPIA aligned templates for access requests
     */
    private static function createJustificationTemplateTable(): bool
    {
        $tableName = 'access_justification_template';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NOT NULL,
                    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
                    `template_text` TEXT NOT NULL,
                    `paia_section` VARCHAR(50) NULL COMMENT 'PAIA Act section reference',
                    `popia_ground` VARCHAR(50) NULL COMMENT 'POPIA lawful processing ground',
                    `required_evidence` JSON NULL COMMENT 'List of required supporting documents',
                    `active` TINYINT(1) NOT NULL DEFAULT 1,
                    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_by` INT NULL,
                    `updated_by` INT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_category` (`category`),
                    KEY `idx_active` (`active`)
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
     * Create request justification table
     * Links access requests to justification templates and tracks completeness
     */
    private static function createRequestJustificationTable(): bool
    {
        $tableName = 'access_request_justification';

        if (self::tableExists($tableName)) {
            self::getLogger()->info("Table {$tableName} already exists");
            return true;
        }

        try {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `request_id` INT UNSIGNED NOT NULL,
                    `template_id` INT UNSIGNED NULL,
                    `justification_text` TEXT NOT NULL,
                    `paia_section` VARCHAR(50) NULL,
                    `popia_ground` VARCHAR(50) NULL,
                    `evidence_provided` JSON NULL COMMENT 'List of evidence documents provided',
                    `validation_status` ENUM('pending', 'valid', 'incomplete', 'rejected') DEFAULT 'pending',
                    `validation_notes` TEXT NULL,
                    `validated_by` INT NULL,
                    `validated_at` DATETIME NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_request` (`request_id`),
                    KEY `idx_template` (`template_id`),
                    KEY `idx_validation` (`validation_status`),
                    CONSTRAINT `fk_arj_request` FOREIGN KEY (`request_id`) 
                        REFERENCES `access_request` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_arj_template` FOREIGN KEY (`template_id`) 
                        REFERENCES `access_justification_template` (`id`) ON DELETE SET NULL
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
     * Drop all security extension tables (for clean reinstall)
     */
    public static function rollback(): array
    {
        $tables = [
            'access_request_justification',
            'access_justification_template',
            'security_compliance_log',
            'security_retention_schedule',
            'security_access_condition_link',
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
