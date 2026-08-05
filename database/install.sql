-- =============================================================================
-- AtoM Framework + AHG Plugins - Complete Schema
-- Generated: 2025-12-29 14:22:32
-- Custom tables: 295
-- =============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
-- Table: atom_extension
CREATE TABLE IF NOT EXISTS `atom_extension` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'GPL-3.0',
  `status` VARCHAR(57) COLLATE utf8mb4_unicode_ci DEFAULT 'installed' COMMENT 'installed, enabled, disabled, pending_removal',
  `protection_level` VARCHAR(42) COLLATE utf8mb4_unicode_ci DEFAULT 'extension' COMMENT 'core, system, theme, extension',
  `theme_support` json DEFAULT NULL,
  `requires_framework` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_atom` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_php` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dependencies` json DEFAULT NULL,
  `optional_dependencies` json DEFAULT NULL,
  `tables_created` json DEFAULT NULL,
  `shared_tables` json DEFAULT NULL,
  `helpers` json DEFAULT NULL,
  `install_task` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uninstall_task` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installed_at` datetime DEFAULT NULL,
  `enabled_at` datetime DEFAULT NULL,
  `disabled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_machine_name` (`machine_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_extension_admin
CREATE TABLE IF NOT EXISTS `atom_extension_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int NOT NULL,
  `admin_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_params` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `badge_callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '100',
  `is_enabled` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_key` (`admin_key`),
  KEY `fk_admin_extension` (`extension_id`),
  CONSTRAINT `fk_admin_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_extension_audit
