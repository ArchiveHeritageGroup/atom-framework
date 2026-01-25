-- Migration: Create Heritage Platform Tables for Sessions 6-9
-- Date: 2026-01-26
-- Description: Admin Configuration, Access Mediation, Custodian Interface, Analytics & Learning

-- ============================================================================
-- SESSION 8: ADMIN CONFIGURATION
-- ============================================================================

-- Table: heritage_feature_toggle
-- Feature flags per institution
CREATE TABLE IF NOT EXISTS heritage_feature_toggle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    feature_code VARCHAR(100) NOT NULL,
    feature_name VARCHAR(255) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution_feature (institution_id, feature_code),
    INDEX idx_feature_code (feature_code),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_branding_config
-- Institution branding configuration
CREATE TABLE IF NOT EXISTS heritage_branding_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    logo_path VARCHAR(500) DEFAULT NULL,
    favicon_path VARCHAR(500) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,
    accent_color VARCHAR(7) DEFAULT NULL,
    banner_text VARCHAR(500) DEFAULT NULL,
    footer_text TEXT DEFAULT NULL,
    custom_css TEXT DEFAULT NULL,
    social_links JSON DEFAULT NULL,
    contact_info JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SESSION 6: ACCESS MEDIATION
-- ============================================================================

-- Table: heritage_trust_level
-- User trust levels for access control
CREATE TABLE IF NOT EXISTS heritage_trust_level (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    can_view_restricted TINYINT(1) DEFAULT 0,
    can_download TINYINT(1) DEFAULT 0,
    can_bulk_download TINYINT(1) DEFAULT 0,
    is_system TINYINT(1) DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_level (level),
    INDEX idx_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_user_trust
-- User trust level assignments
CREATE TABLE IF NOT EXISTS heritage_user_trust (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trust_level_id INT NOT NULL,
    institution_id INT DEFAULT NULL,
    granted_by INT DEFAULT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,

    UNIQUE KEY uk_user_institution (user_id, institution_id),
    INDEX idx_trust_level (trust_level_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active),

    CONSTRAINT fk_heritage_user_trust_level
        FOREIGN KEY (trust_level_id) REFERENCES heritage_trust_level(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_purpose
-- Purposes for access requests
CREATE TABLE IF NOT EXISTS heritage_purpose (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    requires_approval TINYINT(1) DEFAULT 0,
    min_trust_level INT DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,

    INDEX idx_enabled (is_enabled),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_embargo
-- Embargoes on objects
CREATE TABLE IF NOT EXISTS heritage_embargo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    embargo_type ENUM('full', 'digital_only', 'metadata_hidden') DEFAULT 'full',
    reason TEXT DEFAULT NULL,
    legal_basis VARCHAR(255) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    auto_release TINYINT(1) DEFAULT 1,
    notify_on_release TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_end_date (end_date),
    INDEX idx_type (embargo_type),
    INDEX idx_auto_release (auto_release, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_access_request
-- Access requests from users
CREATE TABLE IF NOT EXISTS heritage_access_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_id INT NOT NULL,
    purpose_id INT DEFAULT NULL,
    purpose_text VARCHAR(255) DEFAULT NULL,
    justification TEXT DEFAULT NULL,
    research_description TEXT DEFAULT NULL,
    institution_affiliation VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'denied', 'expired', 'withdrawn') DEFAULT 'pending',
    decision_by INT DEFAULT NULL,
    decision_at TIMESTAMP NULL,
    decision_notes TEXT DEFAULT NULL,
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    access_granted JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),

    CONSTRAINT fk_heritage_access_request_purpose
        FOREIGN KEY (purpose_id) REFERENCES heritage_purpose(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_access_rule
-- Access rules for objects/collections
CREATE TABLE IF NOT EXISTS heritage_access_rule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    rule_type ENUM('allow', 'deny', 'require_approval') DEFAULT 'deny',
    applies_to ENUM('all', 'anonymous', 'authenticated', 'trust_level') DEFAULT 'all',
    trust_level_id INT DEFAULT NULL,
    action ENUM('view', 'view_metadata', 'download', 'download_master', 'print', 'all') DEFAULT 'view',
    priority INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_collection (collection_id),
    INDEX idx_repository (repository_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_popia_flag
-- POPIA/GDPR privacy flags
CREATE TABLE IF NOT EXISTS heritage_popia_flag (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    flag_type ENUM('personal_info', 'sensitive', 'children', 'health', 'biometric', 'criminal', 'financial', 'political', 'religious', 'sexual') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT DEFAULT NULL,
    affected_fields JSON DEFAULT NULL,
    detected_by ENUM('automatic', 'manual', 'review') DEFAULT 'manual',
    is_resolved TINYINT(1) DEFAULT 0,
    resolution_notes TEXT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_flag_type (flag_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SESSION 7: CUSTODIAN INTERFACE
-- ============================================================================

-- Table: heritage_audit_log
-- Detailed change tracking
CREATE TABLE IF NOT EXISTS heritage_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(255) DEFAULT NULL,
    object_id INT DEFAULT NULL,
    object_type VARCHAR(100) DEFAULT 'information_object',
    object_identifier VARCHAR(255) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    action_category ENUM('create', 'update', 'delete', 'view', 'export', 'import', 'batch', 'access', 'system') DEFAULT 'update',
    field_name VARCHAR(100) DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    changes_json JSON DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id, object_type),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_category (action_category),
    INDEX idx_created (created_at),
    INDEX idx_field (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_batch_job
-- Batch job tracking
CREATE TABLE IF NOT EXISTS heritage_batch_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(100) NOT NULL,
    job_name VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'queued', 'processing', 'completed', 'failed', 'cancelled', 'paused') DEFAULT 'pending',
    user_id INT NOT NULL,
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    successful_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    skipped_items INT DEFAULT 0,
    parameters JSON DEFAULT NULL,
    results JSON DEFAULT NULL,
    error_log JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    progress_message VARCHAR(500) DEFAULT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_type (job_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_batch_item
-- Individual items in a batch job
CREATE TABLE IF NOT EXISTS heritage_batch_item (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    object_id INT NOT NULL,
    status ENUM('pending', 'processing', 'success', 'failed', 'skipped') DEFAULT 'pending',
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job (job_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),

    CONSTRAINT fk_heritage_batch_item_job
        FOREIGN KEY (job_id) REFERENCES heritage_batch_job(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SESSION 9: ANALYTICS & LEARNING
-- ============================================================================

-- Table: heritage_analytics_daily
-- Daily aggregate metrics
CREATE TABLE IF NOT EXISTS heritage_analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    metric_type VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) DEFAULT 0,
    previous_value DECIMAL(15,2) DEFAULT NULL,
    change_percent DECIMAL(10,2) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_date_metric (institution_id, date, metric_type),
    INDEX idx_date (date),
    INDEX idx_metric_type (metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_search
-- Search pattern tracking
CREATE TABLE IF NOT EXISTS heritage_analytics_search (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    query_pattern VARCHAR(255) DEFAULT NULL,
    query_normalized VARCHAR(255) DEFAULT NULL,
    search_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    zero_result_count INT DEFAULT 0,
    avg_results DECIMAL(10,2) DEFAULT 0,
    avg_position_clicked DECIMAL(5,2) DEFAULT NULL,
    conversion_rate DECIMAL(5,4) DEFAULT 0,

    UNIQUE KEY uk_date_pattern (institution_id, date, query_pattern),
    INDEX idx_date (date),
    INDEX idx_search_count (search_count DESC),
    INDEX idx_zero_result (zero_result_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_content
-- Content performance tracking
CREATE TABLE IF NOT EXISTS heritage_analytics_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    view_count INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    search_appearances INT DEFAULT 0,
    download_count INT DEFAULT 0,
    citation_count INT DEFAULT 0,
    share_count INT DEFAULT 0,
    avg_dwell_time_seconds INT DEFAULT NULL,
    click_through_rate DECIMAL(5,4) DEFAULT 0,
    bounce_rate DECIMAL(5,4) DEFAULT NULL,
    metadata JSON DEFAULT NULL,

    UNIQUE KEY uk_object_period (object_id, period_start, period_end),
    INDEX idx_period (period_start, period_end),
    INDEX idx_views (view_count DESC),
    INDEX idx_ctr (click_through_rate DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_alert
-- Actionable alerts and insights
CREATE TABLE IF NOT EXISTS heritage_analytics_alert (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    alert_type VARCHAR(100) NOT NULL,
    category ENUM('content', 'search', 'access', 'quality', 'system', 'opportunity') DEFAULT 'system',
    severity ENUM('info', 'warning', 'critical', 'success') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    action_label VARCHAR(100) DEFAULT NULL,
    related_data JSON DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    dismissed_by INT DEFAULT NULL,
    dismissed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_type (alert_type),
    INDEX idx_category (category),
    INDEX idx_severity (severity),
    INDEX idx_dismissed (is_dismissed),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_content_quality
-- Content quality scores
CREATE TABLE IF NOT EXISTS heritage_content_quality (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL UNIQUE,
    overall_score DECIMAL(5,2) DEFAULT 0,
    completeness_score DECIMAL(5,2) DEFAULT 0,
    accessibility_score DECIMAL(5,2) DEFAULT 0,
    engagement_score DECIMAL(5,2) DEFAULT 0,
    discoverability_score DECIMAL(5,2) DEFAULT 0,
    issues JSON DEFAULT NULL,
    suggestions JSON DEFAULT NULL,
    last_calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_overall (overall_score DESC),
    INDEX idx_completeness (completeness_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Default trust levels
INSERT IGNORE INTO heritage_trust_level (code, name, level, can_view_restricted, can_download, can_bulk_download, is_system, description) VALUES
('anonymous', 'Anonymous', 0, 0, 0, 0, 1, 'Unauthenticated visitors'),
('registered', 'Registered User', 1, 0, 1, 0, 1, 'Basic registered account'),
('contributor', 'Contributor', 2, 0, 1, 0, 1, 'Users who contribute content'),
('trusted', 'Trusted User', 3, 1, 1, 0, 1, 'Verified trusted researchers'),
('moderator', 'Moderator', 4, 1, 1, 1, 1, 'Content moderators'),
('custodian', 'Custodian', 5, 1, 1, 1, 1, 'Full custodial access');

-- Default purposes
INSERT IGNORE INTO heritage_purpose (code, name, description, requires_approval, min_trust_level, display_order) VALUES
('personal', 'Personal/Family Research', 'Research into family history and genealogy', 0, 0, 1),
('academic', 'Academic Research', 'Scholarly research for educational institutions', 0, 0, 2),
('education', 'Educational Use', 'Use in teaching and educational materials', 0, 0, 3),
('commercial', 'Commercial Use', 'For-profit use requiring license agreement', 1, 1, 4),
('media', 'Media/Journalism', 'Publication in news or media outlets', 1, 1, 5),
('legal', 'Legal/Compliance', 'Legal proceedings or compliance requirements', 1, 1, 6),
('government', 'Government/Official', 'Official government use', 1, 1, 7),
('preservation', 'Preservation/Conservation', 'Digital preservation activities', 0, 2, 8);

-- Default feature toggles (global)
INSERT IGNORE INTO heritage_feature_toggle (institution_id, feature_code, feature_name, is_enabled, config_json) VALUES
(NULL, 'community_contributions', 'Community Contributions', 1, '{"require_moderation": true}'),
(NULL, 'user_registration', 'User Registration', 1, '{"require_email_verification": true}'),
(NULL, 'social_sharing', 'Social Sharing', 1, '{"platforms": ["facebook", "twitter", "linkedin", "email"]}'),
(NULL, 'downloads', 'Downloads', 1, '{"require_login": false, "track_downloads": true}'),
(NULL, 'citations', 'Citation Generation', 1, '{"formats": ["apa", "mla", "chicago", "harvard"]}'),
(NULL, 'analytics', 'Analytics Dashboard', 1, '{"admin_only": true}'),
(NULL, 'access_requests', 'Access Requests', 1, '{"email_notifications": true}'),
(NULL, 'embargoes', 'Embargo Management', 1, '{}'),
(NULL, 'batch_operations', 'Batch Operations', 1, '{"max_items": 1000}'),
(NULL, 'audit_trail', 'Audit Trail', 1, '{"retention_days": 365}');

-- Default branding (global)
INSERT IGNORE INTO heritage_branding_config (institution_id, primary_color, secondary_color, banner_text, footer_text) VALUES
(NULL, '#0d6efd', '#6c757d', NULL, 'Powered by AtoM Heritage Platform');

-- Verification
SELECT 'heritage_feature_toggle' as tbl, COUNT(*) as cnt FROM heritage_feature_toggle
UNION ALL SELECT 'heritage_branding_config', COUNT(*) FROM heritage_branding_config
UNION ALL SELECT 'heritage_trust_level', COUNT(*) FROM heritage_trust_level
UNION ALL SELECT 'heritage_user_trust', COUNT(*) FROM heritage_user_trust
UNION ALL SELECT 'heritage_purpose', COUNT(*) FROM heritage_purpose
UNION ALL SELECT 'heritage_embargo', COUNT(*) FROM heritage_embargo
UNION ALL SELECT 'heritage_access_request', COUNT(*) FROM heritage_access_request
UNION ALL SELECT 'heritage_access_rule', COUNT(*) FROM heritage_access_rule
UNION ALL SELECT 'heritage_popia_flag', COUNT(*) FROM heritage_popia_flag
UNION ALL SELECT 'heritage_audit_log', COUNT(*) FROM heritage_audit_log
UNION ALL SELECT 'heritage_batch_job', COUNT(*) FROM heritage_batch_job
UNION ALL SELECT 'heritage_batch_item', COUNT(*) FROM heritage_batch_item
UNION ALL SELECT 'heritage_analytics_daily', COUNT(*) FROM heritage_analytics_daily
UNION ALL SELECT 'heritage_analytics_search', COUNT(*) FROM heritage_analytics_search
UNION ALL SELECT 'heritage_analytics_content', COUNT(*) FROM heritage_analytics_content
UNION ALL SELECT 'heritage_analytics_alert', COUNT(*) FROM heritage_analytics_alert
UNION ALL SELECT 'heritage_content_quality', COUNT(*) FROM heritage_content_quality;
