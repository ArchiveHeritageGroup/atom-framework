-- =============================================================================
-- AtoM Framework + AHG Plugins - Complete Schema
-- Generated: 2025-12-29 14:22:32
-- Custom tables: 295
-- =============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- Table: access_audit_log
CREATE TABLE IF NOT EXISTS `access_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  `access_level` varchar(50) DEFAULT 'full',
  `denial_reasons` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_granted` (`granted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_justification_template
CREATE TABLE IF NOT EXISTS `access_justification_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `paia_section` varchar(50) DEFAULT NULL,
  `template_text` text NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request
CREATE TABLE IF NOT EXISTS `access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_type` enum('clearance','object','repository','authority','researcher') DEFAULT 'clearance',
  `scope_type` enum('single','with_children','collection','repository_all') DEFAULT 'single',
  `user_id` int unsigned NOT NULL,
  `requested_classification_id` int unsigned NOT NULL,
  `current_classification_id` int unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `justification` text,
  `urgency` enum('low','normal','high','critical') DEFAULT 'normal',
  `status` enum('pending','approved','denied','cancelled','expired') DEFAULT 'pending',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_classification` (`requested_classification_id`),
  KEY `idx_reviewed_by` (`reviewed_by`),
  KEY `current_classification_id` (`current_classification_id`),
  CONSTRAINT `access_request_ibfk_1` FOREIGN KEY (`requested_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `access_request_ibfk_2` FOREIGN KEY (`current_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_approver
CREATE TABLE IF NOT EXISTS `access_request_approver` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `min_classification_level` int unsigned DEFAULT '0',
  `max_classification_level` int unsigned DEFAULT '5',
  `email_notifications` tinyint(1) DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_justification
CREATE TABLE IF NOT EXISTS `access_request_justification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `justification_text` text NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_log
CREATE TABLE IF NOT EXISTS `access_request_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int unsigned NOT NULL,
  `action` enum('created','updated','approved','denied','cancelled','expired','escalated') NOT NULL,
  `actor_id` int unsigned DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_actor_id` (`actor_id`),
  CONSTRAINT `access_request_log_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_scope
CREATE TABLE IF NOT EXISTS `access_request_scope` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int unsigned NOT NULL,
  `object_type` enum('information_object','repository','actor') NOT NULL,
  `object_id` int unsigned NOT NULL,
  `include_descendants` tinyint(1) DEFAULT '0',
  `object_title` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_object` (`object_type`,`object_id`),
  CONSTRAINT `access_request_scope_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: actor_face_index
CREATE TABLE IF NOT EXISTS `actor_face_index` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int NOT NULL,
  `face_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to cropped face image',
  `source_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original image path',
  `bounding_box` json DEFAULT NULL COMMENT '{"x":0,"y":0,"width":100,"height":100}',
  `face_encoding` blob COMMENT 'Face embedding vector for similarity matching',
  `encoding_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Version of encoding algorithm',
  `confidence` float DEFAULT '1',
  `detection_backend` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'local/aws/azure/google',
  `attributes` json DEFAULT NULL COMMENT 'Age, gender, emotions, etc.',
  `landmarks` json DEFAULT NULL COMMENT 'Facial landmarks',
  `is_primary` tinyint(1) DEFAULT '0' COMMENT 'Primary face for this actor',
  `is_active` tinyint(1) DEFAULT '1',
  `is_verified` tinyint(1) DEFAULT '0' COMMENT 'Human verified match',
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verified_by` (`verified_by`),
  KEY `created_by` (`created_by`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_verified` (`is_verified`),
  KEY `idx_backend` (`detection_backend`),
  CONSTRAINT `actor_face_index_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `actor` (`id`) ON DELETE CASCADE,
  CONSTRAINT `actor_face_index_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `actor_face_index_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: agreement_rights_vocabulary
CREATE TABLE IF NOT EXISTS `agreement_rights_vocabulary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` enum('usage','restriction','condition','license') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_rights_category` (`category`),
  KEY `idx_rights_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: agreement_type
CREATE TABLE IF NOT EXISTS `agreement_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `prefix` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'AGR',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#6c757d',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_audit_access
CREATE TABLE IF NOT EXISTS `ahg_audit_access` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_classification` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_clearance_level` int unsigned DEFAULT NULL,
  `clearance_verified` tinyint(1) NOT NULL DEFAULT '0',
  `file_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `denial_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_access_uuid` (`uuid`),
  KEY `idx_ahg_access_user` (`user_id`),
  KEY `idx_ahg_access_type` (`access_type`),
  KEY `idx_ahg_access_entity` (`entity_type`,`entity_id`),
  KEY `idx_ahg_access_security` (`security_classification`),
  KEY `idx_ahg_access_created` (`created_at`),
  CONSTRAINT `fk_ahg_audit_access_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_audit_authentication
CREATE TABLE IF NOT EXISTS `ahg_audit_authentication` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_attempts` int unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_auth_uuid` (`uuid`),
  KEY `idx_ahg_auth_user` (`user_id`),
  KEY `idx_ahg_auth_event` (`event_type`),
  KEY `idx_ahg_auth_ip` (`ip_address`),
  KEY `idx_ahg_auth_created` (`created_at`),
  CONSTRAINT `fk_ahg_audit_auth_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_audit_log
CREATE TABLE IF NOT EXISTS `ahg_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_uri` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `changed_fields` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `security_classification` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `culture_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_audit_uuid` (`uuid`),
  KEY `idx_ahg_audit_user` (`user_id`),
  KEY `idx_ahg_audit_action` (`action`),
  KEY `idx_ahg_audit_entity_type` (`entity_type`),
  KEY `idx_ahg_audit_entity_id` (`entity_id`),
  KEY `idx_ahg_audit_created` (`created_at`),
  KEY `idx_ahg_audit_status` (`status`),
  KEY `idx_ahg_audit_ip` (`ip_address`),
  KEY `idx_ahg_audit_security` (`security_classification`),
  KEY `idx_ahg_audit_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_ahg_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5718 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_audit_retention_policy
CREATE TABLE IF NOT EXISTS `ahg_audit_retention_policy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `log_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `retention_days` int unsigned NOT NULL DEFAULT '2555',
  `archive_before_delete` tinyint(1) NOT NULL DEFAULT '1',
  `archive_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_cleanup_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_retention_type` (`log_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_audit_settings
CREATE TABLE IF NOT EXISTS `ahg_audit_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_settings
CREATE TABLE IF NOT EXISTS `ahg_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','integer','boolean','json','float') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `setting_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_sensitive` tinyint(1) DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_setting_group` (`setting_group`),
  KEY `idx_setting_key` (`setting_key`),
  CONSTRAINT `ahg_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=894 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_contacts
CREATE TABLE IF NOT EXISTS `ahg_vendor_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_vendor` (`vendor_id`),
  KEY `idx_contact_primary` (`is_primary`),
  CONSTRAINT `ahg_vendor_contacts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_metrics
CREATE TABLE IF NOT EXISTS `ahg_vendor_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `year` int NOT NULL,
  `month` int DEFAULT NULL,
  `total_transactions` int DEFAULT '0',
  `completed_transactions` int DEFAULT '0',
  `on_time_returns` int DEFAULT '0',
  `late_returns` int DEFAULT '0',
  `total_items_handled` int DEFAULT '0',
  `total_value_handled` decimal(15,2) DEFAULT '0.00',
  `total_cost` decimal(15,2) DEFAULT '0.00',
  `avg_turnaround_days` decimal(5,1) DEFAULT NULL,
  `avg_quality_score` decimal(3,2) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vendor_period` (`vendor_id`,`year`,`month`),
  KEY `idx_vm_vendor` (`vendor_id`),
  KEY `idx_vm_year` (`year`),
  CONSTRAINT `ahg_vendor_metrics_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_service_types
CREATE TABLE IF NOT EXISTS `ahg_vendor_service_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `requires_insurance` tinyint(1) DEFAULT '0',
  `requires_valuation` tinyint(1) DEFAULT '0',
  `typical_duration_days` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_service_slug` (`slug`),
  KEY `idx_service_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_services
CREATE TABLE IF NOT EXISTS `ahg_vendor_services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `service_type_id` int NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `fixed_rate` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_preferred` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vendor_service` (`vendor_id`,`service_type_id`),
  KEY `idx_vs_vendor` (`vendor_id`),
  KEY `idx_vs_service` (`service_type_id`),
  CONSTRAINT `ahg_vendor_services_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ahg_vendor_services_ibfk_2` FOREIGN KEY (`service_type_id`) REFERENCES `ahg_vendor_service_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transaction_attachments
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `attachment_type` enum('quote','invoice','condition_report','photo','receipt','certificate','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ta_transaction` (`transaction_id`),
  KEY `idx_ta_type` (`attachment_type`),
  CONSTRAINT `ahg_vendor_transaction_attachments_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ahg_vendor_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transaction_history
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `status_from` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_th_transaction` (`transaction_id`),
  KEY `idx_th_date` (`created_at`),
  CONSTRAINT `ahg_vendor_transaction_history_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ahg_vendor_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transaction_items
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `item_title` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_before` text COLLATE utf8mb4_unicode_ci,
  `condition_before_rating` enum('excellent','good','fair','poor','critical') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_after` text COLLATE utf8mb4_unicode_ci,
  `condition_after_rating` enum('excellent','good','fair','poor','critical') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declared_value` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `service_description` text COLLATE utf8mb4_unicode_ci,
  `service_completed` tinyint(1) DEFAULT '0',
  `service_notes` text COLLATE utf8mb4_unicode_ci,
  `item_cost` decimal(10,2) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ti_transaction` (`transaction_id`),
  KEY `idx_ti_object` (`information_object_id`),
  KEY `idx_ti_completed` (`service_completed`),
  CONSTRAINT `ahg_vendor_transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ahg_vendor_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transactions
CREATE TABLE IF NOT EXISTS `ahg_vendor_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` int NOT NULL,
  `service_type_id` int NOT NULL,
  `status` enum('pending_approval','approved','dispatched','received_by_vendor','in_progress','completed','ready_for_collection','returned','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending_approval',
  `request_date` date NOT NULL,
  `approval_date` date DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `requested_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `dispatched_by` int DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `quote_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `payment_status` enum('not_invoiced','invoiced','paid','disputed') COLLATE utf8mb4_unicode_ci DEFAULT 'not_invoiced',
  `payment_date` date DEFAULT NULL,
  `total_insured_value` decimal(15,2) DEFAULT NULL,
  `insurance_arranged` tinyint(1) DEFAULT '0',
  `insurance_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `courier_company` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispatch_notes` text COLLATE utf8mb4_unicode_ci,
  `vendor_notes` text COLLATE utf8mb4_unicode_ci,
  `return_notes` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `has_quotes` tinyint(1) DEFAULT '0',
  `has_invoices` tinyint(1) DEFAULT '0',
  `has_condition_reports` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  KEY `idx_trans_number` (`transaction_number`),
  KEY `idx_trans_vendor` (`vendor_id`),
  KEY `idx_trans_service` (`service_type_id`),
  KEY `idx_trans_status` (`status`),
  KEY `idx_trans_dispatch` (`dispatch_date`),
  KEY `idx_trans_expected` (`expected_return_date`),
  KEY `idx_trans_payment` (`payment_status`),
  CONSTRAINT `ahg_vendor_transactions_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`),
  CONSTRAINT `ahg_vendor_transactions_ibfk_2` FOREIGN KEY (`service_type_id`) REFERENCES `ahg_vendor_service_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendors
CREATE TABLE IF NOT EXISTS `ahg_vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_type` enum('company','individual','institution','government') COLLATE utf8mb4_unicode_ci DEFAULT 'company',
  `registration_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'South Africa',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_alt` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_insurance` tinyint(1) DEFAULT '0',
  `insurance_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_policy_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_expiry_date` date DEFAULT NULL,
  `insurance_coverage_amount` decimal(15,2) DEFAULT NULL,
  `quality_rating` tinyint DEFAULT NULL COMMENT '1-5 stars',
  `reliability_rating` tinyint DEFAULT NULL COMMENT '1-5 stars',
  `price_rating` tinyint DEFAULT NULL COMMENT '1-5 stars',
  `status` enum('active','inactive','suspended','pending_approval') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_preferred` tinyint(1) DEFAULT '0',
  `is_bbbee_compliant` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `vendor_code` (`vendor_code`),
  KEY `idx_vendor_name` (`name`),
  KEY `idx_vendor_slug` (`slug`),
  KEY `idx_vendor_code` (`vendor_code`),
  KEY `idx_vendor_status` (`status`),
  KEY `idx_vendor_type` (`vendor_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension
CREATE TABLE IF NOT EXISTS `atom_extension` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'GPL-3.0',
  `status` enum('installed','enabled','disabled','pending_removal') COLLATE utf8mb4_unicode_ci DEFAULT 'installed',
  `protection_level` enum('core','system','theme','extension') COLLATE utf8mb4_unicode_ci DEFAULT 'extension',
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
  `action` enum('discovered','installed','enabled','disabled','uninstalled','upgraded','downgraded','backup_created','backup_restored','data_deleted','config_changed','error') COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `menu_location` enum('main','admin','user','footer','mobile') COLLATE utf8mb4_unicode_ci DEFAULT 'main',
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
  `status` enum('pending','processing','deleted','restored','cancelled','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
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
  `setting_type` enum('string','integer','boolean','json','array') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
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
  `widget_type` enum('stat_card','chart','list','table','html','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
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

-- Table: atom_isbn_cache
CREATE TABLE IF NOT EXISTS `atom_isbn_cache` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `isbn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isbn_10` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn_13` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json NOT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'worldcat',
  `oclc_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_isbn` (`isbn`),
  KEY `idx_isbn_10` (`isbn_10`),
  KEY `idx_isbn_13` (`isbn_13`),
  KEY `idx_oclc` (`oclc_number`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_isbn_lookup_audit
CREATE TABLE IF NOT EXISTS `atom_isbn_lookup_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `isbn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `information_object_id` int DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `fields_populated` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `lookup_time_ms` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_isbn` (`isbn`),
  KEY `idx_user` (`user_id`),
  KEY `idx_io` (`information_object_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_isbn_audit_io` FOREIGN KEY (`information_object_id`) REFERENCES `information_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_isbn_audit_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_isbn_provider
CREATE TABLE IF NOT EXISTS `atom_isbn_provider` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key_setting` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reference to atom_setting key',
  `priority` int NOT NULL DEFAULT '100',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `rate_limit_per_minute` int unsigned DEFAULT NULL,
  `response_format` enum('json','xml','marcxml') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_enabled_priority` (`enabled`,`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_block
CREATE TABLE IF NOT EXISTS `atom_landing_block` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL,
  `block_type_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL,
  `css_classes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `container_type` enum('fluid','container','container-lg') COLLATE utf8mb4_unicode_ci DEFAULT 'container',
  `background_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padding_top` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '3',
  `padding_bottom` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '3',
  `position` int DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parent_block_id` int DEFAULT NULL,
  `column_slot` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `block_type_id` (`block_type_id`),
  KEY `idx_page_position` (`page_id`,`position`),
  KEY `idx_parent` (`parent_block_id`),
  CONSTRAINT `atom_landing_block_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `atom_landing_page` (`id`) ON DELETE CASCADE,
  CONSTRAINT `atom_landing_block_ibfk_2` FOREIGN KEY (`block_type_id`) REFERENCES `atom_landing_block_type` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `atom_landing_block_ibfk_3` FOREIGN KEY (`parent_block_id`) REFERENCES `atom_landing_block` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_block_type
CREATE TABLE IF NOT EXISTS `atom_landing_block_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'bi-square',
  `template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_config` json DEFAULT NULL,
  `config_schema` json DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_name` (`machine_name`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_page
CREATE TABLE IF NOT EXISTS `atom_landing_page` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_default` (`is_default`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_page_audit
CREATE TABLE IF NOT EXISTS `atom_landing_page_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int DEFAULT NULL,
  `block_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_page` (`page_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_page_version
CREATE TABLE IF NOT EXISTS `atom_landing_page_version` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL,
  `version_number` int NOT NULL,
  `blocks_snapshot` json NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `published_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_page_version` (`page_id`,`version_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `atom_landing_page_version_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `atom_landing_page` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `status` enum('installed','enabled','disabled','pending_removal') DEFAULT 'enabled',
  `load_order` int DEFAULT '100',
  `plugin_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
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

-- Table: backup_history
CREATE TABLE IF NOT EXISTS `backup_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `backup_id` varchar(100) NOT NULL,
  `backup_path` varchar(500) NOT NULL,
  `backup_type` enum('full','database','files','incremental') DEFAULT 'full',
  `status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `size_bytes` bigint DEFAULT '0',
  `components` json DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `backup_id` (`backup_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: backup_schedule
CREATE TABLE IF NOT EXISTS `backup_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `frequency` enum('hourly','daily','weekly','monthly') DEFAULT 'daily',
  `time` time DEFAULT '02:00:00',
  `day_of_week` tinyint DEFAULT NULL,
  `day_of_month` tinyint DEFAULT NULL,
  `include_database` tinyint(1) DEFAULT '1',
  `include_uploads` tinyint(1) DEFAULT '1',
  `include_plugins` tinyint(1) DEFAULT '1',
  `include_framework` tinyint(1) DEFAULT '1',
  `retention_days` int DEFAULT '30',
  `is_active` tinyint(1) DEFAULT '1',
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: backup_setting
CREATE TABLE IF NOT EXISTS `backup_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: cart
CREATE TABLE IF NOT EXISTS `cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) DEFAULT NULL,
  `archival_description_id` varchar(50) DEFAULT NULL,
  `archival_description` varchar(1024) DEFAULT NULL,
  `slug` varchar(1024) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=900678 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_assessment_schedule
CREATE TABLE IF NOT EXISTS `condition_assessment_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `frequency_months` int DEFAULT '12',
  `last_assessment_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_due` (`next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_conservation_link
CREATE TABLE IF NOT EXISTS `condition_conservation_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `condition_event_id` int unsigned NOT NULL,
  `treatment_id` int unsigned NOT NULL,
  `link_type` varchar(50) DEFAULT 'treatment',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`condition_event_id`),
  KEY `idx_treatment` (`treatment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_damage
CREATE TABLE IF NOT EXISTS `condition_damage` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `condition_report_id` bigint unsigned NOT NULL,
  `damage_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'overall',
  `severity` enum('minor','moderate','severe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'minor',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dimensions` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `treatment_required` tinyint(1) NOT NULL DEFAULT '0',
  `treatment_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cd_report` (`condition_report_id`),
  KEY `idx_cd_type` (`damage_type`),
  KEY `idx_cd_severity` (`severity`),
  CONSTRAINT `condition_damage_condition_report_id_foreign` FOREIGN KEY (`condition_report_id`) REFERENCES `condition_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_event
CREATE TABLE IF NOT EXISTS `condition_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'assessment',
  `event_date` date NOT NULL,
  `assessor` varchar(255) DEFAULT NULL,
  `condition_status` varchar(50) DEFAULT NULL,
  `damage_types` json DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `notes` text,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`event_date`),
  KEY `idx_status` (`condition_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_image
CREATE TABLE IF NOT EXISTS `condition_image` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `condition_report_id` bigint unsigned NOT NULL,
  `digital_object_id` int unsigned DEFAULT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_type` enum('general','detail','damage','before','after','raking','uv') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `annotations` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ci_report` (`condition_report_id`),
  CONSTRAINT `condition_image_condition_report_id_foreign` FOREIGN KEY (`condition_report_id`) REFERENCES `condition_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_report
CREATE TABLE IF NOT EXISTS `condition_report` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `assessor_user_id` int unsigned DEFAULT NULL,
  `assessment_date` date NOT NULL,
  `context` enum('acquisition','loan_out','loan_in','loan_return','exhibition','storage','conservation','routine','incident','insurance','deaccession') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'routine',
  `overall_rating` enum('excellent','good','fair','poor','unacceptable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recommendations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `priority` enum('low','normal','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `next_check_date` date DEFAULT NULL,
  `environmental_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `storage_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cr_object` (`information_object_id`),
  KEY `idx_cr_date` (`assessment_date`),
  KEY `idx_cr_rating` (`overall_rating`),
  KEY `idx_cr_next_check` (`next_check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_vocabulary
CREATE TABLE IF NOT EXISTS `condition_vocabulary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vocabulary_type` enum('damage_type','severity','condition','priority','material','location_zone') COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'For UI display',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'FontAwesome icon class',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_code` (`vocabulary_type`,`code`),
  KEY `idx_type_active` (`vocabulary_type`,`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_vocabulary_term
CREATE TABLE IF NOT EXISTS `condition_vocabulary_term` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vocabulary_type` varchar(50) NOT NULL,
  `term_code` varchar(50) NOT NULL,
  `term_label` varchar(255) NOT NULL,
  `term_description` text,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vocab_term` (`vocabulary_type`,`term_code`),
  KEY `idx_type` (`vocabulary_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: contact_information_extended
CREATE TABLE IF NOT EXISTS `contact_information_extended` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_information_id` int NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mr, Mrs, Dr, Prof, etc.',
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Job title/position',
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Department/Division',
  `cell` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mobile/Cell phone',
  `id_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID/Passport number',
  `alternative_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Secondary email',
  `alternative_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Secondary phone',
  `preferred_contact_method` enum('email','phone','cell','fax','mail') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_preference` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Preferred communication language',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Additional notes',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_id` (`contact_information_id`),
  CONSTRAINT `fk_contact_info_ext` FOREIGN KEY (`contact_information_id`) REFERENCES `contact_information` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: creative_commons_license
CREATE TABLE IF NOT EXISTS `creative_commons_license` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '4.0',
  `allows_adaptation` tinyint(1) DEFAULT '1',
  `allows_commercial` tinyint(1) DEFAULT '1',
  `requires_attribution` tinyint(1) DEFAULT '1',
  `requires_sharealike` tinyint(1) DEFAULT '0',
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_uri` (`uri`),
  UNIQUE KEY `uq_cc_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: creative_commons_license_i18n
CREATE TABLE IF NOT EXISTS `creative_commons_license_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creative_commons_license_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_i18n` (`creative_commons_license_id`,`culture`),
  KEY `idx_cc_i18n_parent` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: custom_watermark
CREATE TABLE IF NOT EXISTS `custom_watermark` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned DEFAULT NULL COMMENT 'NULL = global watermark',
  `name` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `position` varchar(50) DEFAULT 'center',
  `opacity` decimal(3,2) DEFAULT '0.40',
  `created_by` int unsigned DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: dam_iptc_metadata
CREATE TABLE IF NOT EXISTS `dam_iptc_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `creator` varchar(255) DEFAULT NULL,
  `creator_job_title` varchar(255) DEFAULT NULL,
  `creator_address` text,
  `creator_city` varchar(255) DEFAULT NULL,
  `creator_state` varchar(255) DEFAULT NULL,
  `creator_postal_code` varchar(50) DEFAULT NULL,
  `creator_country` varchar(255) DEFAULT NULL,
  `creator_phone` varchar(100) DEFAULT NULL,
  `creator_email` varchar(255) DEFAULT NULL,
  `creator_website` varchar(500) DEFAULT NULL,
  `headline` varchar(500) DEFAULT NULL,
  `caption` text,
  `keywords` text,
  `iptc_subject_code` varchar(255) DEFAULT NULL,
  `intellectual_genre` varchar(255) DEFAULT NULL,
  `iptc_scene` varchar(255) DEFAULT NULL,
  `date_created` date DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state_province` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `sublocation` varchar(500) DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `job_id` varchar(255) DEFAULT NULL,
  `instructions` text,
  `credit_line` varchar(500) DEFAULT NULL,
  `source` varchar(500) DEFAULT NULL,
  `copyright_notice` text,
  `rights_usage_terms` text,
  `license_type` enum('rights_managed','royalty_free','creative_commons','public_domain','editorial','other') DEFAULT NULL,
  `license_url` varchar(500) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `model_release_status` enum('none','not_applicable','unlimited','limited') DEFAULT 'none',
  `model_release_id` varchar(255) DEFAULT NULL,
  `property_release_status` enum('none','not_applicable','unlimited','limited') DEFAULT 'none',
  `property_release_id` varchar(255) DEFAULT NULL,
  `artwork_title` varchar(500) DEFAULT NULL,
  `artwork_creator` varchar(255) DEFAULT NULL,
  `artwork_date` varchar(100) DEFAULT NULL,
  `artwork_source` varchar(500) DEFAULT NULL,
  `artwork_copyright` text,
  `persons_shown` text,
  `camera_make` varchar(100) DEFAULT NULL,
  `camera_model` varchar(100) DEFAULT NULL,
  `lens` varchar(255) DEFAULT NULL,
  `focal_length` varchar(50) DEFAULT NULL,
  `aperture` varchar(20) DEFAULT NULL,
  `shutter_speed` varchar(50) DEFAULT NULL,
  `iso_speed` int DEFAULT NULL,
  `flash_used` tinyint(1) DEFAULT NULL,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `gps_altitude` decimal(10,2) DEFAULT NULL,
  `image_width` int DEFAULT NULL,
  `image_height` int DEFAULT NULL,
  `resolution_x` int DEFAULT NULL,
  `resolution_y` int DEFAULT NULL,
  `resolution_unit` varchar(20) DEFAULT NULL,
  `color_space` varchar(50) DEFAULT NULL,
  `bit_depth` int DEFAULT NULL,
  `orientation` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_creator` (`creator`),
  KEY `idx_keywords` (`keywords`(255)),
  KEY `idx_date_created` (`date_created`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: digital_object_faces
CREATE TABLE IF NOT EXISTS `digital_object_faces` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `face_index` int DEFAULT '0' COMMENT 'Face number in image (0-based)',
  `face_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bounding_box` json DEFAULT NULL,
  `confidence` float DEFAULT '0',
  `matched_actor_id` int DEFAULT NULL,
  `match_similarity` float DEFAULT NULL,
  `match_verified` tinyint(1) DEFAULT '0',
  `alternative_matches` json DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `is_identified` tinyint(1) DEFAULT '0',
  `identification_source` enum('auto','manual','verified') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `identified_by` int DEFAULT NULL,
  `identified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `identified_by` (`identified_by`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_matched_actor` (`matched_actor_id`),
  KEY `idx_identified` (`is_identified`),
  KEY `idx_confidence` (`confidence`),
  CONSTRAINT `digital_object_faces_ibfk_1` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `digital_object_faces_ibfk_2` FOREIGN KEY (`matched_actor_id`) REFERENCES `actor` (`id`) ON DELETE SET NULL,
  CONSTRAINT `digital_object_faces_ibfk_3` FOREIGN KEY (`identified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: digital_object_metadata
CREATE TABLE IF NOT EXISTS `digital_object_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `file_type` enum('image','pdf','office','video','audio','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_metadata` json DEFAULT NULL COMMENT 'Complete raw metadata as extracted',
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `copyright` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_created` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_width` int DEFAULT NULL,
  `image_height` int DEFAULT NULL,
  `camera_make` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `gps_altitude` decimal(10,2) DEFAULT NULL,
  `page_count` int DEFAULT NULL,
  `word_count` int DEFAULT NULL,
  `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `application` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` decimal(12,3) DEFAULT NULL COMMENT 'Duration in seconds',
  `duration_formatted` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frame_rate` decimal(6,2) DEFAULT NULL,
  `bitrate` int DEFAULT NULL,
  `sample_rate` int DEFAULT NULL,
  `channels` int DEFAULT NULL,
  `artist` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_number` int DEFAULT NULL,
  `genre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extraction_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `extraction_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extraction_errors` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_creator` (`creator`),
  KEY `idx_date_created` (`date_created`),
  KEY `idx_gps` (`gps_latitude`,`gps_longitude`),
  CONSTRAINT `digital_object_metadata_ibfk_1` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: display_collection_type
CREATE TABLE IF NOT EXISTS `display_collection_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `default_profile_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_collection_type_i18n
CREATE TABLE IF NOT EXISTS `display_collection_type_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dcti_type` FOREIGN KEY (`id`) REFERENCES `display_collection_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_field
CREATE TABLE IF NOT EXISTS `display_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `field_group` enum('identity','description','context','access','technical','admin') DEFAULT 'description',
  `data_type` enum('text','textarea','date','daterange','number','select','multiselect','relation','file','actor','term') DEFAULT 'text',
  `source_table` varchar(100) DEFAULT NULL,
  `source_column` varchar(100) DEFAULT NULL,
  `source_i18n` tinyint(1) DEFAULT '0',
  `property_type_id` int DEFAULT NULL,
  `taxonomy_id` int DEFAULT NULL,
  `relation_type_id` int DEFAULT NULL,
  `event_type_id` int DEFAULT NULL,
  `isad_element` varchar(50) DEFAULT NULL,
  `spectrum_unit` varchar(50) DEFAULT NULL,
  `dc_element` varchar(50) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_field_i18n
CREATE TABLE IF NOT EXISTS `display_field_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `help_text` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dfi_field` FOREIGN KEY (`id`) REFERENCES `display_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_level
CREATE TABLE IF NOT EXISTS `display_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_code` varchar(30) DEFAULT NULL,
  `domain` varchar(20) DEFAULT 'universal',
  `valid_parent_codes` json DEFAULT NULL,
  `valid_child_codes` json DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `atom_term_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_level_i18n
CREATE TABLE IF NOT EXISTS `display_level_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dli_level` FOREIGN KEY (`id`) REFERENCES `display_level` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_mode_global
CREATE TABLE IF NOT EXISTS `display_mode_global` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` enum('asc','desc') COLLATE utf8mb4_unicode_ci DEFAULT 'desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` enum('small','medium','large') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `available_modes` json DEFAULT NULL COMMENT 'JSON array of enabled modes for this module',
  `allow_user_override` tinyint(1) DEFAULT '1' COMMENT 'Allow users to change from default',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module` (`module`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: display_object_config
CREATE TABLE IF NOT EXISTS `display_object_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `object_type` varchar(30) DEFAULT 'archive',
  `primary_profile_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`object_type`),
  CONSTRAINT `fk_doc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_object_profile
CREATE TABLE IF NOT EXISTS `display_object_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `profile_id` int NOT NULL,
  `context` varchar(30) DEFAULT 'default',
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`object_id`,`profile_id`,`context`),
  KEY `idx_object` (`object_id`),
  KEY `fk_dop_profile` (`profile_id`),
  CONSTRAINT `fk_dop_profile` FOREIGN KEY (`profile_id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_profile
CREATE TABLE IF NOT EXISTS `display_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `domain` varchar(20) DEFAULT NULL,
  `layout_mode` enum('detail','hierarchy','grid','gallery','list','card','masonry','catalog') DEFAULT 'detail',
  `thumbnail_size` enum('none','small','medium','large','hero','full') DEFAULT 'medium',
  `thumbnail_position` enum('left','right','top','background','inline') DEFAULT 'left',
  `identity_fields` json DEFAULT NULL,
  `description_fields` json DEFAULT NULL,
  `context_fields` json DEFAULT NULL,
  `access_fields` json DEFAULT NULL,
  `technical_fields` json DEFAULT NULL,
  `hidden_fields` json DEFAULT NULL,
  `field_labels` json DEFAULT NULL,
  `available_actions` json DEFAULT NULL,
  `css_class` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_profile_i18n
CREATE TABLE IF NOT EXISTS `display_profile_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dpi_profile` FOREIGN KEY (`id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: donor_agreement
CREATE TABLE IF NOT EXISTS `donor_agreement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agreement_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `donor_id` int unsigned DEFAULT NULL,
  `actor_id` int unsigned DEFAULT NULL,
  `accession_id` int unsigned DEFAULT NULL,
  `information_object_id` int unsigned DEFAULT NULL,
  `repository_id` int unsigned DEFAULT NULL,
  `agreement_type_id` int unsigned NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `donor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_contact_info` text COLLATE utf8mb4_unicode_ci,
  `institution_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_contact_info` text COLLATE utf8mb4_unicode_ci,
  `legal_representative` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_representative_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_representative_contact` text COLLATE utf8mb4_unicode_ci,
  `repository_representative` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repository_representative_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','pending_review','pending_signature','active','expired','terminated','superseded') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `agreement_date` date DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `termination_reason` text COLLATE utf8mb4_unicode_ci,
  `has_financial_terms` tinyint(1) DEFAULT '0',
  `purchase_amount` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `payment_terms` text COLLATE utf8mb4_unicode_ci,
  `scope_description` text COLLATE utf8mb4_unicode_ci,
  `extent_statement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `received_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `general_terms` text COLLATE utf8mb4_unicode_ci,
  `special_conditions` text COLLATE utf8mb4_unicode_ci,
  `donor_signature_date` date DEFAULT NULL,
  `donor_signature_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repository_signature_date` date DEFAULT NULL,
  `repository_signature_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_date` date DEFAULT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `is_template` tinyint(1) DEFAULT '0',
  `parent_agreement_id` int DEFAULT NULL,
  `supersedes_agreement_id` int DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_agreement_number` (`agreement_number`),
  KEY `idx_donor` (`donor_id`),
  KEY `idx_accession` (`accession_id`),
  KEY `idx_io` (`information_object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`effective_date`,`expiry_date`),
  KEY `idx_review` (`review_date`),
  KEY `idx_agreement_type` (`agreement_type_id`),
  KEY `idx_parent_agreement` (`parent_agreement_id`),
  KEY `idx_supersedes_agreement` (`supersedes_agreement_id`),
  CONSTRAINT `fk_donor_agreement_agreement_type` FOREIGN KEY (`agreement_type_id`) REFERENCES `agreement_type` (`id`),
  CONSTRAINT `fk_donor_agreement_parent` FOREIGN KEY (`parent_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donor_agreement_supersedes` FOREIGN KEY (`supersedes_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_accession
CREATE TABLE IF NOT EXISTS `donor_agreement_accession` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `accession_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `linked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `linked_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daa` (`donor_agreement_id`,`accession_id`),
  KEY `idx_daa_accession` (`accession_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_classification
CREATE TABLE IF NOT EXISTS `donor_agreement_classification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `term_id` int NOT NULL,
  `applies_to_all` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dac` (`donor_agreement_id`,`term_id`),
  KEY `idx_dac_term` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_document
CREATE TABLE IF NOT EXISTS `donor_agreement_document` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `document_type` enum('signed_agreement','draft','amendment','addendum','schedule','correspondence','appraisal_report','inventory','deed_of_gift','transfer_form','receipt','payment_record','legal_opinion','board_resolution','donor_id','provenance_evidence','valuation','insurance','photo','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `checksum_md5` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `document_date` date DEFAULT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_signed` tinyint(1) DEFAULT '0',
  `signature_date` date DEFAULT NULL,
  `signed_by` text COLLATE utf8mb4_unicode_ci,
  `is_confidential` tinyint(1) DEFAULT '0',
  `access_restriction` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_type` (`document_type`),
  CONSTRAINT `fk_donor_agreement_document_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_history
CREATE TABLE IF NOT EXISTS `donor_agreement_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agreement_id` int NOT NULL,
  `action` enum('created','updated','status_changed','approved','renewed','terminated','document_added','document_removed','record_linked','record_unlinked','reminder_sent','note_added') COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_agreement` (`agreement_id`),
  KEY `idx_history_action` (`action`),
  KEY `idx_history_date` (`created_at`),
  CONSTRAINT `fk_agreement_history` FOREIGN KEY (`agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_i18n
CREATE TABLE IF NOT EXISTS `donor_agreement_i18n` (
  `id` int NOT NULL,
  `culture` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `restrictions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attribution_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `internal_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_donor_agreement_i18n` FOREIGN KEY (`id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_record
CREATE TABLE IF NOT EXISTS `donor_agreement_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agreement_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `relationship_type` enum('covers','partially_covers','references') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'covers',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_agreement_record` (`agreement_id`,`information_object_id`),
  KEY `idx_record_agreement` (`agreement_id`),
  KEY `idx_record_io` (`information_object_id`),
  CONSTRAINT `fk_agreement_record_agreement` FOREIGN KEY (`agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_agreement_record_io` FOREIGN KEY (`information_object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_reminder
CREATE TABLE IF NOT EXISTS `donor_agreement_reminder` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `reminder_type` enum('expiry_warning','review_due','renewal_required','restriction_ending','payment_due','donor_contact','anniversary','audit','preservation_check','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `reminder_date` date NOT NULL,
  `advance_days` int DEFAULT '30',
  `is_recurring` tinyint(1) DEFAULT '0',
  `recurrence_pattern` enum('daily','weekly','monthly','quarterly','yearly') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `notify_email` tinyint(1) DEFAULT '1',
  `notify_system` tinyint(1) DEFAULT '1',
  `notification_recipients` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','snoozed','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `snooze_until` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int unsigned DEFAULT NULL,
  `completion_notes` text COLLATE utf8mb4_unicode_ci,
  `action_required` text COLLATE utf8mb4_unicode_ci,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_sent` tinyint(1) DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_date` (`reminder_date`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  CONSTRAINT `fk_donor_agreement_reminder_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_reminder_log
CREATE TABLE IF NOT EXISTS `donor_agreement_reminder_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_reminder_id` int unsigned NOT NULL,
  `sent_at` datetime NOT NULL,
  `sent_to` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `notification_method` enum('email','system','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sent','failed','bounced') COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int unsigned DEFAULT NULL,
  `response_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminder` (`donor_agreement_reminder_id`),
  CONSTRAINT `fk_donor_agreement_reminder_log_reminder` FOREIGN KEY (`donor_agreement_reminder_id`) REFERENCES `donor_agreement_reminder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_restriction
CREATE TABLE IF NOT EXISTS `donor_agreement_restriction` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `restriction_type` enum('closure','partial_closure','redaction','permission_only','researcher_only','onsite_only','no_copying','no_publication','anonymization','time_embargo','review_required','security_clearance','popia_restricted','legal_hold','cultural_protocol') COLLATE utf8mb4_unicode_ci NOT NULL,
  `applies_to_all` tinyint(1) DEFAULT '1',
  `specific_materials` text COLLATE utf8mb4_unicode_ci,
  `box_list` text COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `auto_release` tinyint(1) DEFAULT '0',
  `release_date` date DEFAULT NULL,
  `release_trigger` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_be_overridden` tinyint(1) DEFAULT '0',
  `override_authority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `legal_basis` text COLLATE utf8mb4_unicode_ci,
  `popia_category` enum('special_personal','personal','children','criminal','biometric') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_subject_consent` tinyint(1) DEFAULT NULL,
  `security_clearance_level` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_type` (`restriction_type`),
  KEY `idx_release` (`release_date`,`auto_release`),
  CONSTRAINT `fk_donor_agreement_restriction_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_right
CREATE TABLE IF NOT EXISTS `donor_agreement_right` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `right_type` enum('replicate','migrate','modify','use','disseminate','delete','display','publish','digitize','reproduce','loan','exhibit','broadcast','commercial_use','derivative_works') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` enum('granted','restricted','prohibited','conditional') COLLATE utf8mb4_unicode_ci NOT NULL,
  `conditions` text COLLATE utf8mb4_unicode_ci,
  `applies_to_digital` tinyint(1) DEFAULT '1',
  `applies_to_physical` tinyint(1) DEFAULT '1',
  `applies_to_metadata` tinyint(1) DEFAULT '1',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `requires_donor_approval` tinyint(1) DEFAULT '0',
  `requires_fee` tinyint(1) DEFAULT '0',
  `fee_structure` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_type` (`right_type`),
  CONSTRAINT `fk_donor_agreement_right_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_agreement_rights
CREATE TABLE IF NOT EXISTS `donor_agreement_rights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `extended_rights_id` bigint unsigned DEFAULT NULL,
  `embargo_id` bigint unsigned DEFAULT NULL,
  `applies_to` enum('all_items','specific_items','digital_only','physical_only','metadata_only') COLLATE utf8mb4_unicode_ci DEFAULT 'all_items',
  `auto_apply` tinyint(1) DEFAULT '1',
  `inherit_to_children` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dar_agreement` (`donor_agreement_id`),
  KEY `idx_dar_rights` (`extended_rights_id`),
  KEY `idx_dar_embargo` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_provenance
CREATE TABLE IF NOT EXISTS `donor_provenance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donor_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `donor_agreement_id` int DEFAULT NULL,
  `agreement_item_id` int DEFAULT NULL,
  `relationship_type` enum('donated','deposited','loaned','purchased','transferred','bequeathed','gifted') COLLATE utf8mb4_unicode_ci DEFAULT 'donated',
  `provenance_date` date DEFAULT NULL,
  `sequence_number` int DEFAULT NULL,
  `is_current_owner` tinyint(1) DEFAULT '0',
  `custody_start_date` date DEFAULT NULL,
  `custody_end_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `source_of_acquisition` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_donor_provenance` (`donor_id`,`information_object_id`),
  KEY `idx_dp_donor` (`donor_id`),
  KEY `idx_dp_io` (`information_object_id`),
  KEY `idx_dp_agreement` (`donor_agreement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: donor_rights_application_log
CREATE TABLE IF NOT EXISTS `donor_rights_application_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `rights_type` enum('extended_rights','embargo','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  `extended_rights_id` bigint unsigned DEFAULT NULL,
  `embargo_id` bigint unsigned DEFAULT NULL,
  `action` enum('applied','removed','updated','inherited') COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_by` int DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_dral_agreement` (`donor_agreement_id`),
  KEY `idx_dral_io` (`information_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: email_setting
CREATE TABLE IF NOT EXISTS `email_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','email','number','boolean','textarea','password') DEFAULT 'text',
  `setting_group` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: embargo
CREATE TABLE IF NOT EXISTS `embargo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `embargo_type` enum('full','metadata_only','digital_object','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `is_perpetual` tinyint(1) DEFAULT '0',
  `status` enum('active','expired','lifted','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_by` int DEFAULT NULL,
  `lifted_by` int DEFAULT NULL,
  `lifted_at` timestamp NULL DEFAULT NULL,
  `lift_reason` text COLLATE utf8mb4_unicode_ci,
  `notify_on_expiry` tinyint(1) DEFAULT '1',
  `notify_days_before` int DEFAULT '30',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_embargo_object` (`object_id`),
  KEY `idx_embargo_status` (`object_id`,`status`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_object_active` (`object_id`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: embargo_audit
CREATE TABLE IF NOT EXISTS `embargo_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embargo_id` bigint unsigned NOT NULL,
  `action` enum('created','modified','lifted','extended','exception_added','exception_removed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emb_audit_embargo` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: embargo_exception
CREATE TABLE IF NOT EXISTS `embargo_exception` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embargo_id` bigint unsigned NOT NULL,
  `exception_type` enum('user','group','ip_range','repository') COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception_id` int DEFAULT NULL,
  `ip_range_start` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_range_end` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `granted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emb_exc_embargo` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: embargo_i18n
CREATE TABLE IF NOT EXISTS `embargo_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embargo_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `public_message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_embargo_i18n` (`embargo_id`,`culture`),
  KEY `idx_embargo_i18n_parent` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights
CREATE TABLE IF NOT EXISTS `extended_rights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `rights_statement_id` bigint unsigned DEFAULT NULL,
  `creative_commons_license_id` bigint unsigned DEFAULT NULL,
  `rights_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `rights_holder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rights_holder_uri` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ext_rights_object` (`object_id`),
  KEY `idx_ext_rights_rs` (`rights_statement_id`),
  KEY `idx_ext_rights_cc` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights_batch_log
CREATE TABLE IF NOT EXISTS `extended_rights_batch_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_count` int NOT NULL DEFAULT '0',
  `object_ids` json DEFAULT NULL,
  `data` json DEFAULT NULL,
  `results` json DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_performed_at` (`performed_at`),
  KEY `idx_performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights_i18n
CREATE TABLE IF NOT EXISTS `extended_rights_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `extended_rights_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `rights_note` text COLLATE utf8mb4_unicode_ci,
  `usage_conditions` text COLLATE utf8mb4_unicode_ci,
  `copyright_notice` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ext_rights_i18n` (`extended_rights_id`,`culture`),
  KEY `idx_ext_rights_i18n_parent` (`extended_rights_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights_tk_label
CREATE TABLE IF NOT EXISTS `extended_rights_tk_label` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `extended_rights_id` bigint unsigned NOT NULL,
  `tk_label_id` bigint unsigned NOT NULL,
  `community_id` int DEFAULT NULL,
  `community_note` text COLLATE utf8mb4_unicode_ci,
  `assigned_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ext_rights_tk` (`extended_rights_id`,`tk_label_id`),
  KEY `idx_ext_rights_tk_label` (`tk_label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: favorites
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) DEFAULT NULL,
  `archival_description_id` varchar(50) DEFAULT NULL,
  `archival_description` varchar(1024) DEFAULT NULL,
  `slug` varchar(1024) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=900676 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: feedback
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int NOT NULL,
  `feed_name` varchar(50) DEFAULT NULL,
  `feed_surname` varchar(50) DEFAULT NULL,
  `feed_phone` varchar(50) DEFAULT NULL,
  `feed_email` varchar(50) DEFAULT NULL,
  `feed_relationship` text,
  `parent_id` varchar(50) DEFAULT NULL,
  `feed_type_id` int DEFAULT NULL,
  `lft` int NOT NULL,
  `rgt` int NOT NULL,
  `source_culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `feedback_FK_1` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: feedback_i18n
CREATE TABLE IF NOT EXISTS `feedback_i18n` (
  `name` varchar(1024) DEFAULT NULL,
  `unique_identifier` varchar(1024) DEFAULT NULL,
  `remarks` text,
  `id` int NOT NULL,
  `object_id` text,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `status_id` int NOT NULL,
  `culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `feedback_i18n_FK_1` FOREIGN KEY (`id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_artist
CREATE TABLE IF NOT EXISTS `gallery_artist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int DEFAULT NULL,
  `display_name` varchar(255) NOT NULL,
  `sort_name` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `death_date` date DEFAULT NULL,
  `death_place` varchar(255) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `artist_type` enum('individual','collective','studio','anonymous') DEFAULT 'individual',
  `medium_specialty` text,
  `movement_style` text,
  `active_period` varchar(100) DEFAULT NULL,
  `represented` tinyint(1) DEFAULT '0',
  `representation_start` date DEFAULT NULL,
  `representation_end` date DEFAULT NULL,
  `representation_terms` text,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `exclusivity` tinyint(1) DEFAULT '0',
  `biography` text,
  `artist_statement` text,
  `cv` text,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `studio_address` text,
  `instagram` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_name` (`display_name`),
  KEY `idx_represented` (`represented`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_artist_bibliography
CREATE TABLE IF NOT EXISTS `gallery_artist_bibliography` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artist_id` int NOT NULL,
  `entry_type` enum('book','catalog','article','review','interview','thesis','website','video','other') DEFAULT 'article',
  `title` varchar(500) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `publication` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `volume` varchar(50) DEFAULT NULL,
  `issue` varchar(50) DEFAULT NULL,
  `pages` varchar(50) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_artist` (`artist_id`),
  KEY `idx_type` (`entry_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_artist_exhibition_history
CREATE TABLE IF NOT EXISTS `gallery_artist_exhibition_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artist_id` int NOT NULL,
  `exhibition_type` enum('solo','group','duo','retrospective','survey') DEFAULT 'group',
  `title` varchar(255) NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `curator` varchar(255) DEFAULT NULL,
  `catalog_published` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_artist` (`artist_id`),
  KEY `idx_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_exhibition
CREATE TABLE IF NOT EXISTS `gallery_exhibition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text,
  `curator` varchar(255) DEFAULT NULL,
  `exhibition_type` enum('permanent','temporary','traveling','virtual','pop-up') DEFAULT 'temporary',
  `status` enum('planning','confirmed','installing','open','closing','closed','cancelled') DEFAULT 'planning',
  `venue_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `opening_event_date` datetime DEFAULT NULL,
  `closing_event_date` datetime DEFAULT NULL,
  `target_audience` text,
  `themes` text,
  `budget` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `visitor_count` int DEFAULT '0',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_venue` (`venue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_exhibition_checklist
CREATE TABLE IF NOT EXISTS `gallery_exhibition_checklist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exhibition_id` int NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text,
  `category` enum('planning','design','marketing','installation','opening','operation','closing') DEFAULT 'planning',
  `assigned_to` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int DEFAULT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exhibition` (`exhibition_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_exhibition_object
CREATE TABLE IF NOT EXISTS `gallery_exhibition_object` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exhibition_id` int NOT NULL,
  `object_id` int NOT NULL,
  `space_id` int DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `section` varchar(255) DEFAULT NULL,
  `display_notes` text,
  `label_text` text,
  `installation_requirements` text,
  `installed_at` datetime DEFAULT NULL,
  `installed_by` int DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `condition_on_install` text,
  `condition_on_remove` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exhibit` (`exhibition_id`,`object_id`),
  KEY `idx_exhibition` (`exhibition_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_facility_report
CREATE TABLE IF NOT EXISTS `gallery_facility_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `report_type` enum('incoming','outgoing') NOT NULL,
  `institution_name` varchar(255) DEFAULT NULL,
  `building_age` int DEFAULT NULL,
  `construction_type` varchar(100) DEFAULT NULL,
  `fire_detection` tinyint(1) DEFAULT '0',
  `fire_suppression` tinyint(1) DEFAULT '0',
  `security_24hr` tinyint(1) DEFAULT '0',
  `security_guards` tinyint(1) DEFAULT '0',
  `cctv` tinyint(1) DEFAULT '0',
  `intrusion_detection` tinyint(1) DEFAULT '0',
  `climate_controlled` tinyint(1) DEFAULT '0',
  `temperature_range` varchar(50) DEFAULT NULL,
  `humidity_range` varchar(50) DEFAULT NULL,
  `light_levels` varchar(100) DEFAULT NULL,
  `uv_filtering` tinyint(1) DEFAULT '0',
  `trained_handlers` tinyint(1) DEFAULT '0',
  `loading_dock` tinyint(1) DEFAULT '0',
  `freight_elevator` tinyint(1) DEFAULT '0',
  `storage_available` tinyint(1) DEFAULT '0',
  `insurance_coverage` varchar(255) DEFAULT NULL,
  `completed_by` varchar(255) DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `approved` tinyint(1) DEFAULT '0',
  `approved_by` int DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_insurance_policy
CREATE TABLE IF NOT EXISTS `gallery_insurance_policy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `policy_number` varchar(100) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `policy_type` enum('all_risk','named_perils','transit','exhibition','permanent_collection') DEFAULT 'all_risk',
  `coverage_amount` decimal(14,2) DEFAULT NULL,
  `deductible` decimal(12,2) DEFAULT NULL,
  `premium` decimal(12,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_loan
CREATE TABLE IF NOT EXISTS `gallery_loan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(50) NOT NULL,
  `loan_type` enum('incoming','outgoing') NOT NULL,
  `status` enum('inquiry','requested','approved','agreed','in_transit_out','on_loan','in_transit_return','returned','cancelled','declined') DEFAULT 'inquiry',
  `purpose` varchar(255) DEFAULT NULL,
  `exhibition_id` int DEFAULT NULL,
  `institution_name` varchar(255) NOT NULL,
  `institution_address` text,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_fee` decimal(12,2) DEFAULT NULL,
  `insurance_value` decimal(12,2) DEFAULT NULL,
  `insurance_provider` varchar(255) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `special_conditions` text,
  `agreement_signed` tinyint(1) DEFAULT '0',
  `agreement_date` date DEFAULT NULL,
  `facility_report_received` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_number` (`loan_number`),
  KEY `idx_type` (`loan_type`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`loan_start_date`,`loan_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_loan_object
CREATE TABLE IF NOT EXISTS `gallery_loan_object` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `object_id` int NOT NULL,
  `insurance_value` decimal(12,2) DEFAULT NULL,
  `condition_out` text,
  `condition_out_date` date DEFAULT NULL,
  `condition_out_by` int DEFAULT NULL,
  `condition_return` text,
  `condition_return_date` date DEFAULT NULL,
  `condition_return_by` int DEFAULT NULL,
  `packing_instructions` text,
  `display_requirements` text,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_space
CREATE TABLE IF NOT EXISTS `gallery_space` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venue_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `area_sqm` decimal(10,2) DEFAULT NULL,
  `wall_length_m` decimal(10,2) DEFAULT NULL,
  `height_m` decimal(10,2) DEFAULT NULL,
  `lighting_type` varchar(100) DEFAULT NULL,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `max_weight_kg` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_venue` (`venue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_valuation
CREATE TABLE IF NOT EXISTS `gallery_valuation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_type` enum('insurance','market','replacement','auction_estimate','probate','donation') DEFAULT 'insurance',
  `value_amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'ZAR',
  `valuation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `appraiser_name` varchar(255) DEFAULT NULL,
  `appraiser_credentials` varchar(255) DEFAULT NULL,
  `appraiser_organization` varchar(255) DEFAULT NULL,
  `methodology` text,
  `comparables` text,
  `notes` text,
  `document_path` varchar(500) DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`valuation_type`),
  KEY `idx_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_venue
CREATE TABLE IF NOT EXISTS `gallery_venue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `address` text,
  `total_area_sqm` decimal(10,2) DEFAULT NULL,
  `max_capacity` int DEFAULT NULL,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `security_level` varchar(50) DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: getty_vocabulary_link
CREATE TABLE IF NOT EXISTS `getty_vocabulary_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `term_id` int unsigned NOT NULL,
  `vocabulary` enum('aat','tgn','ulan') NOT NULL,
  `getty_uri` varchar(255) NOT NULL,
  `getty_id` varchar(50) NOT NULL,
  `getty_pref_label` varchar(500) DEFAULT NULL,
  `getty_scope_note` text,
  `status` enum('confirmed','suggested','rejected','pending') NOT NULL DEFAULT 'pending',
  `confidence` decimal(3,2) NOT NULL DEFAULT '0.00',
  `confirmed_by_user_id` int unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term_getty` (`term_id`,`getty_uri`),
  KEY `idx_vocabulary` (`vocabulary`),
  KEY `idx_status` (`status`),
  KEY `idx_getty_id` (`getty_id`),
  KEY `idx_vocab_status` (`vocabulary`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: grap_compliance_check
CREATE TABLE IF NOT EXISTS `grap_compliance_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `check_date` datetime NOT NULL,
  `check_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('passed','failed','warning','not_applicable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`check_date`),
  KEY `idx_status` (`status`),
  KEY `idx_severity` (`severity`),
  CONSTRAINT `fk_grap_compliance_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: grap_financial_year_snapshot
CREATE TABLE IF NOT EXISTS `grap_financial_year_snapshot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `repository_id` int DEFAULT NULL,
  `financial_year_end` date NOT NULL,
  `asset_class` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_assets` int DEFAULT '0',
  `total_carrying_amount` decimal(18,2) DEFAULT '0.00',
  `total_impairment` decimal(18,2) DEFAULT '0.00',
  `total_revaluation_surplus` decimal(18,2) DEFAULT '0.00',
  `additions_count` int DEFAULT '0',
  `additions_value` decimal(18,2) DEFAULT '0.00',
  `disposals_count` int DEFAULT '0',
  `disposals_value` decimal(18,2) DEFAULT '0.00',
  `impairments_count` int DEFAULT '0',
  `impairments_value` decimal(18,2) DEFAULT '0.00',
  `revaluations_count` int DEFAULT '0',
  `snapshot_data` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_snapshot` (`repository_id`,`financial_year_end`,`asset_class`),
  KEY `idx_repo` (`repository_id`),
  KEY `idx_fy` (`financial_year_end`),
  KEY `idx_class` (`asset_class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: grap_heritage_asset
CREATE TABLE IF NOT EXISTS `grap_heritage_asset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `recognition_status` varchar(50) DEFAULT NULL,
  `recognition_status_reason` varchar(255) DEFAULT NULL,
  `recognition_date` date DEFAULT NULL,
  `measurement_basis` varchar(50) DEFAULT NULL,
  `acquisition_method` varchar(50) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `cost_of_acquisition` decimal(15,2) DEFAULT '0.00',
  `fair_value_at_acquisition` decimal(15,2) DEFAULT NULL,
  `nominal_value` decimal(15,2) DEFAULT '1.00',
  `donor_name` varchar(255) DEFAULT NULL,
  `donor_restrictions` text,
  `initial_carrying_amount` decimal(15,2) DEFAULT '0.00',
  `current_carrying_amount` decimal(15,2) DEFAULT '0.00',
  `last_valuation_date` date DEFAULT NULL,
  `last_valuation_amount` decimal(15,2) DEFAULT NULL,
  `valuation_method` varchar(50) DEFAULT NULL,
  `valuer_name` varchar(255) DEFAULT NULL,
  `valuer_credentials` varchar(255) DEFAULT NULL,
  `valuation_report_reference` varchar(255) DEFAULT NULL,
  `revaluation_frequency` varchar(50) DEFAULT NULL,
  `revaluation_surplus` decimal(15,2) DEFAULT '0.00',
  `depreciation_policy` varchar(50) DEFAULT NULL,
  `useful_life_years` int DEFAULT NULL,
  `residual_value` decimal(15,2) DEFAULT '0.00',
  `depreciation_method` varchar(50) DEFAULT NULL,
  `annual_depreciation` decimal(15,2) DEFAULT '0.00',
  `accumulated_depreciation` decimal(15,2) DEFAULT '0.00',
  `last_impairment_date` date DEFAULT NULL,
  `impairment_indicators` tinyint(1) DEFAULT '0',
  `impairment_indicators_details` text,
  `impairment_loss` decimal(15,2) DEFAULT '0.00',
  `recoverable_service_amount` decimal(15,2) DEFAULT NULL,
  `derecognition_date` date DEFAULT NULL,
  `derecognition_reason` varchar(50) DEFAULT NULL,
  `derecognition_proceeds` decimal(15,2) DEFAULT NULL,
  `gain_loss_on_derecognition` decimal(15,2) DEFAULT NULL,
  `asset_class` varchar(50) DEFAULT NULL,
  `asset_sub_class` varchar(100) DEFAULT NULL,
  `gl_account_code` varchar(50) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `fund_source` varchar(100) DEFAULT NULL,
  `budget_vote` varchar(50) DEFAULT NULL,
  `heritage_significance` varchar(50) DEFAULT NULL,
  `significance_statement` text,
  `restrictions_on_use` text,
  `restrictions_on_disposal` text,
  `conservation_requirements` text,
  `conservation_commitments` text,
  `insurance_required` tinyint(1) DEFAULT '1',
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `notes` text,
  `insurance_provider` varchar(255) DEFAULT NULL,
  `insurance_expiry_date` date DEFAULT NULL,
  `risk_assessment_date` date DEFAULT NULL,
  `risk_level` varchar(50) DEFAULT NULL,
  `current_location` varchar(255) DEFAULT NULL,
  `storage_conditions` text,
  `condition_rating` varchar(50) DEFAULT NULL,
  `last_condition_assessment` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `initial_recognition_date` date DEFAULT NULL,
  `initial_recognition_value` decimal(15,2) DEFAULT NULL,
  `acquisition_method_grap` varchar(50) DEFAULT NULL,
  `heritage_significance_rating` varchar(50) DEFAULT NULL,
  `restrictions_use_disposal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_revaluation_date` date DEFAULT NULL,
  `revaluation_amount` decimal(15,2) DEFAULT NULL,
  `insurance_coverage_required` decimal(15,2) DEFAULT NULL,
  `insurance_coverage_actual` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_io` (`object_id`),
  KEY `idx_recognition_status` (`recognition_status`),
  KEY `idx_asset_class` (`asset_class`),
  KEY `idx_gl_account` (`gl_account_code`),
  KEY `idx_cost_center` (`cost_center`),
  KEY `idx_acquisition_date` (`acquisition_date`),
  KEY `idx_valuation_date` (`last_valuation_date`),
  KEY `idx_heritage_significance` (`heritage_significance`),
  CONSTRAINT `grap_heritage_asset_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: grap_impairment_assessment
CREATE TABLE IF NOT EXISTS `grap_impairment_assessment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_heritage_asset_id` int NOT NULL,
  `assessment_date` date NOT NULL,
  `physical_damage` tinyint(1) DEFAULT '0',
  `physical_damage_details` text,
  `obsolescence` tinyint(1) DEFAULT '0',
  `obsolescence_details` text,
  `change_in_use` tinyint(1) DEFAULT '0',
  `change_in_use_details` text,
  `external_factors` tinyint(1) DEFAULT '0',
  `external_factors_details` text,
  `impairment_identified` tinyint(1) DEFAULT '0',
  `carrying_amount_before` decimal(15,2) DEFAULT NULL,
  `recoverable_service_amount` decimal(15,2) DEFAULT NULL,
  `impairment_loss` decimal(15,2) DEFAULT NULL,
  `carrying_amount_after` decimal(15,2) DEFAULT NULL,
  `reversal_applicable` tinyint(1) DEFAULT '0',
  `reversal_amount` decimal(15,2) DEFAULT NULL,
  `reversal_date` date DEFAULT NULL,
  `assessor_name` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grap_heritage_asset_id` (`grap_heritage_asset_id`),
  KEY `idx_assessment_date` (`assessment_date`),
  KEY `idx_impairment_identified` (`impairment_identified`),
  CONSTRAINT `grap_impairment_assessment_ibfk_1` FOREIGN KEY (`grap_heritage_asset_id`) REFERENCES `grap_heritage_asset` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: grap_journal_entry
CREATE TABLE IF NOT EXISTS `grap_journal_entry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_heritage_asset_id` int NOT NULL,
  `journal_date` date NOT NULL,
  `journal_number` varchar(50) DEFAULT NULL,
  `journal_type` enum('recognition','revaluation','depreciation','impairment','derecognition','adjustment','transfer') NOT NULL,
  `debit_account` varchar(50) NOT NULL,
  `debit_amount` decimal(15,2) NOT NULL,
  `credit_account` varchar(50) NOT NULL,
  `credit_amount` decimal(15,2) NOT NULL,
  `description` text,
  `reference_document` varchar(255) DEFAULT NULL,
  `fiscal_year` int DEFAULT NULL,
  `fiscal_period` int DEFAULT NULL,
  `posted` tinyint(1) DEFAULT '0',
  `posted_by` int DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `reversed` tinyint(1) DEFAULT '0',
  `reversal_journal_id` int DEFAULT NULL,
  `reversal_date` date DEFAULT NULL,
  `reversal_reason` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grap_heritage_asset_id` (`grap_heritage_asset_id`),
  KEY `idx_journal_date` (`journal_date`),
  KEY `idx_journal_type` (`journal_type`),
  KEY `idx_fiscal_year` (`fiscal_year`),
  KEY `idx_posted` (`posted`),
  CONSTRAINT `grap_journal_entry_ibfk_1` FOREIGN KEY (`grap_heritage_asset_id`) REFERENCES `grap_heritage_asset` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: grap_movement_register
CREATE TABLE IF NOT EXISTS `grap_movement_register` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_heritage_asset_id` int NOT NULL,
  `movement_date` date NOT NULL,
  `movement_type` enum('loan_out','loan_return','transfer','exhibition','conservation','storage_change','other') NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `reason` text,
  `authorized_by` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `condition_on_departure` enum('excellent','good','fair','poor') DEFAULT NULL,
  `condition_on_return` enum('excellent','good','fair','poor') DEFAULT NULL,
  `condition_notes` text,
  `insurance_confirmed` tinyint(1) DEFAULT '0',
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grap_heritage_asset_id` (`grap_heritage_asset_id`),
  KEY `idx_movement_date` (`movement_date`),
  KEY `idx_movement_type` (`movement_type`),
  CONSTRAINT `grap_movement_register_ibfk_1` FOREIGN KEY (`grap_heritage_asset_id`) REFERENCES `grap_heritage_asset` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: grap_spectrum_procedure_link
CREATE TABLE IF NOT EXISTS `grap_spectrum_procedure_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `grap_asset_id` int NOT NULL,
  `spectrum_procedure` varchar(50) NOT NULL COMMENT 'acquisition, loan_in, loan_out, movement, valuation, condition, deaccession',
  `spectrum_record_id` int NOT NULL,
  `link_type` enum('initial_recognition','subsequent_measurement','impairment','disposal','audit') NOT NULL,
  `link_date` date NOT NULL,
  `financial_impact` decimal(15,2) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grap` (`grap_asset_id`),
  KEY `idx_spectrum` (`spectrum_procedure`,`spectrum_record_id`),
  KEY `idx_type` (`link_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: grap_transaction_log
CREATE TABLE IF NOT EXISTS `grap_transaction_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `transaction_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` date DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `transaction_data` json DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_grap_trans_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: grap_valuation_history
CREATE TABLE IF NOT EXISTS `grap_valuation_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_heritage_asset_id` int NOT NULL,
  `valuation_date` date NOT NULL,
  `previous_value` decimal(15,2) DEFAULT NULL,
  `new_value` decimal(15,2) NOT NULL,
  `valuation_change` decimal(15,2) DEFAULT NULL,
  `valuation_method` enum('market_approach','cost_approach','income_approach','expert_opinion') DEFAULT NULL,
  `valuer_name` varchar(255) DEFAULT NULL,
  `valuer_credentials` varchar(255) DEFAULT NULL,
  `valuer_organization` varchar(255) DEFAULT NULL,
  `valuation_report_reference` varchar(255) DEFAULT NULL,
  `revaluation_surplus_change` decimal(15,2) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_valuation_date` (`valuation_date`),
  KEY `idx_grap_asset` (`grap_heritage_asset_id`),
  CONSTRAINT `grap_valuation_history_ibfk_1` FOREIGN KEY (`grap_heritage_asset_id`) REFERENCES `grap_heritage_asset` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_3d_manifest
CREATE TABLE IF NOT EXISTS `iiif_3d_manifest` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `manifest_json` longtext,
  `manifest_hash` varchar(64) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_id` (`model_id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `iiif_3d_manifest_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_annotation
CREATE TABLE IF NOT EXISTS `iiif_annotation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `canvas_id` int DEFAULT NULL,
  `target_canvas` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_selector` json DEFAULT NULL,
  `motivation` enum('commenting','tagging','describing','linking','transcribing','identifying','supplementing') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'commenting',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_canvas` (`target_canvas`(255)),
  KEY `idx_motivation` (`motivation`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_annotation_body
CREATE TABLE IF NOT EXISTS `iiif_annotation_body` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annotation_id` int NOT NULL,
  `body_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'TextualBody',
  `body_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body_format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text/plain',
  `body_language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `body_purpose` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_annotation` (`annotation_id`),
  CONSTRAINT `iiif_annotation_body_ibfk_1` FOREIGN KEY (`annotation_id`) REFERENCES `iiif_annotation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_collection
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_collection_i18n
CREATE TABLE IF NOT EXISTS `iiif_collection_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection_culture` (`collection_id`,`culture`),
  CONSTRAINT `iiif_collection_i18n_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_collection_item
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
  CONSTRAINT `iiif_collection_item_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE,
  CONSTRAINT `iiif_collection_item_ibfk_2` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_ocr_block
CREATE TABLE IF NOT EXISTS `iiif_ocr_block` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ocr_id` int NOT NULL,
  `page_number` int DEFAULT '1',
  `block_type` enum('word','line','paragraph','region') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'word',
  `text` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `x` int NOT NULL,
  `y` int NOT NULL,
  `width` int NOT NULL,
  `height` int NOT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `block_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_ocr` (`ocr_id`),
  KEY `idx_page` (`page_number`),
  KEY `idx_type` (`block_type`),
  KEY `idx_text` (`text`(100)),
  CONSTRAINT `iiif_ocr_block_ibfk_1` FOREIGN KEY (`ocr_id`) REFERENCES `iiif_ocr_text` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_ocr_text
CREATE TABLE IF NOT EXISTS `iiif_ocr_text` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `full_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `format` enum('plain','alto','hocr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'plain',
  `language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `confidence` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  FULLTEXT KEY `ft_text` (`full_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_viewer_settings
CREATE TABLE IF NOT EXISTS `iiif_viewer_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `condition_status` enum('excellent','good','fair','poor','critical') DEFAULT NULL,
  `condition_notes` text,
  `access_status` enum('available','in_use','restricted','offsite','missing') DEFAULT 'available',
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

-- Table: library_item
CREATE TABLE IF NOT EXISTS `library_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `material_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monograph' COMMENT 'monograph, serial, volume, issue, chapter, article, manuscript, map, pamphlet',
  `subtitle` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsibility_statement` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classification_scheme` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'dewey, lcc, udc, bliss, colon, custom',
  `classification_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dewey_decimal` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cutter_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shelf_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copy_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `volume_designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issn` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lccn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oclc_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `openlibrary_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `goodreads_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `librarything_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `openlibrary_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ebook_preview_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_url_original` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edition_statement` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_place` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_date` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_date` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printing` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagination` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dimensions` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `physical_details` text COLLATE utf8mb4_unicode_ci,
  `language` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accompanying_material` text COLLATE utf8mb4_unicode_ci,
  `series_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_issn` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subseries_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `general_note` text COLLATE utf8mb4_unicode_ci,
  `bibliography_note` text COLLATE utf8mb4_unicode_ci,
  `contents_note` text COLLATE utf8mb4_unicode_ci,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `target_audience` text COLLATE utf8mb4_unicode_ci,
  `system_requirements` text COLLATE utf8mb4_unicode_ci,
  `binding_note` text COLLATE utf8mb4_unicode_ci,
  `frequency` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `former_frequency` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numbering_peculiarities` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_start_date` date DEFAULT NULL,
  `publication_end_date` date DEFAULT NULL,
  `publication_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'current, ceased, suspended',
  `total_copies` smallint unsigned NOT NULL DEFAULT '1',
  `available_copies` smallint unsigned NOT NULL DEFAULT '1',
  `circulation_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available' COMMENT 'available, on_loan, processing, lost, withdrawn, reference',
  `cataloging_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cataloging_rules` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'aacr2, rda, isbd',
  `encoding_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: library_item_creator
CREATE TABLE IF NOT EXISTS `library_item_creator` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `library_item_id` bigint unsigned NOT NULL,
  `name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'author',
  `sort_order` int DEFAULT '0',
  `authority_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_library_item_id` (`library_item_id`),
  KEY `idx_name` (`name`(100)),
  CONSTRAINT `library_item_creator_ibfk_1` FOREIGN KEY (`library_item_id`) REFERENCES `library_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: library_item_subject
CREATE TABLE IF NOT EXISTS `library_item_subject` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `library_item_id` bigint unsigned NOT NULL,
  `heading` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'topic',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_library_item_id` (`library_item_id`),
  KEY `idx_heading` (`heading`(100)),
  CONSTRAINT `library_item_subject_ibfk_1` FOREIGN KEY (`library_item_id`) REFERENCES `library_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=329 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan
CREATE TABLE IF NOT EXISTS `loan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `loan_type` enum('out','in') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `purpose` enum('exhibition','research','conservation','photography','education','filming','long_term','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exhibition',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `partner_institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_contact_phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `request_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `insurance_type` enum('borrower','lender','shared','government','self') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrower',
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ZAR',
  `insurance_policy_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loan_fee` decimal(12,2) DEFAULT NULL,
  `loan_fee_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ZAR',
  `internal_approver_id` int unsigned DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_loan_number_unique` (`loan_number`),
  KEY `idx_loan_type` (`loan_type`),
  KEY `idx_loan_partner` (`partner_institution`),
  KEY `idx_loan_start` (`start_date`),
  KEY `idx_loan_end` (`end_date`),
  KEY `idx_loan_return` (`return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan_document
CREATE TABLE IF NOT EXISTS `loan_document` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `document_type` enum('agreement','facilities_report','condition_report','insurance_certificate','receipt','correspondence','photograph','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ld_loan` (`loan_id`),
  KEY `idx_ld_type` (`document_type`),
  CONSTRAINT `loan_document_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan_extension
CREATE TABLE IF NOT EXISTS `loan_extension` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `previous_end_date` date NOT NULL,
  `new_end_date` date NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_le_loan` (`loan_id`),
  CONSTRAINT `loan_extension_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan_object
CREATE TABLE IF NOT EXISTS `loan_object` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `information_object_id` int unsigned NOT NULL,
  `object_title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `object_identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `condition_report_id` bigint unsigned DEFAULT NULL,
  `special_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lo_loan` (`loan_id`),
  KEY `idx_lo_object` (`information_object_id`),
  CONSTRAINT `loan_object_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_chapters
CREATE TABLE IF NOT EXISTS `media_chapters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `media_metadata_id` int NOT NULL,
  `chapter_index` int NOT NULL,
  `start_time` decimal(12,3) NOT NULL,
  `end_time` decimal(12,3) DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_metadata` (`media_metadata_id`),
  CONSTRAINT `media_chapters_ibfk_1` FOREIGN KEY (`media_metadata_id`) REFERENCES `media_metadata` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_derivatives
CREATE TABLE IF NOT EXISTS `media_derivatives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `derivative_type` enum('thumbnail','poster','preview','waveform') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `derivative_index` int DEFAULT '0',
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_type` (`derivative_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_metadata
CREATE TABLE IF NOT EXISTS `media_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `media_type` enum('audio','video') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `duration` decimal(12,3) DEFAULT NULL,
  `bitrate` int DEFAULT NULL,
  `audio_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_sample_rate` int DEFAULT NULL,
  `audio_channels` int DEFAULT NULL,
  `audio_bits_per_sample` int DEFAULT NULL,
  `audio_channel_layout` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_width` int DEFAULT NULL,
  `video_height` int DEFAULT NULL,
  `video_frame_rate` decimal(10,3) DEFAULT NULL,
  `video_pixel_format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_aspect_ratio` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `artist` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `make` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `software` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_coordinates` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_metadata` json DEFAULT NULL,
  `consolidated_metadata` json DEFAULT NULL,
  `waveform_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extracted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_media_type` (`media_type`),
  KEY `idx_format` (`format`),
  FULLTEXT KEY `ft_tags` (`title`,`artist`,`album`,`genre`,`comment`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_processing_queue
CREATE TABLE IF NOT EXISTS `media_processing_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `task_type` enum('metadata_extraction','transcription','waveform','thumbnail') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_options` json DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `priority` int DEFAULT '0',
  `progress` int DEFAULT '0',
  `progress_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `retry_count` int DEFAULT '0',
  `max_retries` int DEFAULT '3',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_task_type` (`task_type`),
  KEY `idx_priority` (`priority` DESC),
  KEY `idx_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_processor_settings
CREATE TABLE IF NOT EXISTS `media_processor_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','integer','float','boolean','json') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `setting_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_snippets
CREATE TABLE IF NOT EXISTS `media_snippets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_time` decimal(10,3) NOT NULL,
  `end_time` decimal(10,3) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_do_id` (`digital_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: media_speakers
CREATE TABLE IF NOT EXISTS `media_speakers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transcription_id` int NOT NULL,
  `speaker_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `speaker_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_duration` decimal(12,3) DEFAULT NULL,
  `segment_count` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transcription` (`transcription_id`),
  CONSTRAINT `media_speakers_ibfk_1` FOREIGN KEY (`transcription_id`) REFERENCES `media_transcription` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_transcription
CREATE TABLE IF NOT EXISTS `media_transcription` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `full_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transcription_data` json DEFAULT NULL,
  `segment_count` int DEFAULT NULL,
  `duration` decimal(12,3) DEFAULT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `model_used` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vtt_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `txt_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_language` (`language`),
  FULLTEXT KEY `ft_text` (`full_text`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: metadata_extraction_log
CREATE TABLE IF NOT EXISTS `metadata_extraction_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int DEFAULT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `operation` enum('extract','face_detect','face_match','index_face','bulk') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('success','partial','failed','skipped') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_extracted` tinyint(1) DEFAULT '0',
  `faces_detected` int DEFAULT '0',
  `faces_matched` int DEFAULT '0',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `processing_time_ms` int DEFAULT NULL,
  `triggered_by` enum('upload','job','manual','api') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_operation` (`operation`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: museum_metadata
CREATE TABLE IF NOT EXISTS `museum_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `work_type` varchar(50) DEFAULT NULL,
  `object_type` varchar(255) DEFAULT NULL,
  `classification` varchar(255) DEFAULT NULL,
  `materials` text,
  `techniques` text,
  `measurements` varchar(255) DEFAULT NULL,
  `dimensions` varchar(255) DEFAULT NULL,
  `creation_date_earliest` date DEFAULT NULL,
  `creation_date_latest` date DEFAULT NULL,
  `inscription` text,
  `inscriptions` text,
  `condition_notes` text,
  `provenance` text,
  `style_period` varchar(255) DEFAULT NULL,
  `cultural_context` varchar(255) DEFAULT NULL,
  `current_location` text,
  `edition_description` text,
  `state_description` varchar(512) DEFAULT NULL,
  `state_identification` varchar(100) DEFAULT NULL,
  `facture_description` text,
  `technique_cco` varchar(512) DEFAULT NULL,
  `technique_qualifier` varchar(255) DEFAULT NULL,
  `orientation` varchar(100) DEFAULT NULL,
  `physical_appearance` text,
  `color` varchar(255) DEFAULT NULL,
  `shape` varchar(255) DEFAULT NULL,
  `condition_term` varchar(100) DEFAULT NULL,
  `condition_date` date DEFAULT NULL,
  `condition_description` text,
  `condition_agent` varchar(255) DEFAULT NULL,
  `treatment_type` varchar(255) DEFAULT NULL,
  `treatment_date` date DEFAULT NULL,
  `treatment_agent` varchar(255) DEFAULT NULL,
  `treatment_description` text,
  `inscription_transcription` text,
  `inscription_type` varchar(100) DEFAULT NULL,
  `inscription_location` varchar(255) DEFAULT NULL,
  `inscription_language` varchar(100) DEFAULT NULL,
  `inscription_translation` text,
  `mark_type` varchar(100) DEFAULT NULL,
  `mark_description` text,
  `mark_location` varchar(255) DEFAULT NULL,
  `related_work_type` varchar(100) DEFAULT NULL,
  `related_work_relationship` varchar(255) DEFAULT NULL,
  `related_work_label` varchar(512) DEFAULT NULL,
  `related_work_id` varchar(255) DEFAULT NULL,
  `current_location_repository` varchar(512) DEFAULT NULL,
  `current_location_geography` varchar(512) DEFAULT NULL,
  `current_location_coordinates` varchar(100) DEFAULT NULL,
  `current_location_ref_number` varchar(255) DEFAULT NULL,
  `creation_place` varchar(512) DEFAULT NULL,
  `creation_place_type` varchar(100) DEFAULT NULL,
  `discovery_place` varchar(512) DEFAULT NULL,
  `discovery_place_type` varchar(100) DEFAULT NULL,
  `provenance_text` text,
  `ownership_history` text,
  `legal_status` varchar(255) DEFAULT NULL,
  `rights_type` varchar(100) DEFAULT NULL,
  `rights_holder` varchar(512) DEFAULT NULL,
  `rights_date` varchar(100) DEFAULT NULL,
  `rights_remarks` text,
  `cataloger_name` varchar(255) DEFAULT NULL,
  `cataloging_date` date DEFAULT NULL,
  `cataloging_institution` varchar(512) DEFAULT NULL,
  `cataloging_remarks` text,
  `record_type` varchar(100) DEFAULT NULL,
  `record_level` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creator_identity` varchar(512) DEFAULT NULL,
  `creator_role` varchar(255) DEFAULT NULL,
  `creator_extent` varchar(255) DEFAULT NULL,
  `creator_qualifier` varchar(255) DEFAULT NULL,
  `creator_attribution` varchar(255) DEFAULT NULL,
  `creation_date_display` varchar(255) DEFAULT NULL,
  `creation_date_qualifier` varchar(100) DEFAULT NULL,
  `style` varchar(255) DEFAULT NULL,
  `period` varchar(255) DEFAULT NULL,
  `cultural_group` varchar(255) DEFAULT NULL,
  `movement` varchar(255) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `dynasty` varchar(255) DEFAULT NULL,
  `subject_indexing_type` varchar(100) DEFAULT NULL,
  `subject_display` text,
  `subject_extent` varchar(255) DEFAULT NULL,
  `historical_context` text,
  `architectural_context` text,
  `archaeological_context` text,
  `object_class` varchar(255) DEFAULT NULL,
  `object_category` varchar(255) DEFAULT NULL,
  `object_sub_category` varchar(255) DEFAULT NULL,
  `edition_number` varchar(100) DEFAULT NULL,
  `edition_size` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_object` (`object_id`),
  CONSTRAINT `museum_metadata_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_fixity_check
CREATE TABLE IF NOT EXISTS `oais_fixity_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `content_id` int DEFAULT NULL,
  `check_type` enum('md5','sha256','sha512') NOT NULL,
  `expected_value` varchar(128) NOT NULL,
  `actual_value` varchar(128) NOT NULL,
  `is_valid` tinyint(1) NOT NULL,
  `checked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `checked_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_is_valid` (`is_valid`),
  CONSTRAINT `oais_fixity_check_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_information_package
CREATE TABLE IF NOT EXISTS `oais_information_package` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_type` enum('SIP','AIP','DIP') NOT NULL,
  `package_id` varchar(255) NOT NULL,
  `object_id` int DEFAULT NULL COMMENT 'Link to information_object',
  `parent_package_id` int DEFAULT NULL COMMENT 'For DIP->AIP relationship',
  `status` enum('pending','ingesting','stored','preserved','disseminated','error') DEFAULT 'pending',
  `checksum_md5` varchar(32) DEFAULT NULL,
  `checksum_sha256` varchar(64) DEFAULT NULL,
  `checksum_sha512` varchar(128) DEFAULT NULL,
  `total_size` bigint DEFAULT '0',
  `file_count` int DEFAULT '0',
  `storage_location` varchar(500) DEFAULT NULL,
  `preservation_level` enum('bit','logical','semantic') DEFAULT 'bit',
  `retention_period` int DEFAULT NULL COMMENT 'Years to retain',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ingested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_id` (`package_id`),
  KEY `parent_package_id` (`parent_package_id`),
  KEY `idx_package_type` (`package_type`),
  KEY `idx_status` (`status`),
  KEY `idx_object_id` (`object_id`),
  CONSTRAINT `oais_information_package_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `oais_information_package_ibfk_2` FOREIGN KEY (`parent_package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_package_content
CREATE TABLE IF NOT EXISTS `oais_package_content` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint DEFAULT '0',
  `mime_type` varchar(100) DEFAULT NULL,
  `checksum_md5` varchar(32) DEFAULT NULL,
  `checksum_sha256` varchar(64) DEFAULT NULL,
  `pronom_puid` varchar(50) DEFAULT NULL COMMENT 'PRONOM format ID',
  `format_name` varchar(255) DEFAULT NULL,
  `format_version` varchar(50) DEFAULT NULL,
  `content_type` enum('content','metadata','manifest','signature') DEFAULT 'content',
  `is_original` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_pronom` (`pronom_puid`),
  CONSTRAINT `oais_package_content_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oais_package_content_ibfk_2` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_premis_event
CREATE TABLE IF NOT EXISTS `oais_premis_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `content_id` int DEFAULT NULL,
  `event_identifier` varchar(255) NOT NULL,
  `event_type` enum('capture','compression','creation','deaccession','decompression','decryption','deletion','digital_signature_validation','dissemination','encryption','fixity_check','format_identification','ingestion','message_digest_calculation','migration','normalization','replication','validation','virus_check') NOT NULL,
  `event_date_time` datetime NOT NULL,
  `event_detail` text,
  `event_outcome` enum('success','failure','warning') NOT NULL,
  `event_outcome_detail` text,
  `linking_agent_identifier` varchar(255) DEFAULT NULL,
  `linking_agent_role` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_event_date` (`event_date_time`),
  CONSTRAINT `oais_premis_event_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oais_premis_event_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `oais_package_content` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_preservation_policy
CREATE TABLE IF NOT EXISTS `oais_preservation_policy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `source_format_puid` varchar(50) DEFAULT NULL,
  `target_format_puid` varchar(50) DEFAULT NULL,
  `action_type` enum('migrate','normalize','emulate','preserve') NOT NULL,
  `priority` int DEFAULT '5',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_pronom_format
CREATE TABLE IF NOT EXISTS `oais_pronom_format` (
  `id` int NOT NULL AUTO_INCREMENT,
  `puid` varchar(50) NOT NULL COMMENT 'e.g., fmt/18 for PDF 1.4',
  `format_name` varchar(255) NOT NULL,
  `format_version` varchar(50) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `extensions` text COMMENT 'JSON array of extensions',
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `preservation_action_required` tinyint(1) DEFAULT '0',
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `puid` (`puid`),
  KEY `idx_puid` (`puid`),
  KEY `idx_risk` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_audit_log
CREATE TABLE IF NOT EXISTS `object_3d_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int DEFAULT NULL,
  `object_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` enum('upload','update','delete','view','ar_view','download','hotspot_add','hotspot_delete') NOT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_hotspot
CREATE TABLE IF NOT EXISTS `object_3d_hotspot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `hotspot_type` enum('annotation','info','link','damage','detail') DEFAULT 'annotation',
  `position_x` decimal(10,6) NOT NULL,
  `position_y` decimal(10,6) NOT NULL,
  `position_z` decimal(10,6) NOT NULL,
  `normal_x` decimal(10,6) DEFAULT '0.000000',
  `normal_y` decimal(10,6) DEFAULT '1.000000',
  `normal_z` decimal(10,6) DEFAULT '0.000000',
  `icon` varchar(50) DEFAULT 'info',
  `color` varchar(20) DEFAULT '#1a73e8',
  `link_url` varchar(500) DEFAULT NULL,
  `link_target` enum('_self','_blank') DEFAULT '_blank',
  `display_order` int DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `object_3d_hotspot_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_hotspot_i18n
CREATE TABLE IF NOT EXISTS `object_3d_hotspot_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hotspot_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hotspot_culture` (`hotspot_id`,`culture`),
  CONSTRAINT `object_3d_hotspot_i18n_ibfk_1` FOREIGN KEY (`hotspot_id`) REFERENCES `object_3d_hotspot` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_model
CREATE TABLE IF NOT EXISTS `object_3d_model` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `format` enum('glb','gltf','obj','fbx','stl','ply','usdz') DEFAULT 'glb',
  `vertex_count` int DEFAULT NULL,
  `face_count` int DEFAULT NULL,
  `texture_count` int DEFAULT NULL,
  `animation_count` int DEFAULT '0',
  `has_materials` tinyint(1) DEFAULT '1',
  `auto_rotate` tinyint(1) DEFAULT '1',
  `rotation_speed` decimal(3,2) DEFAULT '1.00',
  `camera_orbit` varchar(100) DEFAULT '0deg 75deg 105%',
  `min_camera_orbit` varchar(100) DEFAULT NULL,
  `max_camera_orbit` varchar(100) DEFAULT NULL,
  `field_of_view` varchar(20) DEFAULT '30deg',
  `exposure` decimal(3,2) DEFAULT '1.00',
  `shadow_intensity` decimal(3,2) DEFAULT '1.00',
  `shadow_softness` decimal(3,2) DEFAULT '1.00',
  `environment_image` varchar(255) DEFAULT NULL,
  `skybox_image` varchar(255) DEFAULT NULL,
  `background_color` varchar(20) DEFAULT '#f5f5f5',
  `ar_enabled` tinyint(1) DEFAULT '1',
  `ar_scale` varchar(20) DEFAULT 'auto',
  `ar_placement` enum('floor','wall') DEFAULT 'floor',
  `poster_image` varchar(500) DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_format` (`format`),
  KEY `idx_is_public` (`is_public`),
  CONSTRAINT `object_3d_model_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_model_i18n
CREATE TABLE IF NOT EXISTS `object_3d_model_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `alt_text` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model_culture` (`model_id`,`culture`),
  CONSTRAINT `object_3d_model_i18n_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_settings
CREATE TABLE IF NOT EXISTS `object_3d_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `auto_rotate` tinyint(1) DEFAULT '1',
  `rotation_speed` decimal(3,2) DEFAULT '1.00',
  `camera_orbit` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0deg 75deg 105%',
  `field_of_view` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '30deg',
  `exposure` decimal(3,2) DEFAULT '1.00',
  `shadow_intensity` decimal(3,2) DEFAULT '1.00',
  `background_color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#f5f5f5',
  `ar_enabled` tinyint(1) DEFAULT '1',
  `ar_scale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'auto',
  `ar_placement` enum('floor','wall') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'floor',
  `poster_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_3d_texture
CREATE TABLE IF NOT EXISTS `object_3d_texture` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `texture_type` enum('diffuse','normal','roughness','metallic','ao','emissive','environment') DEFAULT 'diffuse',
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `object_3d_texture_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_access_grant
CREATE TABLE IF NOT EXISTS `object_access_grant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `request_id` int unsigned DEFAULT NULL,
  `object_type` enum('information_object','repository','actor') NOT NULL,
  `object_id` int unsigned NOT NULL,
  `include_descendants` tinyint(1) DEFAULT '0',
  `access_level` enum('view','download','edit') DEFAULT 'view',
  `granted_by` int unsigned NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int unsigned DEFAULT NULL,
  `notes` text,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_object` (`object_type`,`object_id`),
  KEY `idx_active` (`active`),
  KEY `idx_request` (`request_id`),
  CONSTRAINT `object_access_grant_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_classification_history
CREATE TABLE IF NOT EXISTS `object_classification_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `previous_classification_id` int unsigned DEFAULT NULL,
  `new_classification_id` int unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `changed_by` int DEFAULT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_compartment
CREATE TABLE IF NOT EXISTS `object_compartment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `compartment_id` int unsigned NOT NULL,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_object_compartment` (`object_id`,`compartment_id`),
  KEY `idx_compartment` (`compartment_id`),
  CONSTRAINT `object_compartment_ibfk_1` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_creative_commons
CREATE TABLE IF NOT EXISTS `object_creative_commons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `creative_commons_license_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obj_cc` (`object_id`,`creative_commons_license_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_cc_id` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_declassification_schedule
CREATE TABLE IF NOT EXISTS `object_declassification_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `from_classification_id` int DEFAULT NULL,
  `to_classification_id` int DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `processed` tinyint(1) DEFAULT '0',
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_scheduled` (`scheduled_date`),
  KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_provenance
CREATE TABLE IF NOT EXISTS `object_provenance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `donor_id` int DEFAULT NULL,
  `acquisition_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `provenance_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_donor_id` (`donor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_rights_holder
CREATE TABLE IF NOT EXISTS `object_rights_holder` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `donor_id` int NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_donor_id` (`donor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_rights_statement
CREATE TABLE IF NOT EXISTS `object_rights_statement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `rights_statement_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obj_rs` (`object_id`,`rights_statement_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_rights_statement_id` (`rights_statement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_security_classification
CREATE TABLE IF NOT EXISTS `object_security_classification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `classified_by` int DEFAULT NULL,
  `classified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `review_date` date DEFAULT NULL,
  `declassify_date` date DEFAULT NULL,
  `declassify_to_id` int unsigned DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `inherit_to_children` tinyint(1) DEFAULT '1',
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_osc_object` (`object_id`),
  KEY `idx_osc_classification_review_declassify` (`classification_id`,`review_date`,`declassify_date`),
  KEY `idx_osc_assigned_by` (`assigned_by`),
  KEY `fk_osc_classified_by` (`classified_by`),
  CONSTRAINT `fk_osc_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_osc_classified_by` FOREIGN KEY (`classified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_osc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_tk_label
CREATE TABLE IF NOT EXISTS `object_tk_label` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `tk_label_id` bigint unsigned NOT NULL,
  `community_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_text` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obj_tk` (`object_id`,`tk_label_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_tk_label_id` (`tk_label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_watermark_setting
CREATE TABLE IF NOT EXISTS `object_watermark_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `watermark_enabled` tinyint(1) DEFAULT '1',
  `watermark_type_id` int unsigned DEFAULT NULL,
  `custom_watermark_id` int unsigned DEFAULT NULL,
  `position` varchar(50) DEFAULT 'center',
  `opacity` decimal(3,2) DEFAULT '0.40',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `watermark_type_id` (`watermark_type_id`),
  KEY `custom_watermark_id` (`custom_watermark_id`),
  CONSTRAINT `object_watermark_setting_ibfk_1` FOREIGN KEY (`watermark_type_id`) REFERENCES `watermark_type` (`id`) ON DELETE SET NULL,
  CONSTRAINT `object_watermark_setting_ibfk_2` FOREIGN KEY (`custom_watermark_id`) REFERENCES `custom_watermark` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: physical_object_extended
CREATE TABLE IF NOT EXISTS `physical_object_extended` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `physical_object_id` int NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `aisle` varchar(50) DEFAULT NULL,
  `bay` varchar(50) DEFAULT NULL,
  `rack` varchar(50) DEFAULT NULL,
  `shelf` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `reference_code` varchar(100) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `depth` decimal(10,2) DEFAULT NULL,
  `total_capacity` int unsigned DEFAULT NULL COMMENT 'Total slots/spaces available',
  `used_capacity` int unsigned DEFAULT '0' COMMENT 'Currently occupied',
  `available_capacity` int unsigned GENERATED ALWAYS AS ((`total_capacity` - `used_capacity`)) STORED,
  `capacity_unit` varchar(50) DEFAULT NULL COMMENT 'boxes, files, metres, items etc',
  `total_linear_metres` decimal(10,2) DEFAULT NULL,
  `used_linear_metres` decimal(10,2) DEFAULT '0.00',
  `available_linear_metres` decimal(10,2) GENERATED ALWAYS AS ((`total_linear_metres` - `used_linear_metres`)) STORED,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `temperature_min` decimal(5,2) DEFAULT NULL,
  `temperature_max` decimal(5,2) DEFAULT NULL,
  `humidity_min` decimal(5,2) DEFAULT NULL,
  `humidity_max` decimal(5,2) DEFAULT NULL,
  `security_level` varchar(50) DEFAULT NULL,
  `access_restrictions` text,
  `status` enum('active','full','maintenance','decommissioned') DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_physical_object_id` (`physical_object_id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_reference_code` (`reference_code`),
  KEY `idx_building` (`building`),
  KEY `idx_status` (`status`),
  KEY `idx_available_capacity` (`available_capacity`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: privacy_breach_incident
CREATE TABLE IF NOT EXISTS `privacy_breach_incident` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `incident_date` datetime NOT NULL,
  `discovered_date` datetime NOT NULL,
  `breach_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `data_affected` text,
  `individuals_affected` int DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `root_cause` text,
  `containment_actions` text,
  `regulator_notified` tinyint(1) DEFAULT '0',
  `notification_date` datetime DEFAULT NULL,
  `subjects_notified` tinyint(1) DEFAULT '0',
  `status` varchar(50) DEFAULT 'open',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reference` (`reference`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: privacy_consent_record
CREATE TABLE IF NOT EXISTS `privacy_consent_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `data_subject_id` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `consent_given` tinyint(1) DEFAULT '0',
  `consent_date` datetime DEFAULT NULL,
  `withdrawal_date` datetime DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`data_subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: privacy_dsar_log
CREATE TABLE IF NOT EXISTS `privacy_dsar_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dsar_id` int unsigned NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dsar` (`dsar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: privacy_dsar_request
CREATE TABLE IF NOT EXISTS `privacy_dsar_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `request_type` varchar(50) NOT NULL,
  `data_subject_name` varchar(255) NOT NULL,
  `data_subject_email` varchar(255) DEFAULT NULL,
  `data_subject_id_type` varchar(50) DEFAULT NULL,
  `received_date` date NOT NULL,
  `deadline_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `notes` text,
  `assigned_to` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reference` (`reference`),
  KEY `idx_status` (`status`),
  KEY `idx_deadline` (`deadline_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: privacy_processing_activity
CREATE TABLE IF NOT EXISTS `privacy_processing_activity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `purpose` text NOT NULL,
  `lawful_basis` varchar(100) DEFAULT NULL,
  `data_categories` text,
  `data_subjects` text,
  `recipients` text,
  `transfers` text,
  `retention_period` varchar(100) DEFAULT NULL,
  `security_measures` text,
  `dpia_required` tinyint(1) DEFAULT '0',
  `dpia_completed` tinyint(1) DEFAULT '0',
  `status` varchar(50) DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: privacy_template
CREATE TABLE IF NOT EXISTS `privacy_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: provenance_entry
CREATE TABLE IF NOT EXISTS `provenance_entry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `sequence` smallint unsigned NOT NULL DEFAULT '1',
  `owner_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_type` enum('person','family','dealer','auction_house','museum','corporate','government','religious','artist','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `owner_actor_id` int unsigned DEFAULT NULL,
  `owner_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_location_tgn` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date_qualifier` enum('circa','before','after','by') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date_qualifier` enum('circa','before','after','by') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_type` enum('sale','auction','gift','bequest','inheritance','commission','exchange','seizure','restitution','transfer','loan','found','created','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `transfer_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `sale_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auction_house` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auction_lot` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certainty` enum('certain','probable','possible','uncertain','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `sources` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_gap` tinyint(1) NOT NULL DEFAULT '0',
  `gap_explanation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pe_object` (`information_object_id`),
  KEY `idx_pe_object_seq` (`information_object_id`,`sequence`),
  KEY `idx_pe_owner` (`owner_name`),
  KEY `idx_pe_transfer` (`transfer_type`),
  KEY `idx_pe_certainty` (`certainty`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: report_definition
CREATE TABLE IF NOT EXISTS `report_definition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` enum('collection','acquisition','access','preservation','researcher','compliance','statistics','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sector` set('archive','library','museum','dam','researcher') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'archive',
  `report_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PHP class name for report generator',
  `parameters` json DEFAULT NULL COMMENT 'Available filter parameters',
  `output_formats` set('html','pdf','csv','xlsx','json') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'html,csv',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_report_category` (`category`),
  KEY `idx_report_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: request_to_publish
CREATE TABLE IF NOT EXISTS `request_to_publish` (
  `id` int NOT NULL,
  `parent_id` varchar(50) DEFAULT NULL,
  `rtp_type_id` int DEFAULT NULL,
  `lft` int NOT NULL,
  `rgt` int NOT NULL,
  `source_culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `requesttopublish_FK_1` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: request_to_publish_i18n
CREATE TABLE IF NOT EXISTS `request_to_publish_i18n` (
  `unique_identifier` varchar(1024) DEFAULT NULL,
  `rtp_name` varchar(50) DEFAULT NULL,
  `rtp_surname` varchar(50) DEFAULT NULL,
  `rtp_phone` varchar(50) DEFAULT NULL,
  `rtp_email` varchar(50) DEFAULT NULL,
  `rtp_institution` varchar(200) DEFAULT NULL,
  `rtp_motivation` text,
  `rtp_planned_use` text,
  `rtp_need_image_by` datetime DEFAULT NULL,
  `status_id` int NOT NULL,
  `id` int NOT NULL,
  `object_id` varchar(50) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `requesttopublish_i18n_FK_1` FOREIGN KEY (`id`) REFERENCES `request_to_publish` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_annotation
CREATE TABLE IF NOT EXISTS `research_annotation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `object_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `annotation_type` enum('note','highlight','bookmark','tag','transcription') DEFAULT 'note',
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `target_selector` text,
  `tags` varchar(500) DEFAULT NULL,
  `is_private` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_booking
CREATE TABLE IF NOT EXISTS `research_booking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `reading_room_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text,
  `status` enum('pending','confirmed','cancelled','completed','no_show') DEFAULT 'pending',
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_date` (`booking_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_citation_log
CREATE TABLE IF NOT EXISTS `research_citation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int DEFAULT NULL,
  `object_id` int NOT NULL,
  `citation_style` varchar(50) NOT NULL,
  `citation_text` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1781 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_collection
CREATE TABLE IF NOT EXISTS `research_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `is_public` tinyint(1) DEFAULT '0',
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_share_token` (`share_token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_collection_item
CREATE TABLE IF NOT EXISTS `research_collection_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `object_id` int NOT NULL,
  `notes` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item` (`collection_id`,`object_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_material_request
CREATE TABLE IF NOT EXISTS `research_material_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `object_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `notes` text,
  `status` enum('requested','retrieved','delivered','in_use','returned','unavailable') DEFAULT 'requested',
  `retrieved_by` int DEFAULT NULL,
  `retrieved_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `condition_notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_password_reset
CREATE TABLE IF NOT EXISTS `research_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_reading_room
CREATE TABLE IF NOT EXISTS `research_reading_room` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `amenities` text,
  `capacity` int DEFAULT '10',
  `location` varchar(255) DEFAULT NULL,
  `operating_hours` text,
  `rules` text,
  `advance_booking_days` int DEFAULT '14',
  `max_booking_hours` int DEFAULT '4',
  `cancellation_hours` int DEFAULT '24',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `opening_time` time DEFAULT '09:00:00',
  `closing_time` time DEFAULT '17:00:00',
  `days_open` varchar(50) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_researcher
CREATE TABLE IF NOT EXISTS `research_researcher` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `affiliation_type` enum('academic','government','private','independent','student','other') DEFAULT 'independent',
  `institution` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `student_id` varchar(100) DEFAULT NULL,
  `research_interests` text,
  `current_project` text,
  `orcid_id` varchar(50) DEFAULT NULL,
  `id_type` enum('passport','national_id','drivers_license','student_card','other') DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `id_verified` tinyint(1) DEFAULT '0',
  `id_verified_by` int DEFAULT NULL,
  `id_verified_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','suspended','expired') DEFAULT 'pending',
  `rejection_reason` text,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_saved_search
CREATE TABLE IF NOT EXISTS `research_saved_search` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `search_query` text NOT NULL,
  `search_filters` text,
  `search_type` varchar(50) DEFAULT 'informationobject',
  `alert_enabled` tinyint(1) DEFAULT '0',
  `alert_frequency` enum('daily','weekly','monthly') DEFAULT 'weekly',
  `last_alert_at` datetime DEFAULT NULL,
  `new_results_count` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: ric_orphan_tracking
CREATE TABLE IF NOT EXISTS `ric_orphan_tracking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_entity_id` int DEFAULT NULL,
  `detected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detection_method` enum('integrity_check','sync_failure','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('detected','reviewed','cleaned','retained','restored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detected',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `triple_count` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_orphan_uri` (`ric_uri`(255)),
  KEY `idx_ric_orphan_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_config
CREATE TABLE IF NOT EXISTS `ric_sync_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_log
CREATE TABLE IF NOT EXISTS `ric_sync_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `operation` enum('create','update','delete','move','resync','cleanup','integrity_check') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('success','failure','partial','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `triples_affected` int DEFAULT NULL,
  `details` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `execution_time_ms` int DEFAULT NULL,
  `triggered_by` enum('user','system','cron','api','cli') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `user_id` int DEFAULT NULL,
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ric_log_entity` (`entity_type`,`entity_id`),
  KEY `idx_ric_log_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_queue
CREATE TABLE IF NOT EXISTS `ric_sync_queue` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `operation` enum('create','update','delete','move') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` tinyint NOT NULL DEFAULT '5',
  `status` enum('queued','processing','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` int NOT NULL DEFAULT '0',
  `max_attempts` int NOT NULL DEFAULT '3',
  `old_parent_id` int DEFAULT NULL,
  `new_parent_id` int DEFAULT NULL,
  `scheduled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ric_queue_status` (`status`,`priority`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_status
CREATE TABLE IF NOT EXISTS `ric_sync_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_status` enum('synced','pending','failed','deleted','orphaned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `last_synced_at` datetime DEFAULT NULL,
  `last_sync_attempt` datetime DEFAULT NULL,
  `sync_error` text COLLATE utf8mb4_unicode_ci,
  `retry_count` int NOT NULL DEFAULT '0',
  `content_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atom_updated_at` datetime DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `hierarchy_path` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_sync_entity` (`entity_type`,`entity_id`),
  KEY `idx_ric_sync_uri` (`ric_uri`(255)),
  KEY `idx_ric_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_cc_license
CREATE TABLE IF NOT EXISTS `rights_cc_license` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '4.0',
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allows_commercial` tinyint(1) DEFAULT '1',
  `allows_derivatives` tinyint(1) DEFAULT '1',
  `requires_share_alike` tinyint(1) DEFAULT '0',
  `requires_attribution` tinyint(1) DEFAULT '1',
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_cc_license_i18n
CREATE TABLE IF NOT EXISTS `rights_cc_license_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `human_readable` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_cc_license_i18n` FOREIGN KEY (`id`) REFERENCES `rights_cc_license` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_derivative_log
CREATE TABLE IF NOT EXISTS `rights_derivative_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `rule_id` int DEFAULT NULL,
  `derivative_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derivative_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` int DEFAULT NULL,
  `request_purpose` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_rule` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_derivative_rule
CREATE TABLE IF NOT EXISTS `rights_derivative_rule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL COMMENT 'NULL = applies to collection or global',
  `collection_id` int DEFAULT NULL COMMENT 'NULL = applies to object or global',
  `is_global` tinyint(1) DEFAULT '0',
  `rule_type` enum('watermark','redaction','resize','format_conversion','metadata_strip') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int DEFAULT '0',
  `applies_to_roles` json DEFAULT NULL COMMENT 'Array of role IDs, NULL = all',
  `applies_to_clearance_levels` json DEFAULT NULL COMMENT 'Array of clearance level codes',
  `applies_to_purposes` json DEFAULT NULL COMMENT 'Array of purpose codes',
  `watermark_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watermark_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watermark_position` enum('center','top_left','top_right','bottom_left','bottom_right','tile') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'bottom_right',
  `watermark_opacity` int DEFAULT '50' COMMENT '0-100',
  `redaction_areas` json DEFAULT NULL COMMENT 'Array of {x, y, width, height, page}',
  `redaction_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#000000',
  `max_width` int DEFAULT NULL,
  `max_height` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_rule_type` (`rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_embargo
CREATE TABLE IF NOT EXISTS `rights_embargo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `embargo_type` enum('full','metadata_only','digital_only','partial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `reason` enum('donor_restriction','copyright','privacy','legal','commercial','research','cultural','security','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL = indefinite',
  `auto_release` tinyint(1) DEFAULT '1' COMMENT 'Auto-lift on end_date',
  `review_date` date DEFAULT NULL,
  `review_interval_months` int DEFAULT '12',
  `last_reviewed_at` datetime DEFAULT NULL,
  `last_reviewed_by` int DEFAULT NULL,
  `status` enum('active','pending','lifted','expired','extended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by` int DEFAULT NULL,
  `lift_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notify_before_days` int DEFAULT '30',
  `notification_sent` tinyint(1) DEFAULT '0',
  `notify_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of emails',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_review_date` (`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_embargo_i18n
CREATE TABLE IF NOT EXISTS `rights_embargo_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `reason_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `internal_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_embargo_i18n` FOREIGN KEY (`id`) REFERENCES `rights_embargo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_embargo_log
CREATE TABLE IF NOT EXISTS `rights_embargo_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `embargo_id` int NOT NULL,
  `action` enum('created','extended','lifted','reviewed','notification_sent','auto_released') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_end_date` date DEFAULT NULL,
  `new_end_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_embargo` (`embargo_id`),
  CONSTRAINT `fk_embargo_log` FOREIGN KEY (`embargo_id`) REFERENCES `rights_embargo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_grant
CREATE TABLE IF NOT EXISTS `rights_grant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rights_record_id` int NOT NULL,
  `act` enum('render','disseminate','replicate','migrate','modify','delete','print','use','publish','excerpt','annotate','move','sell') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `restriction` enum('allow','disallow','conditional') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'allow',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `condition_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rights_record` (`rights_record_id`),
  KEY `idx_act` (`act`),
  KEY `idx_restriction` (`restriction`),
  CONSTRAINT `fk_rights_grant_record` FOREIGN KEY (`rights_record_id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_grant_i18n
CREATE TABLE IF NOT EXISTS `rights_grant_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `restriction_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_grant_i18n` FOREIGN KEY (`id`) REFERENCES `rights_grant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_object_tk_label
CREATE TABLE IF NOT EXISTS `rights_object_tk_label` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `tk_label_id` int NOT NULL,
  `community_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `custom_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `verified` tinyint(1) DEFAULT '0',
  `verified_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_object_label` (`object_id`,`tk_label_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_label` (`tk_label_id`),
  CONSTRAINT `fk_object_tk_label` FOREIGN KEY (`tk_label_id`) REFERENCES `rights_tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_orphan_search_step
CREATE TABLE IF NOT EXISTS `rights_orphan_search_step` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orphan_work_id` int NOT NULL,
  `source_type` enum('database','registry','publisher','author_society','archive','library','internet','newspaper','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `search_date` date NOT NULL,
  `search_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `results_found` tinyint(1) DEFAULT '0',
  `results_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evidence_file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `screenshot_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orphan_work` (`orphan_work_id`),
  CONSTRAINT `fk_orphan_search_step` FOREIGN KEY (`orphan_work_id`) REFERENCES `rights_orphan_work` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_orphan_work
CREATE TABLE IF NOT EXISTS `rights_orphan_work` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `status` enum('in_progress','completed','rights_holder_found','abandoned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'in_progress',
  `work_type` enum('literary','dramatic','musical','artistic','film','sound_recording','broadcast','typographical','database','photograph','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_started_date` date DEFAULT NULL,
  `search_completed_date` date DEFAULT NULL,
  `search_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZA',
  `rights_holder_found` tinyint(1) DEFAULT '0',
  `rights_holder_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rights_holder_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_attempted` tinyint(1) DEFAULT '0',
  `contact_date` date DEFAULT NULL,
  `contact_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `intended_use` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `proposed_fee` decimal(10,2) DEFAULT NULL,
  `fee_held_in_escrow` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_orphan_work_i18n
CREATE TABLE IF NOT EXISTS `rights_orphan_work_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `search_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_orphan_work_i18n` FOREIGN KEY (`id`) REFERENCES `rights_orphan_work` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_record
CREATE TABLE IF NOT EXISTS `rights_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `basis` enum('copyright','license','statute','donor','policy','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'copyright',
  `rights_statement_id` int DEFAULT NULL,
  `cc_license_id` int DEFAULT NULL,
  `copyright_status` enum('copyrighted','public_domain','unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `copyright_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_holder_actor_id` int DEFAULT NULL COMMENT 'FK to actor.id',
  `copyright_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZA' COMMENT 'ISO 3166-1 alpha-2',
  `copyright_determination_date` date DEFAULT NULL,
  `copyright_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statute_citation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statute_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statute_determination_date` date DEFAULT NULL,
  `statute_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `donor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_actor_id` int DEFAULT NULL COMMENT 'FK to actor.id',
  `donor_agreement_date` date DEFAULT NULL,
  `donor_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `policy_identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `documentation_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentation_role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL COMMENT 'FK to user.id',
  `updated_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_basis` (`basis`),
  KEY `idx_status` (`copyright_status`),
  KEY `fk_rights_statement` (`rights_statement_id`),
  KEY `fk_rights_cc_license` (`cc_license_id`),
  CONSTRAINT `fk_rights_cc_license` FOREIGN KEY (`cc_license_id`) REFERENCES `rights_cc_license` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_record_i18n
CREATE TABLE IF NOT EXISTS `rights_record_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `rights_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `restriction_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_record_i18n` FOREIGN KEY (`id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_statement
CREATE TABLE IF NOT EXISTS `rights_statement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('in-copyright','no-copyright','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rights_statement_uri` (`uri`),
  UNIQUE KEY `uq_rights_statement_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_statement_i18n
CREATE TABLE IF NOT EXISTS `rights_statement_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rights_statement_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `definition` text COLLATE utf8mb4_unicode_ci,
  `scope_note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rs_i18n` (`rights_statement_id`,`culture`),
  KEY `idx_rs_i18n_parent` (`rights_statement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_territory
CREATE TABLE IF NOT EXISTS `rights_territory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rights_record_id` int NOT NULL,
  `territory_type` enum('include','exclude') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'include',
  `country_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ISO 3166-1 alpha-2 or region code',
  `is_gdpr_territory` tinyint(1) DEFAULT '0',
  `gdpr_legal_basis` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rights_record` (`rights_record_id`),
  KEY `idx_country` (`country_code`),
  CONSTRAINT `fk_rights_territory_record` FOREIGN KEY (`rights_record_id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_tk_label
CREATE TABLE IF NOT EXISTS `rights_tk_label` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('tk','bc','attribution') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tk',
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex color code',
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_category` (`category`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_tk_label_i18n
CREATE TABLE IF NOT EXISTS `rights_tk_label_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `usage_protocol` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_tk_label_i18n` FOREIGN KEY (`id`) REFERENCES `rights_tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search
CREATE TABLE IF NOT EXISTS `saved_search` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `search_params` json NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'informationobject',
  `search_url` text COLLATE utf8mb4_unicode_ci,
  `result_count` int DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `is_global` tinyint(1) DEFAULT '0',
  `display_order` int DEFAULT '100',
  `share_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notify_on_new` tinyint(1) NOT NULL DEFAULT '0',
  `notification_frequency` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT 'weekly',
  `last_notification_at` datetime DEFAULT NULL,
  `last_result_count` int DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`),
  KEY `idx_saved_search_user` (`user_id`),
  KEY `idx_saved_search_entity` (`entity_type`),
  KEY `idx_saved_search_public` (`is_public`),
  KEY `idx_saved_search_notify` (`notify_on_new`,`notification_frequency`),
  KEY `idx_global` (`is_global`,`display_order`),
  CONSTRAINT `fk_saved_search_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search_i18n
CREATE TABLE IF NOT EXISTS `saved_search_i18n` (
  `id` int NOT NULL,
  `culture` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_saved_search_i18n` FOREIGN KEY (`id`) REFERENCES `saved_search` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search_log
CREATE TABLE IF NOT EXISTS `saved_search_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saved_search_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `result_count` int DEFAULT NULL,
  `execution_time_ms` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_search` (`saved_search_id`),
  KEY `idx_log_date` (`executed_at`),
  KEY `idx_log_user` (`user_id`),
  CONSTRAINT `fk_saved_search_log` FOREIGN KEY (`saved_search_id`) REFERENCES `saved_search` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search_tag
CREATE TABLE IF NOT EXISTS `saved_search_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saved_search_id` int NOT NULL,
  `tag` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_search_tag` (`saved_search_id`,`tag`),
  KEY `idx_tag` (`tag`),
  CONSTRAINT `fk_saved_search_tag` FOREIGN KEY (`saved_search_id`) REFERENCES `saved_search` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_history
CREATE TABLE IF NOT EXISTS `search_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `session_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `search_query` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_params` json DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'informationobject',
  `result_count` int DEFAULT '0',
  `execution_time` float DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_history_user` (`user_id`),
  KEY `idx_search_history_session` (`session_id`),
  KEY `idx_search_history_created` (`created_at`),
  KEY `idx_search_history_entity` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_popular
CREATE TABLE IF NOT EXISTS `search_popular` (
  `id` int NOT NULL AUTO_INCREMENT,
  `search_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_query` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_params` json DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'informationobject',
  `search_count` int DEFAULT '1',
  `last_searched` datetime DEFAULT CURRENT_TIMESTAMP,
  `avg_results` float DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_search_popular_hash` (`search_hash`),
  KEY `idx_search_popular_count` (`search_count` DESC),
  KEY `idx_search_popular_last` (`last_searched`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_settings
CREATE TABLE IF NOT EXISTS `search_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_search_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_template
CREATE TABLE IF NOT EXISTS `search_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-search',
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'primary',
  `search_params` json NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'informationobject',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `show_on_homepage` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_search_template_slug` (`slug`),
  KEY `idx_search_template_category` (`category`),
  KEY `idx_search_template_featured` (`is_featured`),
  KEY `idx_search_template_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_2fa_session
CREATE TABLE IF NOT EXISTS `security_2fa_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `verified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `idx_user_session` (`user_id`,`session_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_access_condition_link
CREATE TABLE IF NOT EXISTS `security_access_condition_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `access_conditions` text,
  `reproduction_conditions` text,
  `narssa_ref` varchar(100) DEFAULT NULL,
  `retention_period` varchar(50) DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_classification` (`classification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_access_log
CREATE TABLE IF NOT EXISTS `security_access_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `object_id` int NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_granted` tinyint(1) NOT NULL,
  `denial_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sal_object` (`object_id`),
  KEY `idx_sal_user` (`user_id`),
  KEY `idx_sal_classification` (`classification_id`),
  CONSTRAINT `fk_sal_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sal_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_access_request
CREATE TABLE IF NOT EXISTS `security_access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `object_id` int unsigned DEFAULT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `compartment_id` int unsigned DEFAULT NULL,
  `request_type` enum('view','download','print','clearance_upgrade','compartment_access','renewal') NOT NULL,
  `justification` text NOT NULL,
  `duration_hours` int DEFAULT NULL,
  `priority` enum('normal','urgent','immediate') DEFAULT 'normal',
  `status` enum('pending','approved','denied','expired','cancelled') DEFAULT 'pending',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text,
  `access_granted_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority_status` (`priority`,`status`,`created_at`),
  KEY `classification_id` (`classification_id`),
  KEY `compartment_id` (`compartment_id`),
  CONSTRAINT `security_access_request_ibfk_1` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_access_request_ibfk_2` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_audit_log
CREATE TABLE IF NOT EXISTS `security_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL,
  `object_type` varchar(50) DEFAULT 'information_object',
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `action_category` varchar(50) DEFAULT 'access',
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_category` (`action_category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_classification
CREATE TABLE IF NOT EXISTS `security_classification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` tinyint unsigned NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_justification` tinyint(1) NOT NULL DEFAULT '0',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `requires_2fa` tinyint(1) NOT NULL DEFAULT '0',
  `max_session_hours` int DEFAULT NULL,
  `watermark_required` tinyint(1) NOT NULL DEFAULT '0',
  `watermark_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `download_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `print_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `copy_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security_classification_level` (`level`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_clearance_history
CREATE TABLE IF NOT EXISTS `security_clearance_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `previous_classification_id` int unsigned DEFAULT NULL,
  `new_classification_id` int unsigned DEFAULT NULL,
  `action` enum('granted','upgraded','downgraded','revoked','renewed','expired','2fa_enabled','2fa_disabled') NOT NULL,
  `changed_by` int unsigned NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `previous_classification_id` (`previous_classification_id`),
  KEY `new_classification_id` (`new_classification_id`),
  CONSTRAINT `security_clearance_history_ibfk_1` FOREIGN KEY (`previous_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_clearance_history_ibfk_2` FOREIGN KEY (`new_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_compartment
CREATE TABLE IF NOT EXISTS `security_compartment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `min_clearance_id` int unsigned NOT NULL,
  `requires_need_to_know` tinyint(1) DEFAULT '1',
  `requires_briefing` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_active` (`active`),
  KEY `min_clearance_id` (`min_clearance_id`),
  CONSTRAINT `security_compartment_ibfk_1` FOREIGN KEY (`min_clearance_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_compliance_log
CREATE TABLE IF NOT EXISTS `security_compliance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `object_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `hash` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_declassification_schedule
CREATE TABLE IF NOT EXISTS `security_declassification_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `scheduled_date` date NOT NULL,
  `from_classification_id` int unsigned NOT NULL,
  `to_classification_id` int unsigned DEFAULT NULL,
  `trigger_type` enum('date','event','retention') NOT NULL DEFAULT 'date',
  `trigger_event` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scheduled` (`scheduled_date`,`processed`),
  KEY `idx_object` (`object_id`),
  KEY `from_classification_id` (`from_classification_id`),
  KEY `to_classification_id` (`to_classification_id`),
  CONSTRAINT `security_declassification_schedule_ibfk_1` FOREIGN KEY (`from_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `security_declassification_schedule_ibfk_2` FOREIGN KEY (`to_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_retention_schedule
CREATE TABLE IF NOT EXISTS `security_retention_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `narssa_ref` varchar(100) NOT NULL,
  `record_type` varchar(255) NOT NULL,
  `retention_period` varchar(100) NOT NULL,
  `disposal_action` varchar(100) NOT NULL,
  `legal_reference` text,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_narssa` (`narssa_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_watermark_log
CREATE TABLE IF NOT EXISTS `security_watermark_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `object_id` int unsigned NOT NULL,
  `digital_object_id` int unsigned DEFAULT NULL,
  `watermark_type` enum('visible','invisible','both') NOT NULL DEFAULT 'visible',
  `watermark_text` varchar(500) NOT NULL,
  `watermark_code` varchar(100) NOT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `idx_object` (`object_id`),
  KEY `idx_code` (`watermark_code`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_acquisition
CREATE TABLE IF NOT EXISTS `spectrum_acquisition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `acquisition_number` varchar(50) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_method` varchar(50) DEFAULT NULL,
  `acquisition_source` varchar(255) DEFAULT NULL,
  `source_contact` text,
  `acquisition_reason` text,
  `acquisition_authorization` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `funding_source` varchar(255) DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `price_currency` varchar(10) DEFAULT NULL,
  `group_purchase_price` decimal(15,2) DEFAULT NULL,
  `accession_date` date DEFAULT NULL,
  `accession_number` varchar(50) DEFAULT NULL,
  `title_transfer_date` date DEFAULT NULL,
  `ownership_history` text,
  `acquisition_note` text,
  `provenance_note` text,
  `legal_title` text,
  `conditions_of_acquisition` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_acquisition_number` (`acquisition_number`),
  KEY `idx_accession_number` (`accession_number`),
  KEY `idx_acquisition_date` (`acquisition_date`),
  KEY `idx_wf_acq` (`workflow_state`),
  CONSTRAINT `spectrum_acquisition_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_approval
CREATE TABLE IF NOT EXISTS `spectrum_approval` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_id`),
  KEY `idx_approver` (`approver_id`),
  CONSTRAINT `spectrum_approval_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `spectrum_event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_audit_log
CREATE TABLE IF NOT EXISTS `spectrum_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL,
  `procedure_type` varchar(50) NOT NULL,
  `procedure_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `action_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `note` text,
  PRIMARY KEY (`id`),
  KEY `idx_audit_object` (`object_id`),
  KEY `idx_audit_procedure` (`procedure_type`,`procedure_id`),
  KEY `idx_audit_date` (`action_date`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_barcode
CREATE TABLE IF NOT EXISTS `spectrum_barcode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `barcode_type` varchar(20) NOT NULL,
  `barcode_content` varchar(500) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  `print_count` int DEFAULT '0',
  `last_printed` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_barcode_object` (`object_id`),
  KEY `idx_barcode_type` (`barcode_type`),
  CONSTRAINT `spectrum_barcode_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_check
CREATE TABLE IF NOT EXISTS `spectrum_condition_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `condition_reference` varchar(50) DEFAULT NULL,
  `check_date` datetime NOT NULL,
  `check_reason` varchar(100) DEFAULT NULL,
  `checked_by` varchar(255) NOT NULL,
  `overall_condition` varchar(50) DEFAULT NULL,
  `condition_note` text,
  `completeness_note` text,
  `hazard_note` text,
  `technical_assessment` text,
  `recommended_treatment` text,
  `treatment_priority` varchar(50) DEFAULT NULL,
  `next_check_date` date DEFAULT NULL,
  `environment_recommendation` text,
  `handling_recommendation` text,
  `display_recommendation` text,
  `storage_recommendation` text,
  `packing_recommendation` text,
  `image_reference` text,
  `photo_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `condition_check_reference` varchar(255) DEFAULT NULL,
  `completeness` varchar(50) DEFAULT NULL,
  `condition_description` text,
  `hazards_noted` text,
  `recommendations` text,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `condition_rating` varchar(50) DEFAULT NULL COMMENT 'Overall condition rating',
  `condition_notes` text COMMENT 'Detailed condition notes',
  `template_id` int DEFAULT NULL,
  `material_type` varchar(50) DEFAULT NULL,
  `template_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_condition_date` (`check_date`),
  KEY `idx_condition_reference` (`condition_reference`),
  KEY `idx_overall_condition` (`overall_condition`),
  KEY `idx_wf_cond` (`workflow_state`),
  KEY `idx_check_date` (`check_date`),
  CONSTRAINT `spectrum_condition_check_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_check_data
CREATE TABLE IF NOT EXISTS `spectrum_condition_check_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `template_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_check_field` (`condition_check_id`,`field_id`),
  KEY `template_id` (`template_id`),
  KEY `field_id` (`field_id`),
  KEY `idx_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_check_data_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_3` FOREIGN KEY (`field_id`) REFERENCES `spectrum_condition_template_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_photo
CREATE TABLE IF NOT EXISTS `spectrum_condition_photo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `photo_type` enum('before','after','detail','damage','overall','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detail',
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_on_object` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `photographer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_date` date DEFAULT NULL,
  `camera_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `annotations` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `digital_object_id` (`digital_object_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_condition_check` (`condition_check_id`),
  KEY `idx_photo_type` (`photo_type`),
  KEY `idx_photo_date` (`photo_date`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `spectrum_condition_photo_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_photo_ibfk_2` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_condition_photo_comparison
CREATE TABLE IF NOT EXISTS `spectrum_condition_photo_comparison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `before_photo_id` int NOT NULL,
  `after_photo_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_condition_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_photo_comparison_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_photos
CREATE TABLE IF NOT EXISTS `spectrum_condition_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL COMMENT 'Reference to spectrum_condition_check',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stored filename',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original uploaded filename',
  `category` enum('overall','detail','damage','before','after','reference') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'overall' COMMENT 'Photo category',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Description or notes about the photo',
  `annotations` json DEFAULT NULL COMMENT 'JSON annotations for damage markers',
  `file_size` int DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type of the image',
  `width` int DEFAULT NULL COMMENT 'Image width in pixels',
  `height` int DEFAULT NULL COMMENT 'Image height in pixels',
  `captured_at` datetime DEFAULT NULL COMMENT 'When photo was taken (from EXIF)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who uploaded the photo',
  `updated_at` datetime DEFAULT NULL COMMENT 'Last update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_check` (`condition_check_id`),
  KEY `idx_category` (`category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_condition_template
CREATE TABLE IF NOT EXISTS `spectrum_condition_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_template_field
CREATE TABLE IF NOT EXISTS `spectrum_condition_template_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` enum('text','textarea','select','multiselect','checkbox','radio','rating','date','number') NOT NULL,
  `options` json DEFAULT NULL COMMENT 'For select/multiselect/radio - array of options',
  `default_value` varchar(255) DEFAULT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `help_text` text,
  `is_required` tinyint(1) DEFAULT '0',
  `validation_rules` json DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_section` (`section_id`),
  CONSTRAINT `spectrum_condition_template_field_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `spectrum_condition_template_section` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_template_section
CREATE TABLE IF NOT EXISTS `spectrum_condition_template_section` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_required` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_template` (`template_id`),
  CONSTRAINT `spectrum_condition_template_section_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_conservation
CREATE TABLE IF NOT EXISTS `spectrum_conservation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `conservation_reference` varchar(50) DEFAULT NULL,
  `treatment_date` date NOT NULL,
  `treatment_end_date` date DEFAULT NULL,
  `conservator_name` varchar(255) NOT NULL,
  `conservator_organization` varchar(255) DEFAULT NULL,
  `condition_before` text,
  `treatment_proposal` text,
  `treatment_performed` text,
  `materials_used` text,
  `condition_after` text,
  `treatment_cost` decimal(15,2) DEFAULT NULL,
  `cost_currency` varchar(10) DEFAULT NULL,
  `next_treatment_date` date DEFAULT NULL,
  `treatment_note` text,
  `report_reference` varchar(100) DEFAULT NULL,
  `image_before` text,
  `image_after` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `recommendations` text,
  `conservation_note` text,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_conservation_reference` (`conservation_reference`),
  KEY `idx_treatment_date` (`treatment_date`),
  KEY `idx_wf_cons` (`workflow_state`),
  CONSTRAINT `spectrum_conservation_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_conservation_treatment
CREATE TABLE IF NOT EXISTS `spectrum_conservation_treatment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `treatment_reference` varchar(100) DEFAULT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `treatment_date` date DEFAULT NULL,
  `conservator` varchar(255) DEFAULT NULL,
  `description` text,
  `materials_used` text,
  `outcome` text,
  `cost` decimal(10,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`treatment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_deaccession
CREATE TABLE IF NOT EXISTS `spectrum_deaccession` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `deaccession_number` varchar(50) NOT NULL,
  `deaccession_date` date NOT NULL,
  `proposal_date` date DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `authorized_by` varchar(255) DEFAULT NULL,
  `deaccession_reason` text,
  `disposal_method` varchar(50) DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_recipient` varchar(255) DEFAULT NULL,
  `disposal_price` decimal(15,2) DEFAULT NULL,
  `disposal_currency` varchar(10) DEFAULT NULL,
  `new_owner` varchar(255) DEFAULT NULL,
  `new_owner_contact` text,
  `legal_requirements_met` tinyint(1) DEFAULT '0',
  `documentation_complete` tinyint(1) DEFAULT '0',
  `deaccession_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_deaccession_number` (`deaccession_number`),
  KEY `idx_deaccession_date` (`deaccession_date`),
  KEY `idx_wf_deacc` (`workflow_state`),
  CONSTRAINT `spectrum_deaccession_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_event
CREATE TABLE IF NOT EXISTS `spectrum_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `procedure_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_from` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `assigned_to_id` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_procedure` (`object_id`,`procedure_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_procedure` (`procedure_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status_to`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `spectrum_event_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_grap_data
CREATE TABLE IF NOT EXISTS `spectrum_grap_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `information_object_id` int NOT NULL,
  `recognition_status` varchar(50) DEFAULT NULL,
  `recognition_status_reason` varchar(255) DEFAULT NULL,
  `measurement_basis` varchar(50) DEFAULT NULL,
  `initial_recognition_date` date DEFAULT NULL,
  `initial_recognition_value` decimal(15,2) DEFAULT NULL,
  `carrying_amount` decimal(15,2) DEFAULT NULL,
  `acquisition_method_grap` varchar(50) DEFAULT NULL,
  `cost_of_acquisition` decimal(15,2) DEFAULT NULL,
  `fair_value_at_acquisition` decimal(15,2) DEFAULT NULL,
  `donor_restrictions` text,
  `last_revaluation_date` date DEFAULT NULL,
  `revaluation_amount` decimal(15,2) DEFAULT NULL,
  `valuer_credentials` varchar(255) DEFAULT NULL,
  `valuation_method` varchar(50) DEFAULT NULL,
  `revaluation_frequency` varchar(50) DEFAULT NULL,
  `depreciation_policy` varchar(50) DEFAULT NULL,
  `useful_life_years` int DEFAULT NULL,
  `residual_value` decimal(15,2) DEFAULT NULL,
  `depreciation_method` varchar(50) DEFAULT NULL,
  `accumulated_depreciation` decimal(15,2) DEFAULT NULL,
  `last_impairment_assessment_date` date DEFAULT NULL,
  `impairment_indicators` tinyint(1) DEFAULT '0',
  `impairment_indicators_details` text,
  `impairment_loss_amount` decimal(15,2) DEFAULT NULL,
  `derecognition_date` date DEFAULT NULL,
  `derecognition_reason` varchar(50) DEFAULT NULL,
  `derecognition_value` decimal(15,2) DEFAULT NULL,
  `gain_loss_on_derecognition` decimal(15,2) DEFAULT NULL,
  `asset_class` varchar(50) DEFAULT NULL,
  `gl_account_code` varchar(50) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `fund_source` varchar(100) DEFAULT NULL,
  `restrictions_use_disposal` text,
  `heritage_significance_rating` varchar(50) DEFAULT NULL,
  `conservation_commitments` text,
  `insurance_coverage_required` decimal(15,2) DEFAULT NULL,
  `insurance_coverage_actual` decimal(15,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grap_io` (`information_object_id`),
  KEY `idx_grap_asset_class` (`asset_class`),
  KEY `idx_grap_recognition` (`recognition_status`),
  KEY `idx_grap_gl_account` (`gl_account_code`),
  KEY `idx_grap_cost_center` (`cost_center`),
  KEY `idx_grap_recognition_date` (`initial_recognition_date`),
  CONSTRAINT `spectrum_grap_data_ibfk_1` FOREIGN KEY (`information_object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_grap_depreciation_schedule
CREATE TABLE IF NOT EXISTS `spectrum_grap_depreciation_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_data_id` int NOT NULL,
  `fiscal_year` int NOT NULL,
  `fiscal_period` varchar(20) DEFAULT NULL,
  `opening_value` decimal(15,2) DEFAULT NULL,
  `depreciation_amount` decimal(15,2) DEFAULT NULL,
  `closing_value` decimal(15,2) DEFAULT NULL,
  `calculated_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_deprec_period` (`grap_data_id`,`fiscal_year`,`fiscal_period`),
  KEY `idx_fiscal_year` (`fiscal_year`),
  CONSTRAINT `spectrum_grap_depreciation_schedule_ibfk_1` FOREIGN KEY (`grap_data_id`) REFERENCES `spectrum_grap_data` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_grap_journal
CREATE TABLE IF NOT EXISTS `spectrum_grap_journal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_data_id` int NOT NULL,
  `journal_date` date NOT NULL,
  `journal_type` varchar(50) NOT NULL,
  `debit_account` varchar(50) DEFAULT NULL,
  `credit_account` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text,
  `reference_number` varchar(100) DEFAULT NULL,
  `posted_by` int DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grap_data_id` (`grap_data_id`),
  KEY `idx_journal_date` (`journal_date`),
  KEY `idx_journal_type` (`journal_type`),
  CONSTRAINT `spectrum_grap_journal_ibfk_1` FOREIGN KEY (`grap_data_id`) REFERENCES `spectrum_grap_data` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_grap_revaluation_history
CREATE TABLE IF NOT EXISTS `spectrum_grap_revaluation_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grap_data_id` int NOT NULL,
  `revaluation_date` date NOT NULL,
  `previous_value` decimal(15,2) DEFAULT NULL,
  `new_value` decimal(15,2) DEFAULT NULL,
  `revaluation_surplus` decimal(15,2) DEFAULT NULL,
  `valuer_name` varchar(255) DEFAULT NULL,
  `valuer_credentials` varchar(255) DEFAULT NULL,
  `valuation_method` varchar(50) DEFAULT NULL,
  `valuation_report_reference` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grap_data_id` (`grap_data_id`),
  KEY `idx_reval_date` (`revaluation_date`),
  CONSTRAINT `spectrum_grap_revaluation_history_ibfk_1` FOREIGN KEY (`grap_data_id`) REFERENCES `spectrum_grap_data` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_loan_agreements
CREATE TABLE IF NOT EXISTS `spectrum_loan_agreements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL COMMENT 'Reference to spectrum_loan_in or spectrum_loan_out',
  `loan_type` enum('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of loan',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Generated PDF filename',
  `template` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'standard' COMMENT 'Template used',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who generated the agreement',
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`,`loan_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_loan_document
CREATE TABLE IF NOT EXISTS `spectrum_loan_document` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_type` varchar(20) NOT NULL,
  `loan_id` int NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  `signed` tinyint(1) DEFAULT '0',
  `signed_at` datetime DEFAULT NULL,
  `signed_by` varchar(255) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_loan_document` (`loan_type`,`loan_id`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_loan_in
CREATE TABLE IF NOT EXISTS `spectrum_loan_in` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `loan_in_number` varchar(50) NOT NULL,
  `lender_name` varchar(255) NOT NULL,
  `lender_contact` text,
  `lender_address` text,
  `loan_in_date` date NOT NULL,
  `loan_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_purpose` varchar(100) DEFAULT NULL,
  `loan_conditions` text,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) DEFAULT NULL,
  `insurance_reference` varchar(100) DEFAULT NULL,
  `insurance_note` text,
  `loan_agreement_date` date DEFAULT NULL,
  `loan_agreement_reference` varchar(100) DEFAULT NULL,
  `special_requirements` text,
  `loan_status` varchar(50) DEFAULT 'active',
  `loan_note` text,
  `agreement_document_id` int DEFAULT NULL,
  `facility_report_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `loan_in_note` text,
  `workflow_state` varchar(50) DEFAULT 'requested',
  `loan_number` varchar(50) DEFAULT NULL COMMENT 'Loan reference number',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'Contact person name',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone',
  `address` text COMMENT 'Lender address',
  `insurance_provider` varchar(255) DEFAULT NULL COMMENT 'Insurance provider',
  `insurance_policy_number` varchar(100) DEFAULT NULL COMMENT 'Policy number',
  `special_conditions` text COMMENT 'Special conditions',
  `handling_requirements` text COMMENT 'Handling requirements',
  `display_requirements` text COMMENT 'Display requirements',
  `environmental_requirements` text COMMENT 'Environmental requirements',
  `object_description` text COMMENT 'Object description for agreement',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_loan_in_number` (`loan_in_number`),
  KEY `idx_loan_in_status` (`loan_status`),
  KEY `idx_loan_return_date` (`loan_return_date`),
  KEY `idx_wf_lin` (`workflow_state`),
  KEY `idx_loan_dates` (`loan_start_date`,`loan_end_date`),
  CONSTRAINT `spectrum_loan_in_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_loan_out
CREATE TABLE IF NOT EXISTS `spectrum_loan_out` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `loan_out_number` varchar(50) NOT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `borrower_contact` text,
  `borrower_address` text,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_address` text,
  `loan_out_date` date NOT NULL,
  `loan_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_purpose` varchar(100) DEFAULT NULL,
  `loan_conditions` text,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) DEFAULT NULL,
  `insurance_reference` varchar(100) DEFAULT NULL,
  `indemnity_reference` varchar(100) DEFAULT NULL,
  `loan_agreement_date` date DEFAULT NULL,
  `loan_agreement_reference` varchar(100) DEFAULT NULL,
  `exhibition_title` varchar(255) DEFAULT NULL,
  `exhibition_dates` text,
  `special_requirements` text,
  `courier_required` tinyint(1) DEFAULT '0',
  `courier_name` varchar(255) DEFAULT NULL,
  `loan_status` varchar(50) DEFAULT 'active',
  `loan_note` text,
  `agreement_document_id` int DEFAULT NULL,
  `facility_report_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `insurance_policy` varchar(255) DEFAULT NULL,
  `loan_out_note` text,
  `workflow_state` varchar(50) DEFAULT 'requested',
  `loan_number` varchar(50) DEFAULT NULL COMMENT 'Loan reference number',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'Contact person name',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone',
  `address` text COMMENT 'Borrower address',
  `insurance_provider` varchar(255) DEFAULT NULL COMMENT 'Insurance provider',
  `insurance_policy_number` varchar(100) DEFAULT NULL COMMENT 'Policy number',
  `special_conditions` text COMMENT 'Special conditions',
  `handling_requirements` text COMMENT 'Handling requirements',
  `display_requirements` text COMMENT 'Display requirements',
  `environmental_requirements` text COMMENT 'Environmental requirements',
  `object_description` text COMMENT 'Object description for agreement',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_loan_out_number` (`loan_out_number`),
  KEY `idx_loan_out_status` (`loan_status`),
  KEY `idx_borrower` (`borrower_name`),
  KEY `idx_wf_lout` (`workflow_state`),
  KEY `idx_loan_dates` (`loan_start_date`,`loan_end_date`),
  CONSTRAINT `spectrum_loan_out_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_location
CREATE TABLE IF NOT EXISTS `spectrum_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `location_type` varchar(50) DEFAULT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_building` varchar(255) DEFAULT NULL,
  `location_floor` varchar(50) DEFAULT NULL,
  `location_room` varchar(100) DEFAULT NULL,
  `location_unit` varchar(100) DEFAULT NULL,
  `location_shelf` varchar(100) DEFAULT NULL,
  `location_box` varchar(100) DEFAULT NULL,
  `location_note` text,
  `fitness_for_purpose` text,
  `security_note` text,
  `environment_note` text,
  `is_current` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `location_coordinates` varchar(255) DEFAULT NULL,
  `security_level` varchar(50) DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_location_current` (`object_id`,`is_current`),
  KEY `idx_location_name` (`location_name`),
  KEY `idx_wf_loc` (`workflow_state`),
  CONSTRAINT `spectrum_location_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_movement
CREATE TABLE IF NOT EXISTS `spectrum_movement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `movement_reference` varchar(50) DEFAULT NULL,
  `scanned_barcode` varchar(100) DEFAULT NULL,
  `scanned_at` datetime DEFAULT NULL,
  `scanned_by` int DEFAULT NULL,
  `movement_date` datetime NOT NULL,
  `movement_reason` varchar(100) DEFAULT NULL,
  `location_from` int DEFAULT NULL,
  `location_to` int DEFAULT NULL,
  `movement_method` varchar(100) DEFAULT NULL,
  `movement_contact` varchar(255) DEFAULT NULL,
  `handler_name` varchar(255) DEFAULT NULL,
  `condition_before` text,
  `condition_after` text,
  `planned_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `movement_note` text,
  `removal_authorization` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `from_location_id` int DEFAULT NULL,
  `to_location_id` int DEFAULT NULL,
  `moved_by` varchar(255) DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `location_from` (`location_from`),
  KEY `location_to` (`location_to`),
  KEY `idx_movement_date` (`movement_date`),
  KEY `idx_movement_reference` (`movement_reference`),
  KEY `idx_wf_mov` (`workflow_state`),
  CONSTRAINT `spectrum_movement_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_movement_ibfk_2` FOREIGN KEY (`location_from`) REFERENCES `spectrum_location` (`id`),
  CONSTRAINT `spectrum_movement_ibfk_3` FOREIGN KEY (`location_to`) REFERENCES `spectrum_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_notification
CREATE TABLE IF NOT EXISTS `spectrum_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_unread` (`user_id`,`read_at`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `spectrum_notification_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `spectrum_event` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_object_entry
CREATE TABLE IF NOT EXISTS `spectrum_object_entry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `entry_number` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_method` varchar(50) DEFAULT NULL,
  `entry_reason` text,
  `depositor_name` varchar(255) DEFAULT NULL,
  `depositor_contact` text,
  `depositor_address` text,
  `current_owner` varchar(255) DEFAULT NULL,
  `owner_contact` text,
  `return_date` date DEFAULT NULL,
  `entry_note` text,
  `received_by` varchar(255) DEFAULT NULL,
  `packing_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'received',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_entry_number` (`entry_number`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_depositor` (`depositor_name`),
  KEY `idx_wf_entry` (`workflow_state`),
  CONSTRAINT `spectrum_object_entry_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_object_exit
CREATE TABLE IF NOT EXISTS `spectrum_object_exit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `exit_number` varchar(50) NOT NULL,
  `exit_date` date NOT NULL,
  `exit_reason` varchar(50) DEFAULT NULL,
  `exit_destination` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_contact` text,
  `recipient_address` text,
  `authorization_name` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `packing_note` text,
  `dispatch_note` text,
  `courier_name` varchar(255) DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `exit_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_exit_number` (`exit_number`),
  KEY `idx_exit_date` (`exit_date`),
  KEY `idx_wf_exit` (`workflow_state`),
  CONSTRAINT `spectrum_object_exit_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_procedure_history
CREATE TABLE IF NOT EXISTS `spectrum_procedure_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `procedure_type` varchar(100) NOT NULL,
  `procedure_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_procedure_type` (`procedure_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_valuation
CREATE TABLE IF NOT EXISTS `spectrum_valuation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_reference` varchar(50) DEFAULT NULL,
  `valuation_date` date NOT NULL,
  `valuation_type` varchar(50) DEFAULT NULL,
  `valuation_amount` decimal(15,2) NOT NULL,
  `valuation_currency` varchar(10) DEFAULT 'ZAR',
  `valuer_name` varchar(255) DEFAULT NULL,
  `valuer_organization` varchar(255) DEFAULT NULL,
  `valuation_note` text,
  `renewal_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `renewal_cycle_months` int DEFAULT '36' COMMENT 'Months between valuations',
  `valuer` varchar(255) DEFAULT NULL COMMENT 'Name of appraiser/company',
  `currency` varchar(3) DEFAULT 'ZAR' COMMENT 'ISO currency code',
  PRIMARY KEY (`id`),
  KEY `idx_valuation_date` (`valuation_date`),
  KEY `idx_valuation_current` (`object_id`,`is_current`),
  KEY `idx_wf_val` (`workflow_state`),
  KEY `idx_renewal_date` (`renewal_date`),
  KEY `idx_wf_valuation` (`workflow_state`),
  CONSTRAINT `spectrum_valuation_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_valuation_alert
CREATE TABLE IF NOT EXISTS `spectrum_valuation_alert` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_id` int DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL,
  `alert_date` date NOT NULL,
  `message` text,
  `is_acknowledged` tinyint(1) DEFAULT '0',
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_alert_date` (`alert_date`),
  KEY `idx_alert_acknowledged` (`is_acknowledged`),
  CONSTRAINT `spectrum_valuation_alert_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_config
CREATE TABLE IF NOT EXISTS `spectrum_workflow_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `config_json` json NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `version` int DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedure_type` (`procedure_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_history
CREATE TABLE IF NOT EXISTS `spectrum_workflow_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `from_state` varchar(50) NOT NULL,
  `to_state` varchar(50) NOT NULL,
  `transition_key` varchar(50) NOT NULL,
  `user_id` int DEFAULT NULL,
  `note` text,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedure_record` (`procedure_type`,`record_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_notification
CREATE TABLE IF NOT EXISTS `spectrum_workflow_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `transition_key` varchar(50) NOT NULL,
  `recipient_user_id` int DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `message` text,
  `is_sent` tinyint(1) DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`is_sent`,`created_at`),
  KEY `idx_recipient` (`recipient_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_state
CREATE TABLE IF NOT EXISTS `spectrum_workflow_state` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `current_state` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_record` (`procedure_type`,`record_id`),
  KEY `idx_procedure_state` (`procedure_type`,`current_state`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: tiff_pdf_merge_file
CREATE TABLE IF NOT EXISTS `tiff_pdf_merge_file` (
  `id` int NOT NULL AUTO_INCREMENT,
  `merge_job_id` int NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint DEFAULT '0',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'image/tiff',
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `bit_depth` int DEFAULT NULL,
  `color_space` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_order` int DEFAULT '0',
  `status` enum('uploaded','processing','processed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'uploaded',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `checksum_md5` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tpm_file_job` (`merge_job_id`),
  KEY `idx_tpm_file_order` (`merge_job_id`,`page_order`),
  CONSTRAINT `tiff_pdf_merge_file_ibfk_1` FOREIGN KEY (`merge_job_id`) REFERENCES `tiff_pdf_merge_job` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tiff_pdf_merge_job
CREATE TABLE IF NOT EXISTS `tiff_pdf_merge_job` (
  `id` int NOT NULL AUTO_INCREMENT,
  `information_object_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `job_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','queued','processing','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `total_files` int DEFAULT '0',
  `processed_files` int DEFAULT '0',
  `output_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_digital_object_id` int DEFAULT NULL,
  `pdf_standard` enum('pdf','pdfa-1b','pdfa-2b','pdfa-3b') COLLATE utf8mb4_unicode_ci DEFAULT 'pdfa-2b',
  `compression_quality` int DEFAULT '85',
  `page_size` enum('auto','a4','letter','legal','a3') COLLATE utf8mb4_unicode_ci DEFAULT 'auto',
  `orientation` enum('auto','portrait','landscape') COLLATE utf8mb4_unicode_ci DEFAULT 'auto',
  `dpi` int DEFAULT '300',
  `preserve_originals` tinyint(1) DEFAULT '1',
  `attach_to_record` tinyint(1) DEFAULT '1',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `options` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tpm_job_status` (`status`),
  KEY `idx_tpm_job_user` (`user_id`),
  KEY `idx_tpm_job_info_object` (`information_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tiff_pdf_settings
CREATE TABLE IF NOT EXISTS `tiff_pdf_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','integer','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label
CREATE TABLE IF NOT EXISTS `tk_label` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_category_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_code` (`code`),
  UNIQUE KEY `uq_tk_uri` (`uri`),
  KEY `idx_tk_cat` (`tk_label_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_category
CREATE TABLE IF NOT EXISTS `tk_label_category` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#000000',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_cat_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_category_i18n
CREATE TABLE IF NOT EXISTS `tk_label_category_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_category_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_cat_i18n` (`tk_label_category_id`,`culture`),
  KEY `idx_tk_cat_i18n_parent` (`tk_label_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_i18n
CREATE TABLE IF NOT EXISTS `tk_label_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `usage_guide` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_i18n` (`tk_label_id`,`culture`),
  KEY `idx_tk_i18n_parent` (`tk_label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_compartment_access
CREATE TABLE IF NOT EXISTS `user_compartment_access` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `compartment_id` int unsigned NOT NULL,
  `granted_by` int unsigned NOT NULL,
  `granted_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `briefing_date` date DEFAULT NULL,
  `briefing_reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_compartment` (`user_id`,`compartment_id`),
  KEY `idx_compartment` (`compartment_id`),
  KEY `idx_expiry` (`expiry_date`,`active`),
  CONSTRAINT `user_compartment_access_ibfk_1` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: user_display_preference
CREATE TABLE IF NOT EXISTS `user_display_preference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module context: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list' COMMENT 'tree, grid, gallery, list, timeline',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` enum('asc','desc') COLLATE utf8mb4_unicode_ci DEFAULT 'desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` enum('small','medium','large') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_custom` tinyint(1) DEFAULT '1' COMMENT 'True if user explicitly set, false if inherited from global',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_module` (`user_id`,`module`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_udp_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_security_clearance
CREATE TABLE IF NOT EXISTS `user_security_clearance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `granted_by` int unsigned DEFAULT NULL,
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usc_user` (`user_id`),
  KEY `idx_usc_classification_id` (`classification_id`),
  KEY `idx_usc_expires_at` (`expires_at`),
  KEY `idx_usc_granted_by` (`granted_by`),
  CONSTRAINT `fk_usc_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_security_clearance_log
CREATE TABLE IF NOT EXISTS `user_security_clearance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `action` enum('granted','revoked','updated','expired') NOT NULL,
  `changed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: viewer_3d_settings
CREATE TABLE IF NOT EXISTS `viewer_3d_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: watermark_setting
CREATE TABLE IF NOT EXISTS `watermark_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: watermark_type
CREATE TABLE IF NOT EXISTS `watermark_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image_file` varchar(255) NOT NULL,
  `position` varchar(50) DEFAULT 'repeat',
  `opacity` decimal(3,2) DEFAULT '0.30',
  `active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

-- Table: library_settings
CREATE TABLE IF NOT EXISTS `library_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: email_setting defaults
INSERT IGNORE INTO `email_setting` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
('smtp_enabled', '0', 'boolean', 'smtp', 'Enable email sending'),
('smtp_host', '', 'text', 'smtp', 'SMTP server hostname'),
('smtp_port', '587', 'number', 'smtp', 'SMTP server port'),
('smtp_encryption', 'tls', 'text', 'smtp', 'Encryption type (tls, ssl, or empty)'),
('smtp_username', '', 'text', 'smtp', 'SMTP username'),
('smtp_password', '', 'password', 'smtp', 'SMTP password'),
('smtp_from_email', '', 'email', 'smtp', 'From email address'),
('smtp_from_name', 'AtoM Archive', 'text', 'smtp', 'From name'),
('notify_new_researcher', '', 'email', 'notifications', 'Email to notify of new researcher registrations'),
('notify_new_booking', '', 'email', 'notifications', 'Email to notify of new booking requests'),
('notify_access_request', '', 'email', 'notifications', 'Email to notify of access requests'),
('template_welcome', 'Welcome to our archive. Your registration has been received.', 'textarea', 'templates', 'Welcome email template'),
('template_booking_confirm', 'Your booking has been confirmed.', 'textarea', 'templates', 'Booking confirmation template'),
('template_access_approved', 'Your access request has been approved.', 'textarea', 'templates', 'Access approved template');

-- Table: atom_isbn_provider
CREATE TABLE IF NOT EXISTS `atom_isbn_provider` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `api_endpoint` varchar(500) NOT NULL,
  `api_key_setting` varchar(100) DEFAULT NULL,
  `priority` int DEFAULT 10,
  `enabled` tinyint(1) DEFAULT 1,
  `rate_limit_per_minute` int DEFAULT 100,
  `response_format` varchar(20) DEFAULT 'json',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default ISBN providers
INSERT IGNORE INTO atom_isbn_provider (name, slug, api_endpoint, api_key_setting, priority, enabled, rate_limit_per_minute, response_format) VALUES
('Open Library', 'openlibrary', 'https://openlibrary.org/api/books', NULL, 10, 1, 100, 'json'),
('Google Books', 'googlebooks', 'https://www.googleapis.com/books/v1/volumes', NULL, 20, 1, 100, 'json'),
('WorldCat', 'worldcat', 'https://www.worldcat.org/webservices/catalog/content/isbn/', NULL, 30, 0, 10, 'marcxml');


-- Migration tracking table
CREATE TABLE IF NOT EXISTS atom_migration (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL UNIQUE,
  batch INT NOT NULL DEFAULT 1,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Library cover queue for async processing
CREATE TABLE IF NOT EXISTS atom_library_cover_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  information_object_id INT UNSIGNED NOT NULL,
  isbn VARCHAR(20) NOT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  attempts TINYINT DEFAULT 0,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  INDEX idx_status (status),
  INDEX idx_io_id (information_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Security Classification Object relationship
CREATE TABLE IF NOT EXISTS security_classification_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    classification_id BIGINT UNSIGNED NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sco_object (object_id),
    INDEX idx_sco_classification (classification_id),
    UNIQUE KEY unique_object_classification (object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- SEED DATA: Required AHG Plugins (DO NOT REMOVE)
-- =============================================================================

-- Required plugins into atom_plugin (for Symfony/AtoM loading)
INSERT INTO atom_plugin (name, class_name, is_enabled, is_core, is_locked, load_order, category, created_at, updated_at)
VALUES
('ahgThemeB5Plugin', 'ahgThemeB5PluginConfiguration', 0, 1, 1, 10, 'theme', NOW(), NOW()),
('ahgSecurityClearancePlugin', 'ahgSecurityClearancePluginConfiguration', 1, 1, 1, 20, 'ahg', NOW(), NOW()),
('ahgDisplayPlugin', 'ahgDisplayPluginConfiguration', 1, 1, 1, 30, 'ahg', NOW(), NOW())
ON DUPLICATE KEY UPDATE is_core = 1, is_locked = 1;

-- Required plugins into atom_extension (for extension manager)
INSERT INTO atom_extension (machine_name, display_name, version, description, status, protection_level, installed_at, enabled_at, created_at)
VALUES
('ahgThemeB5Plugin', 'AHG Bootstrap 5 Theme', '1.0.0', 'AHG Bootstrap 5 theme with enhanced UI', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgSecurityClearancePlugin', 'Security Clearance', '1.0.0', 'Security classification system for records', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgDisplayPlugin', 'Display Mode Manager', '1.0.0', 'Display mode switching for GLAM sectors', 'enabled', 'system', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE protection_level = 'system';

-- NOTE: GLAM/DAM terms are created by individual plugins:
-- ahgMuseumPlugin, ahgLibraryPlugin, ahgGalleryPlugin, ahgDAMPlugin
-- Each plugin creates its own terms in its data/install.sql