CREATE TABLE IF NOT EXISTS `atom_extension_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int DEFAULT NULL,
  `extension_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` VARCHAR(157) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'discovered, installed, enabled, disabled, uninstalled, upgraded, downgraded, backup_created, backup_restored, data_deleted, config_changed, error',
  `performed_by` int DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_extension_name` (`extension_name`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_extension_menu
CREATE TABLE IF NOT EXISTS `atom_extension_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int NOT NULL,
  `menu_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_location` VARCHAR(45) COLLATE utf8mb4_unicode_ci DEFAULT 'main' COMMENT 'main, admin, user, footer, mobile',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_i18n` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_params` json DEFAULT NULL,
  `badge_callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_cache_ttl` int DEFAULT '60',
  `visibility_callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `context` json DEFAULT NULL,
  `sort_order` int DEFAULT '100',
  `is_enabled` tinyint(1) DEFAULT '1',
  `is_separator` tinyint(1) DEFAULT '0',
  `css_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_key` (`menu_key`),
  KEY `fk_menu_extension` (`extension_id`),
  CONSTRAINT `fk_menu_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_extension_pending_deletion
CREATE TABLE IF NOT EXISTS `atom_extension_pending_deletion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_count` int DEFAULT '0',
  `backup_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `backup_size` bigint DEFAULT NULL,
  `delete_after` datetime NOT NULL,
  `status` VARCHAR(69) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, processing, deleted, restored, cancelled, failed',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_extension_name` (`extension_name`),
  KEY `idx_status` (`status`),
  KEY `idx_delete_after` (`delete_after`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_extension_setting
CREATE TABLE IF NOT EXISTS `atom_extension_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int DEFAULT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` VARCHAR(49) COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'string, integer, boolean, json, array',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_extension_setting` (`extension_id`,`setting_key`),
  CONSTRAINT `fk_setting_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_extension_widget
CREATE TABLE IF NOT EXISTS `atom_extension_widget` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int NOT NULL,
  `widget_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `widget_type` VARCHAR(55) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'stat_card, chart, list, table, html, custom',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_callback` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dashboard` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'central',
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_ttl` int DEFAULT '300',
  `sort_order` int DEFAULT '100',
  `is_enabled` tinyint(1) DEFAULT '1',
  `config` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_widget_key` (`widget_key`),
  KEY `fk_widget_extension` (`extension_id`),
  CONSTRAINT `fk_widget_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_framework_migrations
CREATE TABLE IF NOT EXISTS `atom_framework_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- Table: atom_migrations
CREATE TABLE IF NOT EXISTS `atom_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_plugin
CREATE TABLE IF NOT EXISTS `atom_plugin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'ahg',
  `is_enabled` tinyint(1) DEFAULT '0',
  `is_core` tinyint(1) DEFAULT '0',
  `is_locked` tinyint(1) DEFAULT '0',
  `status` VARCHAR(57) DEFAULT 'enabled' COMMENT 'installed, enabled, disabled, pending_removal',
  `load_order` int DEFAULT '100',
  `plugin_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `record_check_query` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SQL query to check if plugin has associated records',
  `enabled_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_is_enabled` (`is_enabled`),
  KEY `idx_category` (`category`),
  KEY `idx_load_order` (`load_order`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_plugin_audit
CREATE TABLE IF NOT EXISTS `atom_plugin_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(255) NOT NULL,
  `action` varchar(50) NOT NULL,
  `previous_state` varchar(50) DEFAULT NULL,
  `new_state` varchar(50) DEFAULT NULL,
  `reason` text,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin` (`plugin_name`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- Table: atom_plugin_dependency
CREATE TABLE IF NOT EXISTS `atom_plugin_dependency` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` bigint unsigned NOT NULL,
  `requires_plugin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_optional` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plugin_dependency` (`plugin_id`,`requires_plugin`),
  KEY `idx_requires_plugin` (`requires_plugin`),
  CONSTRAINT `atom_plugin_dependency_ibfk_1` FOREIGN KEY (`plugin_id`) REFERENCES `atom_plugin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: atom_plugin_hook
CREATE TABLE IF NOT EXISTS `atom_plugin_hook` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` bigint unsigned NOT NULL,
  `event_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listener_class` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listener_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `idx_event_name` (`event_name`),
  KEY `idx_event_active` (`event_name`,`is_active`),
  CONSTRAINT `atom_plugin_hook_ibfk_1` FOREIGN KEY (`plugin_id`) REFERENCES `atom_plugin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: information_object_physical_location
CREATE TABLE IF NOT EXISTS `information_object_physical_location` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int NOT NULL,
  `physical_object_id` int DEFAULT NULL COMMENT 'Link to physical_object container',
  `shelf` varchar(50) DEFAULT NULL,
  `row` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `box_number` varchar(50) DEFAULT NULL,
  `folder_number` varchar(50) DEFAULT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `extent_value` decimal(10,2) DEFAULT NULL,
  `extent_unit` varchar(50) DEFAULT NULL COMMENT 'items, pages, cm, etc',
  `condition_status` VARCHAR(49) DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical',
  `condition_notes` text,
  `access_status` VARCHAR(59) DEFAULT 'available' COMMENT 'available, in_use, restricted, offsite, missing',
  `last_accessed_at` datetime DEFAULT NULL,
  `accessed_by` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_info_object` (`information_object_id`),
  KEY `idx_physical_object` (`physical_object_id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_access_status` (`access_status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- Table: level_of_description_sector
CREATE TABLE IF NOT EXISTS `level_of_description_sector` (
  `id` int NOT NULL AUTO_INCREMENT,
  `term_id` int NOT NULL,
  `sector` varchar(50) NOT NULL,
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_term_sector` (`term_id`,`sector`),
  KEY `idx_sector` (`sector`),
  KEY `idx_term` (`term_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- =====================================================
-- Default Level of Description Sector Mappings
-- Uses name lookup to handle different term IDs across installations
-- =====================================================
-- Archive sector (ISAD standard levels)
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Record group';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Fonds';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Subfonds';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Collection';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Series';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Subseries';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 70, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'File';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 80, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Item';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 90, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Part';
-- Museum sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = '3D Model';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artifact';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artwork';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Installation';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Object';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Specimen';
-- Library sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Book';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Monograph';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Periodical';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Journal';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 45, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Article';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Manuscript';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Document';
-- Gallery sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artwork';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Photograph';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Installation';
-- DAM sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Photograph';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Audio';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Video';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Image';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Document';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = '3D Model';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 70, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Dataset';
-- Table: workflow_history
CREATE TABLE IF NOT EXISTS `workflow_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_instance_id` bigint unsigned NOT NULL,
  `from_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wh_instance` (`workflow_instance_id`),
  KEY `idx_wh_created` (`created_at`),
  CONSTRAINT `workflow_history_workflow_instance_id_foreign` FOREIGN KEY (`workflow_instance_id`) REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table: workflow_instance
CREATE TABLE IF NOT EXISTS `workflow_instance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `current_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_complete` tinyint(1) NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wi_workflow` (`workflow_id`),
  KEY `idx_wi_entity` (`entity_type`,`entity_id`),
  KEY `idx_wi_state` (`current_state`),
  KEY `idx_wi_complete` (`is_complete`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
-- Migration tracking table
CREATE TABLE IF NOT EXISTS atom_migration (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL UNIQUE,
  batch INT NOT NULL DEFAULT 1,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- =============================================================================
-- SEED DATA: Required AHG Plugins (DO NOT REMOVE)
-- =============================================================================
-- Required plugins into atom_plugin (for Symfony/AtoM loading)
INSERT INTO atom_plugin (name, class_name, is_enabled, is_core, is_locked, load_order, category, created_at, updated_at)
VALUES
('ahgCorePlugin', 'ahgCorePluginConfiguration', 1, 1, 1, 5, 'ahg', NOW(), NOW()),
('ahgThemeB5Plugin', 'ahgThemeB5PluginConfiguration', 0, 1, 1, 10, 'theme', NOW(), NOW()),
('ahgSecurityClearancePlugin', 'ahgSecurityClearancePluginConfiguration', 1, 1, 1, 20, 'ahg', NOW(), NOW()),
('ahgDisplayPlugin', 'ahgDisplayPluginConfiguration', 1, 1, 1, 30, 'ahg', NOW(), NOW())
ON DUPLICATE KEY UPDATE is_core = 1, is_locked = 1;
-- Required plugins into atom_extension (for extension manager)
INSERT INTO atom_extension (machine_name, display_name, version, description, status, protection_level, installed_at, enabled_at, created_at)
VALUES
('ahgCorePlugin', 'AHG Core', '1.0.0', 'Core framework components required by all AHG plugins', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgThemeB5Plugin', 'AHG Bootstrap 5 Theme', '1.0.0', 'AHG Bootstrap 5 theme with enhanced UI', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgSecurityClearancePlugin', 'Security Clearance', '1.0.0', 'Security classification system for records', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgDisplayPlugin', 'Display Mode Manager', '1.0.0', 'Display mode switching for GLAM sectors', 'enabled', 'system', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE protection_level = 'system';

-- ahg_settings is the AHG-wide settings store, read by AhgSettingsService. Fifteen
-- framework files and thirty-five plugins depend on it, so it belongs here and not
-- to any one plugin. It was briefly assigned to ahgMultiTenantPlugin when the
-- installer was split per plugin, which left it uncreated on a fresh install -
-- ahgMultiTenantPlugin ships disabled.
CREATE TABLE IF NOT EXISTS ahg_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(49) COMMENT 'string, integer, boolean, json, float' DEFAULT 'string',
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    description VARCHAR(500),
    is_sensitive TINYINT(1) DEFAULT 0,
    updated_by INT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_setting_group (setting_group),
    FOREIGN KEY (updated_by) REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which rows in AtoM's `menu` table a plugin created.
--
-- Without this, removal has to be re-derived from whatever the manifest currently
-- declares - so dropping an entry from a manifest, or renaming one, leaves the old
-- row behind for good. Ownership is recorded when the row is created, so disabling
-- a plugin removes exactly what it added regardless of what the manifest says now.
--
-- Deliberately not atom_extension_menu: that is an unused parallel menu design with
-- its own routes and permissions, keyed to atom_extension, and a plugin enabled
-- through atom_plugin alone has no row there to reference.
CREATE TABLE IF NOT EXISTS `atom_plugin_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(255) NOT NULL,
  `menu_id` int NOT NULL COMMENT 'menu.id of the row this plugin created',
  `menu_name` varchar(255) NOT NULL COMMENT 'the name it was created with',
  `menu_path` varchar(255) NOT NULL COMMENT 'the path it was created with',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_menu` (`plugin_name`, `menu_id`),
  KEY `idx_plugin` (`plugin_name`),
  KEY `idx_menu` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
