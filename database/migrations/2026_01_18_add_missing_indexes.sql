-- Migration: Add missing database indexes for better query performance
-- Date: 2026-01-18
-- Purpose: Improve query performance on frequently filtered columns

-- Helper procedure to safely add indexes
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_index_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    DECLARE index_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO index_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = p_table
    AND INDEX_NAME = p_index;

    IF index_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (`', p_column, '`)');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Add indexes for rights tables
CALL add_index_if_not_exists('rights_i18n', 'idx_culture', 'culture');
CALL add_index_if_not_exists('rights_grant_i18n', 'idx_culture', 'culture');

-- Add indexes for research tables
CALL add_index_if_not_exists('research_annotation', 'idx_annotation_type', 'annotation_type');
CALL add_index_if_not_exists('research_booking', 'idx_confirmed_by', 'confirmed_by');

-- Add indexes for privacy tables
CALL add_index_if_not_exists('privacy_dsar', 'idx_jurisdiction', 'jurisdiction');

-- Add indexes for spectrum tables (commonly queried)
CALL add_index_if_not_exists('spectrum_event', 'idx_event_type', 'event_type');
CALL add_index_if_not_exists('spectrum_event', 'idx_created_at', 'created_at');
CALL add_index_if_not_exists('spectrum_condition_check', 'idx_check_date', 'check_date');
CALL add_index_if_not_exists('spectrum_condition_check', 'idx_condition_status', 'condition_status');

-- Clean up procedure
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
