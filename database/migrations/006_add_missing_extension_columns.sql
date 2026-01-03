-- Migration: 006_add_missing_extension_columns
-- Adds missing columns to atom_extension table for existing installs

-- Add license column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'license');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN license VARCHAR(50) DEFAULT ''GPL-3.0'' AFTER author', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add requires_atom column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'requires_atom');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN requires_atom VARCHAR(20) AFTER requires_framework', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add requires_php column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'requires_php');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN requires_php VARCHAR(20) AFTER requires_atom', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add optional_dependencies column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'optional_dependencies');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN optional_dependencies JSON AFTER dependencies', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add helpers column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'helpers');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN helpers JSON AFTER shared_tables', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add install_task column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'install_task');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN install_task VARCHAR(100) AFTER helpers', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add uninstall_task column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'uninstall_task');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN uninstall_task VARCHAR(100) AFTER install_task', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add config_path column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'config_path');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN config_path VARCHAR(500) AFTER uninstall_task', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add disabled_at column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension' AND column_name = 'disabled_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension ADD COLUMN disabled_at DATETIME AFTER enabled_at', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add setting_group to atom_extension_setting
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'atom_extension_setting' AND column_name = 'setting_group');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_extension_setting ADD COLUMN setting_group VARCHAR(100) DEFAULT ''general'' AFTER setting_type', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
