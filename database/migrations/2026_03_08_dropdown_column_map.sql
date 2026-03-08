-- ============================================================================
-- Dropdown Column Mapping Table
-- Date: 2026-03-08
-- Links database columns to ahg_dropdown taxonomies
-- Used by DropdownService for validation and label resolution
-- ============================================================================

CREATE TABLE IF NOT EXISTS ahg_dropdown_column_map (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    taxonomy VARCHAR(100) NOT NULL COMMENT 'FK to ahg_dropdown.taxonomy',
    is_strict TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=only dropdown values allowed, 0=freetext also allowed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_table_column (table_name, column_name),
    KEY idx_taxonomy (taxonomy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto-populate from columns that have value-list COMMENTs (formerly ENUM)
INSERT IGNORE INTO ahg_dropdown_column_map (table_name, column_name, taxonomy)
SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS c
JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
WHERE c.TABLE_SCHEMA = DATABASE()
AND c.COLUMN_COMMENT REGEXP '^[a-z0-9_]+(, [a-z0-9_]+)+'
AND c.DATA_TYPE = 'varchar'
AND EXISTS (SELECT 1 FROM ahg_dropdown d WHERE d.taxonomy = c.COLUMN_NAME LIMIT 1)
ORDER BY c.TABLE_NAME, c.COLUMN_NAME;
