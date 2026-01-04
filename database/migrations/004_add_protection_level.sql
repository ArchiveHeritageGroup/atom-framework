-- Migration: 004_add_protection_level
-- Add protection level column to atom_extension (safe - checks first)

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'atom_extension' 
    AND COLUMN_NAME = 'protection_level'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE atom_extension ADD COLUMN protection_level ENUM(''core'',''system'',''theme'',''extension'') DEFAULT ''extension'' AFTER status',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
