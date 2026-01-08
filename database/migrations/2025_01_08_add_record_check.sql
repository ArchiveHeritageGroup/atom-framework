-- Migration: Add record check query column to atom_plugin
-- Date: 2025-01-08
-- Purpose: Store SQL queries to check if plugin has associated records

-- Check if column exists before adding
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'atom_plugin' 
    AND COLUMN_NAME = 'record_check_query'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE atom_plugin ADD COLUMN record_check_query TEXT NULL AFTER settings',
    'SELECT "Column record_check_query already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add record check queries for existing plugins
UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_library_item' 
WHERE name = 'ahgLibraryPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_audit_log' 
WHERE name = 'ahgAuditTrailPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_research_request' 
WHERE name = 'ahgResearchPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_backup' 
WHERE name = 'ahgBackupPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_security_clearance' 
WHERE name = 'ahgSecurityClearancePlugin' AND record_check_query IS NULL;
