-- Migration: 004_add_protection_level
-- Add protection level column to atom_extension
-- Uses procedure to safely add column if not exists

DROP PROCEDURE IF EXISTS add_protection_level_column;

DELIMITER //
CREATE PROCEDURE add_protection_level_column()
BEGIN
    DECLARE col_count INT;
    SELECT COUNT(*) INTO col_count FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atom_extension' AND COLUMN_NAME = 'protection_level';
    
    IF col_count = 0 THEN
        ALTER TABLE atom_extension ADD COLUMN protection_level ENUM('core','system','theme','extension') DEFAULT 'extension' AFTER status;
    END IF;
END //
DELIMITER ;

CALL add_protection_level_column();
DROP PROCEDURE IF EXISTS add_protection_level_column;
