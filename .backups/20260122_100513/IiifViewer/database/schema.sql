-- =====================================================
-- IIIF Viewer Framework Database Schema
-- Includes annotations, OCR, collections, and settings
-- =====================================================

-- =====================================================
-- IIIF Collections
-- =====================================================

CREATE TABLE IF NOT EXISTS iiif_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    thumbnail_url VARCHAR(500),
    view_type ENUM('grid', 'list', 'carousel', 'continuous') DEFAULT 'grid',
    is_public TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_public (is_public),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iiif_collection_i18n (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    culture VARCHAR(10) NOT NULL DEFAULT 'en',
    title VARCHAR(500),
    description TEXT,
    FOREIGN KEY (collection_id) REFERENCES iiif_collection(id) ON DELETE CASCADE,
    UNIQUE KEY unique_collection_culture (collection_id, culture)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iiif_collection_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    object_id INT NOT NULL,
    caption TEXT,
    display_order INT DEFAULT 0,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collection_id) REFERENCES iiif_collection(id) ON DELETE CASCADE,
    INDEX idx_collection (collection_id),
    INDEX idx_object (object_id),
    UNIQUE KEY unique_collection_object (collection_id, object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- IIIF Annotations
-- =====================================================

CREATE TABLE IF NOT EXISTS iiif_annotation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    canvas_id INT,
    target_canvas VARCHAR(500) NOT NULL,
    target_selector JSON,
    motivation ENUM('commenting', 'tagging', 'describing', 'linking', 'transcribing', 'identifying', 'supplementing') DEFAULT 'commenting',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_object (object_id),
    INDEX idx_canvas (target_canvas(255)),
    INDEX idx_motivation (motivation),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iiif_annotation_body (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annotation_id INT NOT NULL,
    body_type VARCHAR(50) DEFAULT 'TextualBody',
    body_value TEXT,
    body_format VARCHAR(50) DEFAULT 'text/plain',
    body_language VARCHAR(10) DEFAULT 'en',
    body_purpose VARCHAR(50),
    FOREIGN KEY (annotation_id) REFERENCES iiif_annotation(id) ON DELETE CASCADE,
    INDEX idx_annotation (annotation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- IIIF OCR Text
-- =====================================================

CREATE TABLE IF NOT EXISTS iiif_ocr_text (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    object_id INT NOT NULL,
    full_text LONGTEXT,
    format ENUM('plain', 'alto', 'hocr') DEFAULT 'plain',
    language VARCHAR(10) DEFAULT 'en',
    confidence DECIMAL(5,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_digital_object (digital_object_id),
    INDEX idx_object (object_id),
    FULLTEXT INDEX ft_text (full_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS iiif_ocr_block (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ocr_id INT NOT NULL,
    page_number INT DEFAULT 1,
    block_type ENUM('word', 'line', 'paragraph', 'region') DEFAULT 'word',
    text VARCHAR(1000),
    x INT NOT NULL,
    y INT NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    confidence DECIMAL(5,2),
    block_order INT DEFAULT 0,
    FOREIGN KEY (ocr_id) REFERENCES iiif_ocr_text(id) ON DELETE CASCADE,
    INDEX idx_ocr (ocr_id),
    INDEX idx_page (page_number),
    INDEX idx_type (block_type),
    INDEX idx_text (text(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- IIIF 3D Models (OPTIONAL - for advanced settings only)
-- By default, 3D models are detected from digital_object table
-- These tables provide additional settings if needed
-- =====================================================

-- Optional: Advanced 3D model settings
-- Only needed if you want per-model camera, AR, or hotspot settings
-- Otherwise, 3D models are automatically detected from digital_object by file extension
CREATE TABLE IF NOT EXISTS object_3d_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL UNIQUE,  -- References digital_object.id
    
    -- Viewer settings
    auto_rotate TINYINT(1) DEFAULT 1,
    rotation_speed DECIMAL(3,2) DEFAULT 1.00,
    camera_orbit VARCHAR(100) DEFAULT '0deg 75deg 105%',
    field_of_view VARCHAR(20) DEFAULT '30deg',
    exposure DECIMAL(3,2) DEFAULT 1.00,
    shadow_intensity DECIMAL(3,2) DEFAULT 1.00,
    background_color VARCHAR(20) DEFAULT '#f5f5f5',
    
    -- AR settings
    ar_enabled TINYINT(1) DEFAULT 1,
    ar_scale VARCHAR(20) DEFAULT 'auto',
    ar_placement ENUM('floor', 'wall') DEFAULT 'floor',
    
    -- Custom poster/thumbnail (optional, otherwise auto-generated)
    poster_image VARCHAR(500),
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_digital_object (digital_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: 3D Model Hotspots (for annotations on 3D models)
CREATE TABLE IF NOT EXISTS object_3d_hotspot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,  -- References digital_object.id
    hotspot_type ENUM('annotation', 'info', 'link', 'damage', 'detail') DEFAULT 'annotation',
    position_x DECIMAL(10,6) NOT NULL,
    position_y DECIMAL(10,6) NOT NULL,
    position_z DECIMAL(10,6) NOT NULL,
    normal_x DECIMAL(10,6) DEFAULT 0,
    normal_y DECIMAL(10,6) DEFAULT 1,
    normal_z DECIMAL(10,6) DEFAULT 0,
    icon VARCHAR(50),
    color VARCHAR(20),
    link_url VARCHAR(500),
    link_target VARCHAR(20) DEFAULT '_blank',
    display_order INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    INDEX idx_digital_object (digital_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS object_3d_hotspot_i18n (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotspot_id INT NOT NULL,
    culture VARCHAR(10) NOT NULL DEFAULT 'en',
    title VARCHAR(255),
    description TEXT,
    FOREIGN KEY (hotspot_id) REFERENCES object_3d_hotspot(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hotspot_culture (hotspot_id, culture)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- IIIF Viewer Settings
-- =====================================================

CREATE TABLE IF NOT EXISTS iiif_viewer_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(500),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO iiif_viewer_settings (setting_key, setting_value, setting_type, description) VALUES
('default_viewer', 'openseadragon', 'string', 'Default viewer type'),
('enable_annotations', '1', 'boolean', 'Enable annotation support'),
('enable_ocr_overlay', '1', 'boolean', 'Enable OCR text overlay'),
('enable_3d_ar', '1', 'boolean', 'Enable AR for 3D models'),
('enable_download', '0', 'boolean', 'Enable download button'),
('viewer_height', '600px', 'string', 'Default viewer height'),
('cantaloupe_url', 'https://archives.theahg.co.za/iiif/2', 'string', 'Cantaloupe IIIF server URL'),
('base_url', 'https://archives.theahg.co.za', 'string', 'Base URL for manifests'),
('attribution', 'The Archive and Heritage Group', 'string', 'Attribution text'),
('license', 'https://creativecommons.org/licenses/by-nc-sa/4.0/', 'string', 'Default license URL'),
('osd_config', '{"showNavigator":true,"showRotationControl":true}', 'json', 'OpenSeadragon configuration'),
('mirador_config', '{"sideBarOpenByDefault":false}', 'json', 'Mirador configuration')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================
-- Audit Log
-- =====================================================

CREATE TABLE IF NOT EXISTS iiif_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT,
    action VARCHAR(50) NOT NULL,
    action_category VARCHAR(50),
    details JSON,
    user_id INT,
    user_name VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_object (object_id),
    INDEX idx_action (action),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
