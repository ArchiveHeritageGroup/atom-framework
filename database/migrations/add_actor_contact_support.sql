-- Analysis: contact_information table already links to actor via actor_id
-- Authority records ARE actors in AtoM's data model
-- We just need to ensure the UI exposes this for non-repository actors

-- Check existing structure (reference only)
-- SELECT * FROM contact_information WHERE actor_id IN 
--   (SELECT id FROM actor WHERE id NOT IN (SELECT actor_id FROM repository));

-- Add a note field for contact context if not exists
SET @dbname = DATABASE();
SET @tablename = 'contact_information';
SET @columnname = 'contact_note';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE contact_information ADD COLUMN contact_note TEXT NULL AFTER note'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add contact_type for categorization (primary, secondary, historical, etc.)
SET @columnname = 'contact_type';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE contact_information ADD COLUMN contact_type VARCHAR(50) DEFAULT "primary" AFTER actor_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add valid_from and valid_to for historical contact tracking
SET @columnname = 'valid_from';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE contact_information ADD COLUMN valid_from DATE NULL AFTER contact_type'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'valid_to';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE contact_information ADD COLUMN valid_to DATE NULL AFTER valid_from'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
