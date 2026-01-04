-- Migration: Create Extended Rights Tables
-- RightsStatements.org vocabulary support

CREATE TABLE IF NOT EXISTS rights_statement (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uri VARCHAR(255) NOT NULL UNIQUE,
    code VARCHAR(50) NOT NULL UNIQUE,
    category ENUM('in-copyright', 'no-copyright', 'other') NOT NULL,
    icon_filename VARCHAR(100) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rights_statement_i18n (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rights_statement_id BIGINT UNSIGNED NOT NULL,
    culture VARCHAR(10) DEFAULT 'en',
    name VARCHAR(255) NOT NULL,
    definition TEXT NULL,
    scope_note TEXT NULL,
    UNIQUE KEY unique_statement_culture (rights_statement_id, culture),
    FOREIGN KEY (rights_statement_id) REFERENCES rights_statement(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS creative_commons_license (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uri VARCHAR(255) NOT NULL UNIQUE,
    code VARCHAR(50) NOT NULL UNIQUE,
    version VARCHAR(10) NOT NULL,
    icon_url VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS creative_commons_license_i18n (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_id BIGINT UNSIGNED NOT NULL,
    culture VARCHAR(10) DEFAULT 'en',
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    UNIQUE KEY unique_license_culture (license_id, culture),
    FOREIGN KEY (license_id) REFERENCES creative_commons_license(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
