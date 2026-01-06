-- =====================================================
-- ahgPrivacyPlugin Update 001
-- Adds missing columns to privacy tables
-- Safe to run multiple times (uses ALTER IGNORE / column checks)
-- =====================================================

-- Add missing columns to privacy_processing_activity
SET @dbname = DATABASE();

-- jurisdiction
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'jurisdiction';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN jurisdiction VARCHAR(30) NOT NULL DEFAULT ''popia'' AFTER id', 
    'SELECT ''jurisdiction exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- description
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'description';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN description TEXT NULL AFTER name', 
    'SELECT ''description exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- third_countries
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'third_countries';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN third_countries JSON NULL AFTER recipients', 
    'SELECT ''third_countries exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- dpia_date
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'dpia_date';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN dpia_date DATE NULL AFTER dpia_completed', 
    'SELECT ''dpia_date exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- owner
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'owner';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN owner VARCHAR(255) NULL AFTER status', 
    'SELECT ''owner exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- next_review_date
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'next_review_date';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN next_review_date DATE NULL AFTER owner', 
    'SELECT ''next_review_date exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- lawful_basis_code
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema = @dbname AND table_name = 'privacy_processing_activity' AND column_name = 'lawful_basis_code';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE privacy_processing_activity ADD COLUMN lawful_basis_code VARCHAR(50) NULL AFTER lawful_basis', 
    'SELECT ''lawful_basis_code exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Privacy tables updated' AS result;
