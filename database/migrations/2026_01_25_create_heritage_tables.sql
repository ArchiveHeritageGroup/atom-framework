-- Migration: Create Heritage Platform Tables
-- Date: 2026-01-25
-- Description: Foundation tables for Heritage discovery platform - landing page config, filters, stories, hero images

-- ============================================================================
-- Table: heritage_landing_config
-- Institution landing page configuration
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_landing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Hero section
    hero_tagline VARCHAR(500) DEFAULT 'Discover our collections',
    hero_subtext VARCHAR(500) DEFAULT NULL,
    hero_search_placeholder VARCHAR(255) DEFAULT 'What are you looking for?',
    suggested_searches JSON DEFAULT NULL,

    -- Hero media
    hero_media JSON DEFAULT NULL,
    hero_rotation_seconds INT DEFAULT 8,
    hero_effect ENUM('kenburns', 'fade', 'none') DEFAULT 'kenburns',

    -- Sections enabled
    show_curated_stories TINYINT(1) DEFAULT 1,
    show_community_activity TINYINT(1) DEFAULT 1,
    show_filters TINYINT(1) DEFAULT 1,
    show_stats TINYINT(1) DEFAULT 1,
    show_recent_additions TINYINT(1) DEFAULT 1,

    -- Stats configuration
    stats_config JSON DEFAULT NULL,

    -- Styling
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_filter_type
-- Available filter types system-wide
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_filter_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    source_type ENUM('taxonomy', 'authority', 'field', 'custom') NOT NULL,
    source_reference VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_institution_filter
-- Institution's filter configuration
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_institution_filter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    filter_type_id INT NOT NULL,

    is_enabled TINYINT(1) DEFAULT 1,
    display_name VARCHAR(100) DEFAULT NULL,
    display_icon VARCHAR(50) DEFAULT NULL,
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    show_in_search TINYINT(1) DEFAULT 1,
    max_items_landing INT DEFAULT 6,

    is_hierarchical TINYINT(1) DEFAULT 0,
    allow_multiple TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_filter_type (filter_type_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),

    CONSTRAINT fk_heritage_inst_filter_type
        FOREIGN KEY (filter_type_id) REFERENCES heritage_filter_type(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_filter_value
-- Custom filter values for non-taxonomy filters
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_filter_value (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_filter_id INT NOT NULL,
    value_code VARCHAR(100) NOT NULL,
    display_label VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 100,
    parent_id INT DEFAULT NULL,
    filter_query JSON DEFAULT NULL,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution_filter (institution_filter_id),
    INDEX idx_parent (parent_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),

    CONSTRAINT fk_heritage_filter_value_inst
        FOREIGN KEY (institution_filter_id) REFERENCES heritage_institution_filter(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_heritage_filter_value_parent
        FOREIGN KEY (parent_id) REFERENCES heritage_filter_value(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_curated_story
-- Featured stories/collections on landing page
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_curated_story (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(500) DEFAULT NULL,
    story_type VARCHAR(50) DEFAULT 'collection',

    link_type ENUM('collection', 'search', 'external', 'page') DEFAULT 'search',
    link_reference VARCHAR(500) DEFAULT NULL,

    item_count INT DEFAULT NULL,

    is_featured TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,

    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_featured (is_featured),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_hero_image
-- Hero images for rotation
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_hero_image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    image_path VARCHAR(500) NOT NULL,
    caption VARCHAR(500) DEFAULT NULL,
    collection_name VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(500) DEFAULT NULL,

    display_order INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_discovery_log
-- Search analytics and logging
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_discovery_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    query_text VARCHAR(500) DEFAULT NULL,
    filters_applied JSON DEFAULT NULL,
    result_count INT DEFAULT 0,

    user_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    search_duration_ms INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_query (query_text(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Seed default filter types
-- ============================================================================
INSERT IGNORE INTO heritage_filter_type (code, name, icon, source_type, source_reference, is_system) VALUES
('content_type', 'Format', 'bi-file-earmark', 'taxonomy', 'contentType', 1),
('time_period', 'Time Period', 'bi-calendar', 'field', 'date', 1),
('place', 'Place', 'bi-geo-alt', 'authority', 'place', 1),
('subject', 'Subject', 'bi-tag', 'taxonomy', 'subject', 1),
('creator', 'Creator', 'bi-person', 'authority', 'actor', 1),
('collection', 'Collection', 'bi-collection', 'field', 'repository', 1),
('language', 'Language', 'bi-translate', 'taxonomy', 'language', 1),
('glam_sector', 'Type', 'bi-building', 'taxonomy', 'glamSector', 1);

-- ============================================================================
-- Seed default landing config (for single-institution deployments)
-- ============================================================================
INSERT IGNORE INTO heritage_landing_config (id, institution_id, hero_tagline, hero_subtext, hero_search_placeholder, suggested_searches, stats_config) VALUES
(1, NULL, 'Discover Our Heritage', 'Explore collections spanning centuries of history, culture, and human achievement', 'Search photographs, documents, artifacts...', '["photographs", "maps", "letters", "newspapers"]', '{"show_items": true, "show_collections": true, "show_contributors": false}');

-- ============================================================================
-- Seed default institution filters (enabled for single-institution)
-- ============================================================================
INSERT IGNORE INTO heritage_institution_filter (institution_id, filter_type_id, is_enabled, display_order, show_on_landing, show_in_search, max_items_landing)
SELECT NULL, id, 1,
    CASE code
        WHEN 'content_type' THEN 10
        WHEN 'time_period' THEN 20
        WHEN 'place' THEN 30
        WHEN 'subject' THEN 40
        WHEN 'creator' THEN 50
        WHEN 'collection' THEN 60
        WHEN 'language' THEN 70
        WHEN 'glam_sector' THEN 80
    END,
    CASE WHEN code IN ('content_type', 'time_period', 'place', 'subject', 'creator', 'collection') THEN 1 ELSE 0 END,
    1,
    6
FROM heritage_filter_type
WHERE is_system = 1;

-- Verification
SELECT 'heritage_landing_config' as tbl, COUNT(*) as cnt FROM heritage_landing_config
UNION ALL SELECT 'heritage_filter_type', COUNT(*) FROM heritage_filter_type
UNION ALL SELECT 'heritage_institution_filter', COUNT(*) FROM heritage_institution_filter
UNION ALL SELECT 'heritage_filter_value', COUNT(*) FROM heritage_filter_value
UNION ALL SELECT 'heritage_curated_story', COUNT(*) FROM heritage_curated_story
UNION ALL SELECT 'heritage_hero_image', COUNT(*) FROM heritage_hero_image
UNION ALL SELECT 'heritage_discovery_log', COUNT(*) FROM heritage_discovery_log;
