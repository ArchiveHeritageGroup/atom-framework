-- ============================================================================
-- WorldCat ISBN Lookup Tables
-- AtoM Laravel Framework
-- ============================================================================

-- Check if table exists before creating
CREATE TABLE IF NOT EXISTS atom_isbn_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(13) NOT NULL,
    isbn_10 VARCHAR(10) NULL,
    isbn_13 VARCHAR(13) NULL,
    metadata JSON NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'worldcat',
    oclc_number VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY uk_isbn (isbn),
    INDEX idx_isbn_10 (isbn_10),
    INDEX idx_isbn_13 (isbn_13),
    INDEX idx_oclc (oclc_number),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ISBN Lookup Audit Trail
CREATE TABLE IF NOT EXISTS atom_isbn_lookup_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(13) NOT NULL,
    user_id INT UNSIGNED NULL,
    information_object_id INT UNSIGNED NULL,
    source VARCHAR(50) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    fields_populated JSON NULL,
    error_message TEXT NULL,
    lookup_time_ms INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_isbn (isbn),
    INDEX idx_user (user_id),
    INDEX idx_io (information_object_id),
    INDEX idx_created (created_at),
    CONSTRAINT fk_isbn_audit_user 
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE SET NULL,
    CONSTRAINT fk_isbn_audit_io 
        FOREIGN KEY (information_object_id) REFERENCES information_object(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ISBN Provider Configuration
CREATE TABLE IF NOT EXISTS atom_isbn_provider (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    api_endpoint VARCHAR(500) NOT NULL,
    api_key_setting VARCHAR(100) NULL COMMENT 'Reference to atom_setting key',
    priority INT NOT NULL DEFAULT 100,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    rate_limit_per_minute INT UNSIGNED NULL,
    response_format ENUM('json', 'xml', 'marcxml') NOT NULL DEFAULT 'json',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_enabled_priority (enabled, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default providers
INSERT INTO atom_isbn_provider (name, slug, api_endpoint, priority, enabled, rate_limit_per_minute, response_format)
VALUES 
    ('Open Library', 'openlibrary', 'https://openlibrary.org/api/books', 10, 1, 100, 'json'),
    ('Google Books', 'googlebooks', 'https://www.googleapis.com/books/v1/volumes', 20, 1, 100, 'json'),
    ('WorldCat', 'worldcat', 'https://www.worldcat.org/webservices/catalog/content/isbn/', 30, 0, 10, 'marcxml')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
