-- Encryption support tables for AtoM Heratio
-- Issue 145: Encryption Layers

CREATE TABLE IF NOT EXISTS `ahg_encrypted_fields` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `table_name` VARCHAR(100) NOT NULL,
    `column_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `is_encrypted` TINYINT(1) DEFAULT 0,
    `encrypted_at` DATETIME NULL,
    `algorithm` VARCHAR(50) DEFAULT 'aes-256-gcm',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_table_column` (`table_name`, `column_name`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ahg_encryption_audit` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` ENUM('encrypt','decrypt','rotate','migrate') NOT NULL,
    `target_type` ENUM('file','field') NOT NULL,
    `target_id` VARCHAR(255) DEFAULT NULL,
    `target_table` VARCHAR(100) DEFAULT NULL,
    `target_column` VARCHAR(100) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `status` ENUM('success','failure') DEFAULT 'success',
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_action` (`action`),
    KEY `idx_target` (`target_type`, `target_id`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
