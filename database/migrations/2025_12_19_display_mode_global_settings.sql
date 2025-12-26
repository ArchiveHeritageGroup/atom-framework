-- Display Mode Global Settings
-- Allows admin to configure default display modes per module

-- Global display settings (admin-configurable defaults)
CREATE TABLE IF NOT EXISTS display_mode_global (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(100) NOT NULL COMMENT 'Module: informationobject, actor, repository, etc.',
    display_mode VARCHAR(50) NOT NULL DEFAULT 'list',
    items_per_page INT DEFAULT 30,
    sort_field VARCHAR(100) DEFAULT 'updated_at',
    sort_direction ENUM('asc', 'desc') DEFAULT 'desc',
    show_thumbnails TINYINT(1) DEFAULT 1,
    show_descriptions TINYINT(1) DEFAULT 1,
    card_size ENUM('small', 'medium', 'large') DEFAULT 'medium',
    available_modes JSON COMMENT 'JSON array of enabled modes for this module',
    allow_user_override TINYINT(1) DEFAULT 1 COMMENT 'Allow users to change from default',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_module (module),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default global settings
INSERT INTO display_mode_global 
    (module, display_mode, items_per_page, available_modes, allow_user_override) 
VALUES
    ('informationobject', 'list', 30, '["tree", "grid", "list", "timeline"]', 1),
    ('actor', 'list', 30, '["grid", "list"]', 1),
    ('repository', 'grid', 20, '["grid", "list"]', 1),
    ('digitalobject', 'grid', 24, '["grid", "gallery", "list"]', 1),
    ('library', 'list', 30, '["grid", "list"]', 1),
    ('gallery', 'gallery', 12, '["grid", "gallery", "list"]', 1),
    ('dam', 'grid', 24, '["grid", "gallery", "list"]', 1),
    ('search', 'list', 30, '["grid", "list"]', 1),
    ('accession', 'list', 30, '["grid", "list"]', 1),
    ('function', 'list', 30, '["grid", "list"]', 1),
    ('term', 'list', 50, '["grid", "list"]', 1),
    ('physicalobject', 'list', 30, '["grid", "list"]', 1)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Alter user preferences table to add override tracking
ALTER TABLE user_display_preference 
    ADD COLUMN IF NOT EXISTS is_custom TINYINT(1) DEFAULT 1 
    COMMENT 'True if user explicitly set, false if inherited from global';

-- Add column for user preference source tracking
-- Note: Using separate statement due to MySQL limitations
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'user_display_preference' 
    AND column_name = 'is_custom');
    
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE user_display_preference ADD COLUMN is_custom TINYINT(1) DEFAULT 1', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Audit table for tracking preference changes
CREATE TABLE IF NOT EXISTS display_mode_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    module VARCHAR(100) NOT NULL,
    action ENUM('create', 'update', 'delete', 'reset') NOT NULL,
    old_value JSON,
    new_value JSON,
    scope ENUM('global', 'user') NOT NULL DEFAULT 'user',
    changed_by INT DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_module (module),
    INDEX idx_scope (scope),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
