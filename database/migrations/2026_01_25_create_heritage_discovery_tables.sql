-- Migration: Create Heritage Discovery Engine Tables
-- Date: 2026-01-25
-- Description: Tables for intelligent search, learning, and ranking

-- ============================================================================
-- Table: heritage_discovery_click
-- Track user clicks on search results for learning
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_discovery_click (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    search_log_id BIGINT NOT NULL,
    item_id INT NOT NULL,
    item_type VARCHAR(50) DEFAULT 'information_object',
    position INT NOT NULL,
    time_to_click_ms INT DEFAULT NULL,
    dwell_time_seconds INT DEFAULT NULL,

    session_id VARCHAR(100) DEFAULT NULL,
    user_id INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_search_log (search_log_id),
    INDEX idx_item (item_id),
    INDEX idx_session (session_id),
    INDEX idx_created (created_at),

    CONSTRAINT fk_discovery_click_log
        FOREIGN KEY (search_log_id) REFERENCES heritage_discovery_log(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_learned_term
-- Learned synonyms and term relationships from user behavior
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_learned_term (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    term VARCHAR(255) NOT NULL,
    related_term VARCHAR(255) NOT NULL,
    relationship_type ENUM('synonym', 'broader', 'narrower', 'related', 'spelling') DEFAULT 'related',
    confidence_score DECIMAL(5,4) DEFAULT 0.5,
    usage_count INT DEFAULT 1,

    source ENUM('user_behavior', 'admin', 'taxonomy', 'external') DEFAULT 'user_behavior',
    is_verified TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_term_pair (institution_id, term, related_term),
    INDEX idx_term (term),
    INDEX idx_related (related_term),
    INDEX idx_confidence (confidence_score),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_search_suggestion
-- Autocomplete suggestions built from successful searches
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_search_suggestion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    suggestion_text VARCHAR(255) NOT NULL,
    suggestion_type ENUM('query', 'title', 'subject', 'creator', 'place') DEFAULT 'query',

    search_count INT DEFAULT 1,
    click_count INT DEFAULT 0,
    success_rate DECIMAL(5,4) DEFAULT 0.5,
    avg_results INT DEFAULT 0,

    last_searched_at TIMESTAMP NULL,
    is_curated TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_suggestion (institution_id, suggestion_text, suggestion_type),
    INDEX idx_text (suggestion_text),
    INDEX idx_type (suggestion_type),
    INDEX idx_search_count (search_count DESC),
    INDEX idx_success_rate (success_rate DESC),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_ranking_config
-- Configurable ranking weights per institution
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_ranking_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Relevance weights
    weight_title_match DECIMAL(4,3) DEFAULT 1.000,
    weight_content_match DECIMAL(4,3) DEFAULT 0.700,
    weight_identifier_match DECIMAL(4,3) DEFAULT 0.900,
    weight_subject_match DECIMAL(4,3) DEFAULT 0.800,
    weight_creator_match DECIMAL(4,3) DEFAULT 0.800,

    -- Quality weights
    weight_has_digital_object DECIMAL(4,3) DEFAULT 0.300,
    weight_description_length DECIMAL(4,3) DEFAULT 0.200,
    weight_has_dates DECIMAL(4,3) DEFAULT 0.150,
    weight_has_subjects DECIMAL(4,3) DEFAULT 0.150,

    -- Engagement weights
    weight_view_count DECIMAL(4,3) DEFAULT 0.100,
    weight_download_count DECIMAL(4,3) DEFAULT 0.150,
    weight_citation_count DECIMAL(4,3) DEFAULT 0.200,

    -- Boost/penalty
    boost_featured DECIMAL(4,3) DEFAULT 1.500,
    boost_recent DECIMAL(4,3) DEFAULT 1.100,
    penalty_incomplete DECIMAL(4,3) DEFAULT 0.800,

    -- Freshness decay
    freshness_decay_days INT DEFAULT 365,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_entity_cache
-- Cached extracted entities for faster filtering
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_entity_cache (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,

    entity_type ENUM('person', 'organization', 'place', 'date', 'event', 'work') NOT NULL,
    entity_value VARCHAR(500) NOT NULL,
    normalized_value VARCHAR(500) DEFAULT NULL,
    confidence_score DECIMAL(5,4) DEFAULT 1.0,

    source_field VARCHAR(100) DEFAULT NULL,
    extraction_method ENUM('taxonomy', 'ner', 'pattern', 'manual') DEFAULT 'taxonomy',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_value (entity_value(100)),
    INDEX idx_normalized (normalized_value(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Add columns to heritage_discovery_log for enhanced tracking
-- Using procedure to handle "column already exists" gracefully
-- ============================================================================
DROP PROCEDURE IF EXISTS add_discovery_log_columns;
DELIMITER //
CREATE PROCEDURE add_discovery_log_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'detected_language') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN detected_language VARCHAR(10) DEFAULT 'en' AFTER query_text;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'query_intent') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN query_intent VARCHAR(50) DEFAULT NULL AFTER detected_language;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'parsed_entities') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN parsed_entities JSON DEFAULT NULL AFTER query_intent;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'expanded_terms') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN expanded_terms JSON DEFAULT NULL AFTER parsed_entities;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'click_count') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN click_count INT DEFAULT 0 AFTER result_count;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'first_click_position') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN first_click_position INT DEFAULT NULL AFTER click_count;
    END IF;
