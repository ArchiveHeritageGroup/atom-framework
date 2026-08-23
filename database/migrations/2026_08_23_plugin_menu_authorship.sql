-- Migration: record whether a plugin CREATED a menu row or merely adopted one
-- Date: 2026-08-23
--
-- Why: syncMenuEntries() adopts a pre-existing menu row whose name and path match
-- a manifest entry, so the row is not duplicated on re-enable. It recorded that
-- adoption as ownership, and releaseMenuRow() deletes what a plugin owns. A
-- manifest entry matching a row base AtoM shipped therefore made the next disable
-- delete base navigation: 42 rows went on one instance - the whole browse and add
-- menus, import, admin, the user and group ACL entries - recoverable only from the
-- binary log.
--
-- Existing rows default to 0 (not created by a plugin). That is deliberate: their
-- authorship is genuinely unknown, and declining to delete leaves at worst a stray
-- entry, whereas guessing the other way repeats the outage.

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'atom_plugin_menu'
    AND COLUMN_NAME = 'created_by_plugin'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE atom_plugin_menu ADD COLUMN created_by_plugin TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = this plugin created the row and may delete it; 0 = adopted, never delete'' AFTER menu_path',
    'SELECT "Column created_by_plugin already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
