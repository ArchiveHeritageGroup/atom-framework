#!/bin/bash
# Schema Sync Script - ensures all tables have correct columns

# Load database config
source /usr/share/nginx/archive/config/config.php 2>/dev/null || {
    DB_HOST="localhost"
    DB_NAME="archive"
    DB_USER="root"
}

echo "Syncing database schema..."

mysql -h"${DB_HOST:-localhost}" -u"${DB_USER:-root}" -p "${DB_NAME:-archive}" << 'SQL'

-- ahg_settings: ensure all columns exist
-- Check and add setting_type
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'setting_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN setting_type VARCHAR(20) DEFAULT ''string'' AFTER setting_value', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add description
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'description');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN description TEXT NULL AFTER setting_group', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add is_sensitive
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'is_sensitive');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN is_sensitive TINYINT(1) DEFAULT 0 AFTER description', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add updated_by
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'ahg_settings' AND column_name = 'updated_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ahg_settings ADD COLUMN updated_by INT NULL AFTER is_sensitive', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Schema sync complete' AS status;
SQL

echo "Done."
