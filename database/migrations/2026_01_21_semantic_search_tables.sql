-- AtoM Framework: Semantic Search Tables
-- Created: 2026-01-21
-- Purpose: Store thesaurus terms, synonyms, and embeddings for semantic search

-- ============================================================================
-- Core Thesaurus Tables
-- ============================================================================

-- Main thesaurus terms table
CREATE TABLE IF NOT EXISTS ahg_thesaurus_term (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term VARCHAR(255) NOT NULL,
    normalized_term VARCHAR(255) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    source VARCHAR(50) NOT NULL COMMENT 'wordnet, wikidata, local',
    source_id VARCHAR(255) NULL COMMENT 'External ID from source',
    definition TEXT NULL,
    pos VARCHAR(20) NULL COMMENT 'Part of speech: noun, verb, adj, etc.',
    domain VARCHAR(100) NULL COMMENT 'archival, library, museum, general',
    frequency INT DEFAULT 0 COMMENT 'Usage frequency score',
    is_preferred TINYINT(1) DEFAULT 0 COMMENT 'Is this the preferred term',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_term_source (normalized_term, source, language),
    INDEX idx_term (term),
    INDEX idx_normalized (normalized_term),
    INDEX idx_domain (domain),
    INDEX idx_source (source),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Synonym relationships between terms
CREATE TABLE IF NOT EXISTS ahg_thesaurus_synonym (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NOT NULL,
    synonym_term_id BIGINT UNSIGNED NULL COMMENT 'Link to another thesaurus term if exists',
    synonym_text VARCHAR(255) NOT NULL COMMENT 'The synonym text',
    relationship_type VARCHAR(50) DEFAULT 'synonym' COMMENT 'synonym, broader, narrower, related, use_for',
    weight DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Relevance weight 0.00-1.00',
    source VARCHAR(50) NOT NULL COMMENT 'wordnet, wikidata, local',
    is_bidirectional TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE CASCADE,
    FOREIGN KEY (synonym_term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE SET NULL,

    UNIQUE KEY uk_term_synonym (term_id, synonym_text, relationship_type),
    INDEX idx_synonym_text (synonym_text),
    INDEX idx_relationship (relationship_type),
    INDEX idx_weight (weight),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync job tracking
CREATE TABLE IF NOT EXISTS ahg_thesaurus_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL COMMENT 'wordnet, wikidata, local',
    sync_type VARCHAR(50) NOT NULL COMMENT 'full, incremental, term_specific',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, running, completed, failed',
    terms_processed INT DEFAULT 0,
    terms_added INT DEFAULT 0,
    terms_updated INT DEFAULT 0,
    synonyms_added INT DEFAULT 0,
    errors TEXT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_source (source),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vector embeddings cache for semantic similarity
CREATE TABLE IF NOT EXISTS ahg_thesaurus_embedding (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id BIGINT UNSIGNED NOT NULL,
    model VARCHAR(100) NOT NULL COMMENT 'ollama model name',
    embedding LONGBLOB NOT NULL COMMENT 'Serialized vector',
    embedding_dimension INT NOT NULL COMMENT 'Vector dimension (e.g., 768, 1536)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (term_id) REFERENCES ahg_thesaurus_term(id) ON DELETE CASCADE,
    UNIQUE KEY uk_term_model (term_id, model),
    INDEX idx_model (model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings and configuration
CREATE TABLE IF NOT EXISTS ahg_semantic_search_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type VARCHAR(20) DEFAULT 'string' COMMENT 'string, int, bool, json',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO ahg_semantic_search_settings (setting_key, setting_value, setting_type, description) VALUES
('semantic_search_enabled', '1', 'bool', 'Enable semantic search functionality'),
('default_expansion_limit', '5', 'int', 'Maximum number of synonyms to expand per term'),
('min_synonym_weight', '0.6', 'string', 'Minimum weight threshold for synonym inclusion'),
('datamuse_rate_limit_ms', '100', 'int', 'Rate limit for Datamuse API calls in milliseconds'),
('wikidata_rate_limit_ms', '500', 'int', 'Rate limit for Wikidata API calls in milliseconds'),
('ollama_endpoint', 'http://localhost:11434', 'string', 'Ollama API endpoint'),
('ollama_model', 'nomic-embed-text', 'string', 'Ollama model for embeddings'),
('elasticsearch_synonyms_path', '/etc/elasticsearch/synonyms/ahg_synonyms.txt', 'string', 'Path to Elasticsearch synonyms file'),
('show_expansion_info', '1', 'bool', 'Show query expansion info to users'),
('cache_ttl_seconds', '86400', 'int', 'Cache TTL for API responses (24 hours)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Search query log for analytics
CREATE TABLE IF NOT EXISTS ahg_semantic_search_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_query VARCHAR(500) NOT NULL,
    expanded_query TEXT NULL,
    expansion_terms TEXT NULL COMMENT 'JSON array of terms added',
    result_count INT DEFAULT 0,
    search_time_ms INT NULL,
    user_id INT NULL,
    session_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_query (original_query(100)),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
