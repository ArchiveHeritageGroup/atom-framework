-- Display Mode Switching - User Preferences
-- AtoM 2.10 Laravel Rebuild

-- Check if table exists and create if not
CREATE TABLE IF NOT EXISTS user_display_preference (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module VARCHAR(100) NOT NULL COMMENT 'Module context: informationobject, actor, repository, etc.',
    display_mode VARCHAR(50) NOT NULL DEFAULT 'list' COMMENT 'tree, grid, gallery, list, timeline',
    items_per_page INT DEFAULT 30,
    sort_field VARCHAR(100) DEFAULT 'updated_at',
    sort_direction ENUM('asc', 'desc') DEFAULT 'desc',
    show_thumbnails TINYINT(1) DEFAULT 1,
    show_descriptions TINYINT(1) DEFAULT 1,
    card_size ENUM('small', 'medium', 'large') DEFAULT 'medium',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_user_module (user_id, module),
    INDEX idx_user_id (user_id),
    INDEX idx_module (module),
    
    CONSTRAINT fk_udp_user FOREIGN KEY (user_id) 
        REFERENCES user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default preferences for anonymous users (user_id = 0)
INSERT IGNORE INTO user_display_preference (user_id, module, display_mode, items_per_page) VALUES
(0, 'informationobject', 'list', 30),
(0, 'actor', 'list', 30),
(0, 'repository', 'grid', 20),
(0, 'digitalobject', 'grid', 24),
(0, 'library', 'list', 30),
(0, 'gallery', 'gallery', 12),
(0, 'dam', 'grid', 24),
(0, 'search', 'list', 30);
