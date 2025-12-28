-- Migration: 004_add_protection_level
-- Add protection level column to atom_extension

SET @dbname = DATABASE();
SET @tablename = 'atom_extension';
SET @columnname = 'protection_level';

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE atom_extension ADD COLUMN protection_level ENUM(''core'',''system'',''theme'',''extension'') DEFAULT ''extension'' AFTER status'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
