-- Add zip_path column if not exists
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'backup_history' AND COLUMN_NAME = 'zip_path');
SET @query := IF(@exist = 0, 'ALTER TABLE backup_history ADD COLUMN zip_path VARCHAR(500) NULL AFTER size_bytes', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add started_at column if not exists
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'backup_history' AND COLUMN_NAME = 'started_at');
SET @query := IF(@exist = 0, 'ALTER TABLE backup_history ADD COLUMN started_at TIMESTAMP NULL AFTER status', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update backup_type to VARCHAR
ALTER TABLE backup_history MODIFY COLUMN backup_type VARCHAR(50) DEFAULT 'manual';
