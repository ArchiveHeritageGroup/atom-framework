-- atom_plugin table for database-driven plugin management
CREATE TABLE IF NOT EXISTS atom_plugin (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    class_name VARCHAR(255) NOT NULL,
    version VARCHAR(50) NULL,
    description TEXT NULL,
    author VARCHAR(255) NULL,
    category VARCHAR(100) DEFAULT 'general',
    is_enabled TINYINT(1) DEFAULT 0,
    is_core TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    load_order INT DEFAULT 100,
    plugin_path VARCHAR(500) NULL,
    settings JSON NULL,
    enabled_at TIMESTAMP NULL,
    disabled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (name),
    KEY idx_category (category),
    KEY idx_is_enabled (is_enabled),
    KEY idx_load_order (load_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- atom_plugin_audit for tracking changes
CREATE TABLE IF NOT EXISTS atom_plugin_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_name VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    previous_state VARCHAR(50) NULL,
    new_state VARCHAR(50) NULL,
    user_id INT NULL,
    reason TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_plugin_name (plugin_name),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
