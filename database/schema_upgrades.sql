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

-- ahg_error_log: collapse repeats of the same fault into one counted row.
-- Without these three columns ErrorNotificationService keeps its previous
-- row-per-error behaviour, so this upgrade is additive and safe to skip.
CALL add_column_if_missing('ahg_error_log', 'signature', 'CHAR(32) NULL COMMENT ''md5(file:line:message) - groups repeats of one fault''');
CALL add_column_if_missing('ahg_error_log', 'occurrences', 'INT NOT NULL DEFAULT 1 COMMENT ''times this signature was seen in the window''');
CALL add_column_if_missing('ahg_error_log', 'last_seen_at', 'DATETIME NULL COMMENT ''most recent occurrence; NULL means it happened once, at created_at''');

-- Index the signature: every log write now looks up the most recent row for it.
SET @db = DATABASE();
SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @db AND table_name = 'ahg_error_log' AND index_name = 'idx_signature';

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `ahg_error_log` ADD INDEX `idx_signature` (`signature`, `id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cleanup
DROP PROCEDURE IF EXISTS add_column_if_missing;
