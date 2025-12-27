-- =============================================================================
-- AtoM Framework Install SQL
-- Core tables required for framework operation
-- Run this first before any plugin installs
-- =============================================================================

SET FOREIGN_KEY_CHECKS=0;

-- Plugin Registry
CREATE TABLE IF NOT EXISTS `atom_plugin` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `load_order` int DEFAULT 100,
  `version` varchar(20) DEFAULT '1.0.0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plugin Audit Log
CREATE TABLE IF NOT EXISTS `atom_plugin_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin` (`plugin_name`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AHG Settings
CREATE TABLE IF NOT EXISTS `ahg_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json','float') DEFAULT 'string',
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `description` varchar(500) DEFAULT NULL,
  `is_sensitive` tinyint(1) DEFAULT 0,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Plugins (Core AtoM plugins)
INSERT IGNORE INTO atom_plugin (name, is_enabled, load_order) VALUES
('arDominionB5Plugin', 1, 10),
('arOaiPlugin', 1, 20),
('arRestApiPlugin', 1, 30),
('sfIsadPlugin', 1, 40),
('sfIsdfPlugin', 1, 50),
('sfIsaarPlugin', 1, 60),
('sfIsdiahPlugin', 1, 70),
('sfEacPlugin', 1, 80),
('sfEadPlugin', 1, 90),
('sfDcPlugin', 1, 100),
('sfModsPlugin', 1, 110),
('sfRadPlugin', 1, 120),
('sfSkosPlugin', 1, 130),
('arDacsPlugin', 1, 140),
('sfWebBrowserPlugin', 1, 150);

SET FOREIGN_KEY_CHECKS=1;

-- Extension Settings
CREATE TABLE IF NOT EXISTS `atom_extension_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `extension_id` int unsigned DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(20) DEFAULT 'string',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ext_key` (`extension_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extension Registry (for Extension System)
CREATE TABLE IF NOT EXISTS `atom_extension` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `machine_name` varchar(100) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `version` varchar(20) DEFAULT '1.0.0',
  `description` text,
  `author` varchar(255) DEFAULT NULL,
  `license` varchar(50) DEFAULT 'GPL-3.0',
  `status` enum('installed','enabled','disabled','pending_removal') DEFAULT 'installed',
  `theme_support` json DEFAULT NULL,
  `requires_framework` varchar(20) DEFAULT NULL,
  `dependencies` json DEFAULT NULL,
  `tables_created` json DEFAULT NULL,
  `shared_tables` json DEFAULT NULL,
  `installed_at` datetime DEFAULT NULL,
  `enabled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_name` (`machine_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pending Deletions
CREATE TABLE IF NOT EXISTS `atom_extension_pending_deletion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `extension_name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `backup_path` varchar(500) DEFAULT NULL,
  `delete_after` datetime NOT NULL,
  `status` enum('pending','deleted','restored','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- IIIF Tables (Homepage Carousel Support)
-- =============================================================================

-- IIIF Viewer Settings
CREATE TABLE IF NOT EXISTS `iiif_viewer_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default IIIF settings
INSERT IGNORE INTO `iiif_viewer_settings` (`setting_key`, `setting_value`, `description`) VALUES
('homepage_collection_id', '', 'Collection ID to feature on homepage'),
('homepage_collection_enabled', '0', 'Enable featured collection on homepage'),
('homepage_carousel_height', '400', 'Homepage carousel height'),
('homepage_carousel_autoplay', '1', 'Auto-rotate homepage carousel'),
('homepage_carousel_interval', '5000', 'Homepage carousel interval (ms)'),
('homepage_show_captions', '1', 'Show captions on homepage carousel'),
('homepage_max_items', '10', 'Max items on homepage carousel');

-- IIIF Collections
CREATE TABLE IF NOT EXISTS `iiif_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `attribution` varchar(500) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `viewing_hint` enum('individuals','paged','continuous','multi-part','top') DEFAULT 'individuals',
  `nav_date` date DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_public` (`is_public`),
  CONSTRAINT `iiif_collection_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `iiif_collection` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IIIF Collection Translations
CREATE TABLE IF NOT EXISTS `iiif_collection_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection_culture` (`collection_id`,`culture`),
  CONSTRAINT `iiif_collection_i18n_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IIIF Collection Items
CREATE TABLE IF NOT EXISTS `iiif_collection_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `manifest_uri` varchar(1000) DEFAULT NULL,
  `item_type` enum('manifest','collection') DEFAULT 'manifest',
  `label` varchar(500) DEFAULT NULL,
  `description` text,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_object` (`object_id`),
  CONSTRAINT `iiif_collection_item_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contact Information Extended
CREATE TABLE IF NOT EXISTS `contact_information_extended` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_information_id` int NOT NULL,
  `title` varchar(100) DEFAULT NULL COMMENT 'Mr, Mrs, Dr, Prof, etc.',
  `role` varchar(255) DEFAULT NULL COMMENT 'Job title/position',
  `department` varchar(255) DEFAULT NULL COMMENT 'Department/Division',
  `cell` varchar(255) DEFAULT NULL COMMENT 'Mobile/Cell phone',
  `id_number` varchar(50) DEFAULT NULL COMMENT 'ID/Passport number',
  `alternative_email` varchar(255) DEFAULT NULL COMMENT 'Secondary email',
  `alternative_phone` varchar(255) DEFAULT NULL COMMENT 'Secondary phone',
  `preferred_contact_method` enum('email','phone','cell','fax','mail') DEFAULT NULL,
  `language_preference` varchar(16) DEFAULT NULL COMMENT 'Preferred communication language',
  `notes` text COMMENT 'Additional notes',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_id` (`contact_information_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
