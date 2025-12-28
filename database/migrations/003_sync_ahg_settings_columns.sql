-- Migration: 003_sync_ahg_settings_columns
-- Ensures ahg_settings has all required columns (for upgrades)

-- Add setting_type if missing
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'setting_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN setting_type VARCHAR(20) DEFAULT ''string'' AFTER setting_value', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add description if missing
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'description');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN description VARCHAR(500) NULL AFTER setting_group', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add is_sensitive if missing
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'is_sensitive');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN is_sensitive TINYINT(1) DEFAULT 0 AFTER description', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add updated_by if missing
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'updated_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN updated_by INT NULL AFTER is_sensitive', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
