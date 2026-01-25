-- Migration: Create Heritage Enhanced Landing Tables
-- Date: 2026-01-25
-- Description: Rijksstudio-inspired discovery interface with curated collections, timeline, and explore categories

-- =============================================================================
-- Table: heritage_featured_collection
-- Curated collections for showcase on landing page
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_featured_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    curator_note TEXT DEFAULT NULL,

    -- Visual
    cover_image VARCHAR(500) DEFAULT NULL,
    thumbnail_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT NULL,
    text_color VARCHAR(7) DEFAULT '#ffffff',

    -- Link
    link_type ENUM('collection', 'search', 'repository', 'external') DEFAULT 'search',
    link_reference VARCHAR(500) DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    search_query JSON DEFAULT NULL,

    -- Stats (cached)
    item_count INT DEFAULT 0,
    image_count INT DEFAULT 0,

    -- Display
    display_size ENUM('small', 'medium', 'large', 'featured') DEFAULT 'medium',
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Scheduling
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_featured (is_featured),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_link_type (link_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Table: heritage_hero_slide
-- Full-bleed hero carousel slides
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_hero_slide (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    title VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Media
    image_path VARCHAR(500) NOT NULL,
    image_alt VARCHAR(255) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    media_type ENUM('image', 'video') DEFAULT 'image',

    -- Visual effects
    overlay_type ENUM('none', 'gradient', 'solid') DEFAULT 'gradient',
    overlay_color VARCHAR(7) DEFAULT '#000000',
    overlay_opacity DECIMAL(3,2) DEFAULT 0.50,
    text_position ENUM('left', 'center', 'right', 'bottom-left', 'bottom-right') DEFAULT 'left',
    ken_burns TINYINT(1) DEFAULT 1,

    -- Call to action
    cta_text VARCHAR(100) DEFAULT NULL,
    cta_url VARCHAR(500) DEFAULT NULL,
    cta_style ENUM('primary', 'secondary', 'outline', 'light') DEFAULT 'primary',

    -- Attribution
    source_item_id INT DEFAULT NULL,
    source_collection VARCHAR(255) DEFAULT NULL,
    photographer_credit VARCHAR(255) DEFAULT NULL,

    -- Display
    display_order INT DEFAULT 100,
    display_duration INT DEFAULT 8,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Scheduling
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Table: heritage_explore_category
-- Visual browse categories (like "Time", "Place", "People", "Theme")
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_explore_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    tagline VARCHAR(255) DEFAULT NULL,

    -- Visual
    icon VARCHAR(50) DEFAULT 'bi-grid',
    cover_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT '#0d6efd',
    text_color VARCHAR(7) DEFAULT '#ffffff',

    -- Data source
    source_type ENUM('taxonomy', 'authority', 'field', 'facet', 'custom') NOT NULL,
    source_reference VARCHAR(255) DEFAULT NULL,
    taxonomy_id INT DEFAULT NULL,

    -- Display configuration
    display_style ENUM('grid', 'list', 'timeline', 'map', 'carousel') DEFAULT 'grid',
    items_per_page INT DEFAULT 24,
    show_counts TINYINT(1) DEFAULT 1,
    show_thumbnails TINYINT(1) DEFAULT 1,

    -- Landing page display
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    landing_items INT DEFAULT 6,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution_code (institution_id, code),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Table: heritage_timeline_period
-- Time periods for timeline navigation
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_timeline_period (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    name VARCHAR(100) NOT NULL,
    short_name VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Date range
    start_year INT NOT NULL,
    end_year INT DEFAULT NULL,
    circa TINYINT(1) DEFAULT 0,

    -- Visual
    cover_image VARCHAR(500) DEFAULT NULL,
    thumbnail_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT NULL,

    -- Search integration
    search_query JSON DEFAULT NULL,
    date_field VARCHAR(100) DEFAULT 'dates',

    -- Stats (cached)
    item_count INT DEFAULT 0,

    -- Display
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_years (start_year, end_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- Default explore categories
INSERT IGNORE INTO heritage_explore_category (institution_id, code, name, description, tagline, icon, source_type, source_reference, display_style, display_order, show_on_landing) VALUES
(NULL, 'time', 'Time', 'Browse by historical period', 'Journey through time', 'bi-clock-history', 'field', 'dates', 'timeline', 1, 1),
(NULL, 'place', 'Place', 'Browse by location', 'Explore by geography', 'bi-geo-alt', 'authority', 'place', 'map', 2, 1),
(NULL, 'people', 'People', 'Browse by person or creator', 'Discover the people', 'bi-people', 'authority', 'actor', 'grid', 3, 1),
(NULL, 'theme', 'Theme', 'Browse by subject', 'Explore by topic', 'bi-tag', 'taxonomy', 'subject', 'grid', 4, 1),
(NULL, 'format', 'Format', 'Browse by format type', 'Filter by media', 'bi-collection', 'taxonomy', 'contentType', 'grid', 5, 1),
(NULL, 'trending', 'Trending', 'Popular items this week', 'What people are viewing', 'bi-graph-up', 'custom', 'trending', 'carousel', 6, 1);

-- Default timeline periods (South African focused with international context)
INSERT IGNORE INTO heritage_timeline_period (institution_id, name, short_name, start_year, end_year, description, display_order, show_on_landing) VALUES
(NULL, 'Pre-Colonial Era', 'Pre-1652', -10000, 1651, 'San and Khoi peoples, early Iron Age settlements, and African kingdoms before European contact', 1, 1),
(NULL, 'Dutch Colonial Period', '1652-1795', 1652, 1795, 'Dutch East India Company settlement at the Cape, expansion and conflicts', 2, 1),
(NULL, 'British Colonial Era', '1795-1910', 1795, 1910, 'British rule, the Great Trek, mineral discoveries, and Anglo-Boer Wars', 3, 1),
(NULL, 'Union of South Africa', '1910-1948', 1910, 1948, 'Formation of the Union, World Wars, and early segregation policies', 4, 1),
(NULL, 'Apartheid Era', '1948-1994', 1948, 1994, 'Formal apartheid, resistance movements, and the struggle for democracy', 5, 1),
(NULL, 'Democratic Era', '1994-Present', 1994, NULL, 'Post-apartheid South Africa, reconciliation, and nation building', 6, 1);

-- =============================================================================
-- VERIFICATION
-- =============================================================================
SELECT 'Enhanced Landing Tables Created' as status;
SELECT
    (SELECT COUNT(*) FROM heritage_featured_collection) as featured_collections,
    (SELECT COUNT(*) FROM heritage_hero_slide) as hero_slides,
    (SELECT COUNT(*) FROM heritage_explore_category) as explore_categories,
    (SELECT COUNT(*) FROM heritage_timeline_period) as timeline_periods;
