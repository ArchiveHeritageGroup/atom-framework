-- Migration: 005_seed_core_plugins
-- Register core AtoM plugins with protection levels

-- Core plugins (cannot disable, cannot uninstall)
INSERT INTO atom_extension (machine_name, display_name, version, description, status, protection_level, installed_at, enabled_at, created_at)
VALUES 
('qbAclPlugin', 'Access Control', '2.10.0', 'Core AtoM access control system', 'enabled', 'core', NOW(), NOW(), NOW()),
('sfPropelPlugin', 'Propel ORM', '2.10.0', 'Core database ORM layer', 'enabled', 'core', NOW(), NOW(), NOW()),
('sfDrupalPlugin', 'Drupal Auth', '2.10.0', 'Core authentication system', 'enabled', 'core', NOW(), NOW(), NOW()),
('arElasticSearchPlugin', 'Elasticsearch', '2.10.0', 'Core search functionality', 'enabled', 'core', NOW(), NOW(), NOW()),
('sfHistoryPlugin', 'History', '2.10.0', 'Undo/redo functionality', 'enabled', 'core', NOW(), NOW(), NOW()),
('sfTranslatePlugin', 'Translation', '2.10.0', 'Internationalization support', 'enabled', 'core', NOW(), NOW(), NOW()),
('sfWebBrowserPlugin', 'Web Browser', '2.10.0', 'HTTP client functionality', 'enabled', 'core', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE protection_level = 'core';

-- System plugins (can disable, cannot uninstall)  
INSERT INTO atom_extension (machine_name, display_name, version, description, status, protection_level, installed_at, enabled_at, created_at)
VALUES 
('arDominionB5Plugin', 'Dominion Theme (Base)', '2.10.0', 'Base Bootstrap 5 theme', 'enabled', 'system', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE protection_level = 'system';
