-- Migration: 002_create_atom_extension
-- Creates extension manager tables
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS atom_extension (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    version VARCHAR(20),
    description TEXT,
    author VARCHAR(255),
    license VARCHAR(50) DEFAULT 'GPL-3.0',
    status ENUM('installed','enabled','disabled','pending_removal') DEFAULT 'installed',
    protection_level ENUM('core','system','theme','extension') DEFAULT 'extension',
    theme_support JSON,
    requires_framework VARCHAR(20),
    requires_atom VARCHAR(20),
    requires_php VARCHAR(20),
    dependencies JSON,
    optional_dependencies JSON,
    tables_created JSON,
    shared_tables JSON,
    helpers JSON,
    install_task VARCHAR(100),
    uninstall_task VARCHAR(100),
    config_path VARCHAR(500),
    installed_at DATETIME,
    enabled_at DATETIME,
    disabled_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS atom_extension_setting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extension_id INT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
    setting_group VARCHAR(100) DEFAULT 'general',
    UNIQUE KEY unique_ext_setting (extension_id, setting_key),
    FOREIGN KEY (extension_id) REFERENCES atom_extension(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS atom_extension_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extension_id INT,
    extension_name VARCHAR(100) NOT NULL,
    action ENUM('installed','enabled','disabled','uninstalled','backup_created','data_deleted','upgraded') NOT NULL,
    performed_by INT,
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS atom_extension_pending_deletion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extension_name VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    backup_path VARCHAR(500),
    delete_after DATETIME NOT NULL,
    status ENUM('pending','deleted','restored','cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
