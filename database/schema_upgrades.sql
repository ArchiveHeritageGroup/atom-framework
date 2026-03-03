-- =============================================================================
-- Schema Upgrades — Safe ALTER statements for existing deployments
-- Run by bin/install Step 1b. All statements are idempotent.
-- =============================================================================

-- Helper procedure: add column if it doesn't exist
DELIMITER //
DROP PROCEDURE IF EXISTS add_column_if_missing//
CREATE PROCEDURE add_column_if_missing(
    IN tbl VARCHAR(100),
    IN col VARCHAR(100),
    IN col_def VARCHAR(500)
)
BEGIN
    SET @db = DATABASE();
    SELECT COUNT(*) INTO @exists
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = tbl AND column_name = col;

    IF @exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- ─── ahg_dropdown: add taxonomy_section ──────────────────────────────
CALL add_column_if_missing('ahg_dropdown', 'taxonomy_section',
    "VARCHAR(50) NULL COMMENT 'UI section grouping' AFTER `is_active`");

-- Add index on taxonomy_section if not exists
SET @db = DATABASE();
SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @db AND table_name = 'ahg_dropdown' AND index_name = 'idx_section';

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `ahg_dropdown` ADD INDEX `idx_section` (`taxonomy_section`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cleanup
DROP PROCEDURE IF EXISTS add_column_if_missing;