END //
DELIMITER ;
CALL add_discovery_log_columns();
DROP PROCEDURE IF EXISTS add_discovery_log_columns;

-- ============================================================================
-- Seed default ranking config (global defaults)
-- ============================================================================
INSERT IGNORE INTO heritage_ranking_config (institution_id) VALUES (NULL);

-- ============================================================================
-- Seed some common learned terms (basic synonyms)
-- ============================================================================
INSERT IGNORE INTO heritage_learned_term (institution_id, term, related_term, relationship_type, confidence_score, source, is_verified) VALUES
-- Photo synonyms
(NULL, 'photo', 'photograph', 'synonym', 0.95, 'admin', 1),
(NULL, 'photos', 'photographs', 'synonym', 0.95, 'admin', 1),
(NULL, 'picture', 'photograph', 'synonym', 0.90, 'admin', 1),
(NULL, 'image', 'photograph', 'related', 0.85, 'admin', 1),
-- Document synonyms
(NULL, 'doc', 'document', 'synonym', 0.90, 'admin', 1),
(NULL, 'letter', 'correspondence', 'related', 0.85, 'admin', 1),
(NULL, 'memo', 'memorandum', 'synonym', 0.95, 'admin', 1),
-- Map synonyms
(NULL, 'map', 'cartographic material', 'related', 0.80, 'admin', 1),
(NULL, 'chart', 'map', 'related', 0.75, 'admin', 1),
-- Time period terms
(NULL, 'old', 'historic', 'related', 0.70, 'admin', 1),
(NULL, 'ancient', 'historic', 'related', 0.75, 'admin', 1),
(NULL, 'vintage', 'historic', 'related', 0.80, 'admin', 1),
(NULL, 'antique', 'historic', 'related', 0.75, 'admin', 1),
-- Common misspellings
(NULL, 'arcive', 'archive', 'spelling', 0.99, 'admin', 1),
(NULL, 'photgraph', 'photograph', 'spelling', 0.99, 'admin', 1),
(NULL, 'documnet', 'document', 'spelling', 0.99, 'admin', 1);

-- Verification
SELECT 'heritage_discovery_click' as tbl, COUNT(*) as cnt FROM heritage_discovery_click
UNION ALL SELECT 'heritage_learned_term', COUNT(*) FROM heritage_learned_term
UNION ALL SELECT 'heritage_search_suggestion', COUNT(*) FROM heritage_search_suggestion
UNION ALL SELECT 'heritage_ranking_config', COUNT(*) FROM heritage_ranking_config
UNION ALL SELECT 'heritage_entity_cache', COUNT(*) FROM heritage_entity_cache;
