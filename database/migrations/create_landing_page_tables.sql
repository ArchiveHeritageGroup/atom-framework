-- ============================================================
-- Landing Page Builder Tables
-- AHG Custom Framework - December 2024
-- ============================================================

-- Landing Page Layouts
CREATE TABLE IF NOT EXISTS atom_landing_page (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    culture VARCHAR(10) DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    INDEX idx_slug (slug),
    INDEX idx_default (is_default),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Block Type Definitions
CREATE TABLE IF NOT EXISTS atom_landing_block_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(100) DEFAULT 'bi-square',
    template VARCHAR(255) NOT NULL,
    default_config JSON,
    config_schema JSON,
    is_system TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Block Instances on Pages
CREATE TABLE IF NOT EXISTS atom_landing_block (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    block_type_id INT NOT NULL,
    title VARCHAR(255),
    config JSON,
    css_classes VARCHAR(255),
    container_type ENUM('fluid', 'container', 'container-lg') DEFAULT 'container',
    background_color VARCHAR(50),
    text_color VARCHAR(50),
    padding_top VARCHAR(20) DEFAULT '3',
    padding_bottom VARCHAR(20) DEFAULT '3',
    position INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES atom_landing_page(id) ON DELETE CASCADE,
    FOREIGN KEY (block_type_id) REFERENCES atom_landing_block_type(id) ON DELETE RESTRICT,
    INDEX idx_page_position (page_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Draft/Published Versions
CREATE TABLE IF NOT EXISTS atom_landing_page_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    version_number INT NOT NULL,
    blocks_snapshot JSON NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at DATETIME,
    published_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (page_id) REFERENCES atom_landing_page(id) ON DELETE CASCADE,
    INDEX idx_page_version (page_id, version_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log
CREATE TABLE IF NOT EXISTS atom_landing_page_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT,
    block_id INT,
    action VARCHAR(50) NOT NULL,
    details JSON,
    user_id INT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_page (page_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Block Types
INSERT INTO atom_landing_block_type (machine_name, label, description, icon, template, default_config, config_schema, is_system, sort_order) VALUES
('hero_banner', 'Hero Banner', 'Large hero section with background image, title, and call-to-action', 'bi-card-image', '_block_hero_banner', 
 '{"title": "Welcome to Our Archive", "subtitle": "Discover our collections", "background_image": "", "overlay_opacity": 0.5, "cta_text": "Explore", "cta_url": "/informationobject/browse", "height": "400px", "text_align": "center"}',
 '{"title": {"type": "text", "label": "Title"}, "subtitle": {"type": "text", "label": "Subtitle"}, "background_image": {"type": "image", "label": "Background Image"}, "overlay_opacity": {"type": "range", "label": "Overlay Opacity", "min": 0, "max": 1, "step": 0.1}, "cta_text": {"type": "text", "label": "Button Text"}, "cta_url": {"type": "text", "label": "Button URL"}, "height": {"type": "select", "label": "Height", "options": ["300px", "400px", "500px", "100vh"]}, "text_align": {"type": "select", "label": "Text Align", "options": ["left", "center", "right"]}}',
 1, 1),

('search_box', 'Search Box', 'Global search input with optional advanced search link', 'bi-search', '_block_search_box',
 '{"placeholder": "Search the archive...", "show_advanced": true, "style": "default"}',
 '{"placeholder": {"type": "text", "label": "Placeholder Text"}, "show_advanced": {"type": "checkbox", "label": "Show Advanced Search Link"}, "style": {"type": "select", "label": "Style", "options": ["default", "large", "minimal"]}}',
 1, 2),

('browse_panels', 'Browse Panels', 'Grid of browse entry points (repositories, subjects, etc.)', 'bi-grid-3x3-gap', '_block_browse_panels',
 '{"panels": [{"title": "Archival Descriptions", "icon": "bi-archive", "url": "/informationobject/browse", "count_entity": "informationobject"}, {"title": "Repositories", "icon": "bi-building", "url": "/repository/browse", "count_entity": "repository"}, {"title": "Subjects", "icon": "bi-tags", "url": "/term/browse?taxonomy=subjects", "count_entity": "term_subjects"}, {"title": "Digital Objects", "icon": "bi-image", "url": "/digitalobject/browse", "count_entity": "digitalobject"}], "columns": 4, "show_counts": true}',
 '{"panels": {"type": "repeater", "label": "Panels", "fields": {"title": {"type": "text"}, "icon": {"type": "icon"}, "url": {"type": "text"}, "count_entity": {"type": "select", "options": ["informationobject", "repository", "actor", "term_subjects", "term_places", "digitalobject", "accession", "function"]}}}, "columns": {"type": "select", "label": "Columns", "options": [2, 3, 4, 6]}, "show_counts": {"type": "checkbox", "label": "Show Record Counts"}}',
 1, 3),

('recent_items', 'Recent Items', 'Display recently added or updated items', 'bi-clock-history', '_block_recent_items',
 '{"title": "Recent Additions", "entity_type": "informationobject", "limit": 6, "show_date": true, "show_thumbnail": true, "layout": "grid", "columns": 3}',
 '{"title": {"type": "text", "label": "Section Title"}, "entity_type": {"type": "select", "label": "Entity Type", "options": ["informationobject", "repository", "actor", "accession"]}, "limit": {"type": "number", "label": "Number of Items", "min": 1, "max": 20}, "show_date": {"type": "checkbox", "label": "Show Date"}, "show_thumbnail": {"type": "checkbox", "label": "Show Thumbnail"}, "layout": {"type": "select", "label": "Layout", "options": ["grid", "list", "carousel"]}, "columns": {"type": "select", "label": "Columns", "options": [2, 3, 4, 6]}}',
 1, 4),

('featured_items', 'Featured Items', 'Manually curated featured items carousel or grid', 'bi-star', '_block_featured_items',
 '{"title": "Featured Collections", "items": [], "layout": "carousel", "auto_rotate": true, "interval": 5000}',
 '{"title": {"type": "text", "label": "Section Title"}, "items": {"type": "entity_picker", "label": "Select Items", "entity_types": ["informationobject", "repository", "actor"]}, "layout": {"type": "select", "label": "Layout", "options": ["carousel", "grid"]}, "auto_rotate": {"type": "checkbox", "label": "Auto Rotate"}, "interval": {"type": "number", "label": "Rotation Interval (ms)"}}',
 1, 5),

('statistics', 'Statistics', 'Display archive statistics with counters', 'bi-bar-chart', '_block_statistics',
 '{"title": "Our Collections", "stats": [{"label": "Archival Descriptions", "entity": "informationobject", "icon": "bi-archive"}, {"label": "Repositories", "entity": "repository", "icon": "bi-building"}, {"label": "Digital Objects", "entity": "digitalobject", "icon": "bi-image"}], "layout": "horizontal", "animate_numbers": true}',
 '{"title": {"type": "text", "label": "Section Title"}, "stats": {"type": "repeater", "label": "Statistics", "fields": {"label": {"type": "text"}, "entity": {"type": "select", "options": ["informationobject", "repository", "actor", "digitalobject", "accession", "function", "term"]}, "icon": {"type": "icon"}}}, "layout": {"type": "select", "label": "Layout", "options": ["horizontal", "vertical"]}, "animate_numbers": {"type": "checkbox", "label": "Animate Numbers"}}',
 1, 6),

('text_content', 'Text Content', 'Rich text content block with optional image', 'bi-file-text', '_block_text_content',
 '{"title": "", "content": "", "image": "", "image_position": "none", "image_width": "33%"}',
 '{"title": {"type": "text", "label": "Title (optional)"}, "content": {"type": "richtext", "label": "Content"}, "image": {"type": "image", "label": "Image (optional)"}, "image_position": {"type": "select", "label": "Image Position", "options": ["none", "left", "right", "top", "bottom"]}, "image_width": {"type": "select", "label": "Image Width", "options": ["25%", "33%", "50%"]}}',
 1, 7),

('holdings_list', 'Holdings List', 'List of top-level holdings/fonds', 'bi-collection', '_block_holdings_list',
 '{"title": "Our Holdings", "repository_id": null, "limit": 10, "show_level": true, "show_dates": true, "show_extent": false}',
 '{"title": {"type": "text", "label": "Section Title"}, "repository_id": {"type": "entity_picker", "label": "Filter by Repository", "entity_types": ["repository"]}, "limit": {"type": "number", "label": "Number of Items"}, "show_level": {"type": "checkbox", "label": "Show Level of Description"}, "show_dates": {"type": "checkbox", "label": "Show Dates"}, "show_extent": {"type": "checkbox", "label": "Show Extent"}}',
 1, 8),

('image_carousel', 'Image Carousel', 'Rotating image carousel with captions', 'bi-images', '_block_image_carousel',
 '{"title": "", "images": [], "height": "400px", "show_indicators": true, "show_controls": true, "auto_play": true, "interval": 5000}',
 '{"title": {"type": "text", "label": "Title (optional)"}, "images": {"type": "gallery", "label": "Images"}, "height": {"type": "select", "label": "Height", "options": ["300px", "400px", "500px"]}, "show_indicators": {"type": "checkbox", "label": "Show Indicators"}, "show_controls": {"type": "checkbox", "label": "Show Controls"}, "auto_play": {"type": "checkbox", "label": "Auto Play"}, "interval": {"type": "number", "label": "Interval (ms)"}}',
 1, 9),

('quick_links', 'Quick Links', 'Grid or list of custom links', 'bi-link-45deg', '_block_quick_links',
 '{"title": "Quick Links", "links": [{"label": "Advanced Search", "url": "/search/advanced", "icon": "bi-search"}, {"label": "About Us", "url": "/staticpage/about", "icon": "bi-info-circle"}], "layout": "inline", "style": "buttons"}',
 '{"title": {"type": "text", "label": "Section Title"}, "links": {"type": "repeater", "label": "Links", "fields": {"label": {"type": "text"}, "url": {"type": "text"}, "icon": {"type": "icon"}, "new_window": {"type": "checkbox"}}}, "layout": {"type": "select", "label": "Layout", "options": ["inline", "grid", "list"]}, "style": {"type": "select", "label": "Style", "options": ["buttons", "links", "cards"]}}',
 1, 10),

('repository_spotlight', 'Repository Spotlight', 'Featured repository with description and holdings', 'bi-building-check', '_block_repository_spotlight',
 '{"repository_id": null, "show_logo": true, "show_description": true, "show_contact": false, "show_holdings_count": true, "max_holdings": 5}',
 '{"repository_id": {"type": "entity_picker", "label": "Select Repository", "entity_types": ["repository"]}, "show_logo": {"type": "checkbox", "label": "Show Logo"}, "show_description": {"type": "checkbox", "label": "Show Description"}, "show_contact": {"type": "checkbox", "label": "Show Contact Info"}, "show_holdings_count": {"type": "checkbox", "label": "Show Holdings Count"}, "max_holdings": {"type": "number", "label": "Max Holdings to Display"}}',
 1, 11),

('map_block', 'Map Block', 'Interactive map showing repository locations', 'bi-geo-alt', '_block_map',
 '{"title": "Our Locations", "height": "400px", "zoom": 10, "show_all_repositories": true, "repository_ids": []}',
 '{"title": {"type": "text", "label": "Section Title"}, "height": {"type": "select", "label": "Map Height", "options": ["300px", "400px", "500px"]}, "zoom": {"type": "number", "label": "Default Zoom", "min": 1, "max": 18}, "show_all_repositories": {"type": "checkbox", "label": "Show All Repositories"}, "repository_ids": {"type": "entity_picker", "label": "Or Select Specific", "entity_types": ["repository"], "multiple": true}}',
 1, 12),

('divider', 'Divider', 'Visual separator between sections', 'bi-hr', '_block_divider',
 '{"style": "line", "width": "100%", "color": "#dee2e6", "margin_y": "3"}',
 '{"style": {"type": "select", "label": "Style", "options": ["line", "dashed", "dotted", "gradient", "none"]}, "width": {"type": "select", "label": "Width", "options": ["25%", "50%", "75%", "100%"]}, "color": {"type": "color", "label": "Color"}, "margin_y": {"type": "select", "label": "Vertical Margin", "options": ["1", "2", "3", "4", "5"]}}',
 1, 13),

('spacer', 'Spacer', 'Empty space for layout control', 'bi-arrows-expand', '_block_spacer',
 '{"height": "50px"}',
 '{"height": {"type": "select", "label": "Height", "options": ["25px", "50px", "75px", "100px", "150px"]}}',
 1, 14);

-- Insert Default Landing Page
INSERT INTO atom_landing_page (name, slug, description, is_default, is_active, culture) VALUES
('Default Home Page', 'home', 'Default AHG landing page', 1, 1, 'en');

