<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Privacy\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Privacy Extension Database Migrations
 *
 * Creates tables for POPIA/PAIA/GDPR compliance:
 * - Processing activities (ROPA)
 * - Data subject access requests (DSAR)
 * - Breach incidents
 * - Privacy templates
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class PrivacyMigrations
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('privacy_migrations');
            $logPath = '/var/log/atom/privacy_migrations.log';
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

        try {
            $results['processing_activity'] = self::createProcessingActivityTable();
            $results['dsar_request'] = self::createDsarRequestTable();
            $results['dsar_log'] = self::createDsarLogTable();
            $results['breach_incident'] = self::createBreachIncidentTable();
            $results['privacy_template'] = self::createPrivacyTemplateTable();
            $results['consent_record'] = self::createConsentRecordTable();

            self::getLogger()->info('Privacy migrations completed', $results);

        } catch (\Exception $e) {
            self::getLogger()->error('Privacy migration failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Processing Activity Table (ROPA)
     */
    private static function createProcessingActivityTable(): string
    {
        $tableName = 'privacy_processing_activity';

        if (self::tableExists($tableName)) {
            return 'exists';
        }

        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `purpose` TEXT NOT NULL,
            `lawful_basis` VARCHAR(100) NOT NULL COMMENT 'consent, contract, legal_obligation, vital_interests, public_task, legitimate_interests',
            `popia_condition` VARCHAR(100) DEFAULT NULL COMMENT 'POPIA Section 11 condition',
            `data_categories` TEXT DEFAULT NULL COMMENT 'JSON array of data types',
            `data_subjects` TEXT DEFAULT NULL COMMENT 'Description of data subjects',
            `recipients` TEXT DEFAULT NULL COMMENT 'Recipients or categories of recipients',
            `third_countries` TEXT DEFAULT NULL COMMENT 'Transfers to third countries',
            `retention_period` VARCHAR(255) DEFAULT NULL,
            `security_measures` TEXT DEFAULT NULL,
            `dpia_required` TINYINT(1) NOT NULL DEFAULT 0,
            `dpia_completed` TINYINT(1) NOT NULL DEFAULT 0,
            `dpia_date` DATE DEFAULT NULL,
            `dpia_document_id` INT(11) UNSIGNED DEFAULT NULL,
            `responsible_person` VARCHAR(255) DEFAULT NULL,
            `department` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('draft', 'pending_review', 'approved', 'archived') NOT NULL DEFAULT 'draft',
            `review_date` DATE DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_by` INT(11) UNSIGNED DEFAULT NULL,
            `updated_by` INT(11) UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`),
            KEY `idx_lawful_basis` (`lawful_basis`),
            KEY `idx_dpia_required` (`dpia_required`),
            KEY `idx_review_date` (`review_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        DB::statement($sql);

        return 'created';
    }

    /**
     * DSAR Request Table
     */
    private static function createDsarRequestTable(): string
    {
        $tableName = 'privacy_dsar_request';

        if (self::tableExists($tableName)) {
            return 'exists';
        }

        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference_number` VARCHAR(50) NOT NULL COMMENT 'Auto-generated DSAR-YYYYMM-XXXX',
            `request_type` VARCHAR(50) NOT NULL COMMENT 'access, rectification, erasure, restriction, portability, objection',
            `subject_name` VARCHAR(255) NOT NULL,
            `subject_email` VARCHAR(255) DEFAULT NULL,
            `subject_phone` VARCHAR(50) DEFAULT NULL,
            `subject_id_number` VARCHAR(50) DEFAULT NULL COMMENT 'ID/Passport number for verification',
            `subject_address` TEXT DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `identity_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `identity_verified_date` DATETIME DEFAULT NULL,
            `identity_verified_by` INT(11) UNSIGNED DEFAULT NULL,
            `status` ENUM('pending', 'in_progress', 'awaiting_info', 'completed', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
            `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
            `assigned_to` INT(11) UNSIGNED DEFAULT NULL,
            `deadline` DATE NOT NULL COMMENT '30 days from request per POPIA',
            `extended_deadline` DATE DEFAULT NULL COMMENT 'If extension granted',
            `extension_reason` TEXT DEFAULT NULL,
            `response_method` VARCHAR(50) DEFAULT NULL COMMENT 'email, post, collect',
            `response_notes` TEXT DEFAULT NULL,
            `completed_at` DATETIME DEFAULT NULL,
            `rejection_reason` TEXT DEFAULT NULL,
            `created_by` INT(11) UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_reference_number` (`reference_number`),
            KEY `idx_status` (`status`),
            KEY `idx_request_type` (`request_type`),
            KEY `idx_deadline` (`deadline`),
            KEY `idx_assigned_to` (`assigned_to`),
            KEY `idx_subject_email` (`subject_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        DB::statement($sql);

        return 'created';
    }

    /**
     * DSAR Activity Log Table
     */
    private static function createDsarLogTable(): string
    {
        $tableName = 'privacy_dsar_log';

        if (self::tableExists($tableName)) {
            return 'exists';
        }

        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `dsar_id` INT(11) UNSIGNED NOT NULL,
            `action` VARCHAR(100) NOT NULL COMMENT 'status_change, note_added, document_attached, extension_granted, etc.',
            `previous_status` VARCHAR(50) DEFAULT NULL,
            `new_status` VARCHAR(50) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `attachments` TEXT DEFAULT NULL COMMENT 'JSON array of file references',
            `user_id` INT(11) UNSIGNED DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_dsar_id` (`dsar_id`),
            KEY `idx_action` (`action`),
            KEY `idx_created_at` (`created_at`),
            CONSTRAINT `fk_dsar_log_request` FOREIGN KEY (`dsar_id`) 
                REFERENCES `privacy_dsar_request` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        DB::statement($sql);

        return 'created';
    }

    /**
     * Breach Incident Table
     */
    private static function createBreachIncidentTable(): string
    {
        $tableName = 'privacy_breach_incident';

        if (self::tableExists($tableName)) {
            return 'exists';
        }

        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference_number` VARCHAR(50) NOT NULL COMMENT 'Auto-generated BRE-YYYY-XXXX',
            `incident_date` DATE NOT NULL,
            `discovered_date` DATE NOT NULL,
            `description` TEXT NOT NULL,
            `data_types_affected` TEXT DEFAULT NULL COMMENT 'Types of personal data affected',
            `subjects_affected_count` INT(11) DEFAULT NULL COMMENT 'Approximate number of data subjects',
            `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
            `risk_to_rights` TEXT DEFAULT NULL COMMENT 'Risk to rights and freedoms',
            `root_cause` TEXT DEFAULT NULL,
            `containment_actions` TEXT DEFAULT NULL,
            `notification_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether regulator notification is required',
            `regulator_notified` TINYINT(1) NOT NULL DEFAULT 0,
            `regulator_notification_date` DATETIME DEFAULT NULL,
            `regulator_reference` VARCHAR(100) DEFAULT NULL,
            `subjects_notified` TINYINT(1) NOT NULL DEFAULT 0,
            `subjects_notification_date` DATE DEFAULT NULL,
            `subjects_notification_method` VARCHAR(100) DEFAULT NULL,
            `remediation_actions` TEXT DEFAULT NULL,
            `lessons_learned` TEXT DEFAULT NULL,
            `status` ENUM('open', 'investigating', 'contained', 'closed') NOT NULL DEFAULT 'open',
            `closed_date` DATE DEFAULT NULL,
            `reported_by` INT(11) UNSIGNED DEFAULT NULL,
            `assigned_to` INT(11) UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_reference_number` (`reference_number`),
            KEY `idx_status` (`status`),
            KEY `idx_severity` (`severity`),
            KEY `idx_incident_date` (`incident_date`),
            KEY `idx_regulator_notified` (`regulator_notified`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        DB::statement($sql);

        return 'created';
    }

    /**
     * Privacy Template Table
     */
    private static function createPrivacyTemplateTable(): string
    {
        $tableName = 'privacy_template';

        if (self::tableExists($tableName)) {
            return 'exists';
        }

        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `code` VARCHAR(50) NOT NULL,
            `category` VARCHAR(50) NOT NULL COMMENT 'privacy_notice, paia_manual, dpia, consent_form, breach_notification, dsar_response, retention_schedule, processing_agreement',
            `description` TEXT DEFAULT NULL,
            `content` LONGTEXT NOT NULL,
            `variables` TEXT DEFAULT NULL COMMENT 'JSON array of variable placeholders',
            `language` VARCHAR(10) NOT NULL DEFAULT 'en',
            `version` VARCHAR(20) DEFAULT '1.0',
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT(11) UNSIGNED DEFAULT NULL,
            `updated_by` INT(11) UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_code_language` (`code`, `language`),
            KEY `idx_category` (`category`),
            KEY `idx_active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        DB::statement($sql);

        return 'created';
    }

    /**
     * Consent Record Table
     */
    private static function createConsentRecordTable(): string
    {
        $tableName = 'privacy_consent_record';

        if (self::tableExists($tableName)) {
            return 'exists';
        }

        $sql = "CREATE TABLE `{$tableName}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `subject_identifier` VARCHAR(255) NOT NULL COMMENT 'Email or ID used to identify subject',
            `processing_activity_id` INT(11) UNSIGNED DEFAULT NULL,
            `consent_type` VARCHAR(100) NOT NULL COMMENT 'marketing, analytics, third_party, research, etc.',
            `consent_text` TEXT DEFAULT NULL COMMENT 'Exact text shown to user',
            `consent_given` TINYINT(1) NOT NULL DEFAULT 1,
            `consent_date` DATETIME NOT NULL,
            `consent_method` VARCHAR(50) DEFAULT NULL COMMENT 'web_form, paper, verbal, email',
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `withdrawn` TINYINT(1) NOT NULL DEFAULT 0,
            `withdrawn_date` DATETIME DEFAULT NULL,
            `withdrawn_reason` TEXT DEFAULT NULL,
            `proof_document_id` INT(11) UNSIGNED DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_subject` (`subject_identifier`),
            KEY `idx_consent_type` (`consent_type`),
            KEY `idx_consent_date` (`consent_date`),
            KEY `idx_withdrawn` (`withdrawn`),
            KEY `idx_processing_activity` (`processing_activity_id`),
            CONSTRAINT `fk_consent_processing` FOREIGN KEY (`processing_activity_id`) 
                REFERENCES `privacy_processing_activity` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        DB::statement($sql);

        return 'created';
    }

    /**
     * Check if table exists
     */
    private static function tableExists(string $tableName): bool
    {
        try {
            $result = DB::select("SHOW TABLES LIKE '{$tableName}'");

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Drop all privacy tables (for testing/reset)
     */
    public static function rollback(): array
    {
        $tables = [
            'privacy_consent_record',
            'privacy_dsar_log',
            'privacy_dsar_request',
            'privacy_breach_incident',
            'privacy_template',
            'privacy_processing_activity',
        ];

        $results = [];

        foreach ($tables as $table) {
            try {
                DB::statement("DROP TABLE IF EXISTS `{$table}`");
                $results[$table] = 'dropped';
            } catch (\Exception $e) {
                $results[$table] = 'error: ' . $e->getMessage();
            }
        }

        return $results;
    }
}
