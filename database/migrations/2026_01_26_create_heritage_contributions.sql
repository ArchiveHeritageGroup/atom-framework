-- Migration: Create Heritage Contributions Tables
-- Date: 2026-01-26
-- Description: Public contributor accounts and contribution system

-- ============================================================================
-- CONTRIBUTOR ACCOUNTS
-- ============================================================================

-- Table: heritage_contributor
-- Public user accounts (separate from AtoM users)
CREATE TABLE IF NOT EXISTS heritage_contributor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    trust_level ENUM('new', 'contributor', 'trusted', 'expert') DEFAULT 'new',
    email_verified TINYINT(1) DEFAULT 0,
    email_verify_token VARCHAR(100) DEFAULT NULL,
    email_verify_expires TIMESTAMP NULL,
    password_reset_token VARCHAR(100) DEFAULT NULL,
    password_reset_expires TIMESTAMP NULL,
    total_contributions INT DEFAULT 0,
    approved_contributions INT DEFAULT 0,
    rejected_contributions INT DEFAULT 0,
    points INT DEFAULT 0,
    badges JSON DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_contribution_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_trust_level (trust_level),
    INDEX idx_points (points DESC),
    INDEX idx_verified (email_verified),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTION TYPES
-- ============================================================================

-- Table: heritage_contribution_type
-- Types of contributions users can make
CREATE TABLE IF NOT EXISTS heritage_contribution_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-pencil',
    color VARCHAR(20) DEFAULT 'primary',
    requires_validation TINYINT(1) DEFAULT 1,
    points_value INT DEFAULT 10,
    min_trust_level ENUM('new', 'contributor', 'trusted', 'expert') DEFAULT 'new',
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTIONS
-- ============================================================================

-- Table: heritage_contribution
-- Individual contributions from users
CREATE TABLE IF NOT EXISTS heritage_contribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    information_object_id INT NOT NULL,
    contribution_type_id INT NOT NULL,
    content JSON NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'superseded') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT DEFAULT NULL,
    points_awarded INT DEFAULT 0,
    version_number INT DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_object (information_object_id),
    INDEX idx_type (contribution_type_id),
    INDEX idx_status (status),
    INDEX idx_reviewed_by (reviewed_by),
    INDEX idx_created (created_at),
    INDEX idx_featured (is_featured),

    CONSTRAINT fk_heritage_contribution_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_contribution_type
        FOREIGN KEY (contribution_type_id) REFERENCES heritage_contribution_type(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution_version
-- Version history for contribution edits
CREATE TABLE IF NOT EXISTS heritage_contribution_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribution_id INT NOT NULL,
    version_number INT NOT NULL,
    content JSON NOT NULL,
    created_by INT NOT NULL,
    change_summary VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_contribution (contribution_id),
    INDEX idx_version (contribution_id, version_number),

    CONSTRAINT fk_heritage_contribution_version_contribution
        FOREIGN KEY (contribution_id) REFERENCES heritage_contribution(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_contribution_version_creator
        FOREIGN KEY (created_by) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTOR SESSIONS
-- ============================================================================

-- Table: heritage_contributor_session
-- Session tokens for contributor authentication
CREATE TABLE IF NOT EXISTS heritage_contributor_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),

    CONSTRAINT fk_heritage_contributor_session_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTOR BADGES/ACHIEVEMENTS
-- ============================================================================

-- Table: heritage_contributor_badge
-- Badges that can be earned
CREATE TABLE IF NOT EXISTS heritage_contributor_badge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-award',
    color VARCHAR(20) DEFAULT 'primary',
    criteria_type ENUM('contribution_count', 'approval_rate', 'points', 'type_specific', 'manual') DEFAULT 'contribution_count',
    criteria_value INT DEFAULT 0,
    criteria_config JSON DEFAULT NULL,
    points_bonus INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_badge_award
-- Badges awarded to contributors
CREATE TABLE IF NOT EXISTS heritage_contributor_badge_award (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_contributor_badge (contributor_id, badge_id),

    CONSTRAINT fk_heritage_badge_award_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_badge_award_badge
        FOREIGN KEY (badge_id) REFERENCES heritage_contributor_badge(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Default contribution types
INSERT IGNORE INTO heritage_contribution_type (code, name, description, icon, color, requires_validation, points_value, display_order) VALUES
('transcription', 'Transcription', 'Transcribe handwritten or typed documents into searchable text', 'bi-file-text', 'primary', 1, 25, 1),
('identification', 'Identification', 'Identify people, places, or objects in photographs and documents', 'bi-person-badge', 'success', 1, 15, 2),
('context', 'Historical Context', 'Add historical context, personal memories, or background information', 'bi-book', 'info', 1, 20, 3),
('correction', 'Correction', 'Suggest corrections to existing metadata or descriptions', 'bi-pencil-square', 'warning', 1, 10, 4),
('translation', 'Translation', 'Translate content into other languages', 'bi-translate', 'secondary', 1, 30, 5),
('tag', 'Tags/Keywords', 'Add relevant tags and keywords to improve discoverability', 'bi-tags', 'dark', 0, 5, 6);

-- Default badges
INSERT IGNORE INTO heritage_contributor_badge (code, name, description, icon, color, criteria_type, criteria_value, display_order) VALUES
('first_contribution', 'First Steps', 'Made your first contribution', 'bi-star', 'warning', 'contribution_count', 1, 1),
('contributor_10', 'Active Contributor', 'Made 10 approved contributions', 'bi-star-fill', 'warning', 'contribution_count', 10, 2),
('contributor_50', 'Dedicated Contributor', 'Made 50 approved contributions', 'bi-trophy', 'warning', 'contribution_count', 50, 3),
('contributor_100', 'Heritage Champion', 'Made 100 approved contributions', 'bi-trophy-fill', 'primary', 'contribution_count', 100, 4),
('transcriber', 'Transcription Expert', 'Completed 25 transcriptions', 'bi-file-text-fill', 'primary', 'type_specific', 25, 10),
('identifier', 'Sharp Eye', 'Identified people in 25 photographs', 'bi-eye', 'success', 'type_specific', 25, 11),
('historian', 'Local Historian', 'Added context to 25 records', 'bi-book-fill', 'info', 'type_specific', 25, 12),
('perfectionist', 'High Quality', 'Maintained 95% approval rate on 20+ contributions', 'bi-check-circle-fill', 'success', 'approval_rate', 95, 20);

-- Verification
SELECT 'heritage_contributor' as tbl, COUNT(*) as cnt FROM heritage_contributor
UNION ALL SELECT 'heritage_contribution_type', COUNT(*) FROM heritage_contribution_type
UNION ALL SELECT 'heritage_contribution', COUNT(*) FROM heritage_contribution
UNION ALL SELECT 'heritage_contribution_version', COUNT(*) FROM heritage_contribution_version
UNION ALL SELECT 'heritage_contributor_session', COUNT(*) FROM heritage_contributor_session
UNION ALL SELECT 'heritage_contributor_badge', COUNT(*) FROM heritage_contributor_badge
UNION ALL SELECT 'heritage_contributor_badge_award', COUNT(*) FROM heritage_contributor_badge_award;
