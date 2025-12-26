<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class CreateExtensionTables
{
    public function up(): void
    {
        $pdo = Capsule::connection()->getPdo();

        // Plugin Manager table (Symfony plugin loading)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_plugin (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                class_name VARCHAR(255) NOT NULL DEFAULT '',
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension (
                id INT AUTO_INCREMENT PRIMARY KEY,
                machine_name VARCHAR(100) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                version VARCHAR(20) NOT NULL,
                description TEXT,
                author VARCHAR(255),
                license VARCHAR(50) DEFAULT 'GPL-3.0',
                status ENUM('installed','enabled','disabled','pending_removal') DEFAULT 'installed',
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
                UNIQUE KEY uk_machine_name (machine_name),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension_setting (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_id INT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                setting_type ENUM('string','integer','boolean','json','array') DEFAULT 'string',
                description VARCHAR(500),
                is_system TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_extension_setting (extension_id, setting_key),
                CONSTRAINT fk_setting_extension FOREIGN KEY (extension_id) REFERENCES atom_extension(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension_audit (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_id INT NULL,
                extension_name VARCHAR(100) NOT NULL,
                action ENUM('discovered','installed','enabled','disabled','uninstalled','upgraded','downgraded','backup_created','backup_restored','data_deleted','config_changed','error') NOT NULL,
                performed_by INT NULL,
                details JSON,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_extension_name (extension_name),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension_pending_deletion (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_name VARCHAR(100) NOT NULL,
                table_name VARCHAR(100) NOT NULL,
                record_count INT DEFAULT 0,
                backup_path VARCHAR(500),
                backup_size BIGINT,
                delete_after DATETIME NOT NULL,
                status ENUM('pending','processing','deleted','restored','cancelled','failed') DEFAULT 'pending',
                error_message TEXT,
                processed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_extension_name (extension_name),
                INDEX idx_status (status),
                INDEX idx_delete_after (delete_after)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension_widget (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_id INT NOT NULL,
                widget_key VARCHAR(100) NOT NULL,
                widget_type ENUM('stat_card','chart','list','table','html','custom') NOT NULL,
                title VARCHAR(255) NOT NULL,
                description VARCHAR(500),
                icon VARCHAR(50),
                data_callback VARCHAR(255) NOT NULL,
                template VARCHAR(255),
                dashboard VARCHAR(50) DEFAULT 'central',
                section VARCHAR(50),
                cache_ttl INT DEFAULT 300,
                sort_order INT DEFAULT 100,
                is_enabled TINYINT(1) DEFAULT 1,
                config JSON,
                permissions JSON,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_widget_key (widget_key),
                CONSTRAINT fk_widget_extension FOREIGN KEY (extension_id) REFERENCES atom_extension(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension_menu (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_id INT NOT NULL,
                menu_key VARCHAR(100) NOT NULL,
                parent_key VARCHAR(100),
                menu_location ENUM('main','admin','user','footer','mobile') DEFAULT 'main',
                title VARCHAR(255) NOT NULL,
                title_i18n VARCHAR(100),
                icon VARCHAR(50),
                route VARCHAR(255),
                route_params JSON,
                badge_callback VARCHAR(255),
                badge_cache_ttl INT DEFAULT 60,
                visibility_callback VARCHAR(255),
                permissions JSON,
                context JSON,
                sort_order INT DEFAULT 100,
                is_enabled TINYINT(1) DEFAULT 1,
                is_separator TINYINT(1) DEFAULT 0,
                css_class VARCHAR(100),
                target VARCHAR(20),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_menu_key (menu_key),
                CONSTRAINT fk_menu_extension FOREIGN KEY (extension_id) REFERENCES atom_extension(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS atom_extension_admin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                extension_id INT NOT NULL,
                admin_key VARCHAR(100) NOT NULL,
                section VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description VARCHAR(500),
                icon VARCHAR(50),
                route VARCHAR(255) NOT NULL,
                route_params JSON,
                permissions JSON,
                badge_callback VARCHAR(255),
                sort_order INT DEFAULT 100,
                is_enabled TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_admin_key (admin_key),
                CONSTRAINT fk_admin_extension FOREIGN KEY (extension_id) REFERENCES atom_extension(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Default settings
        $pdo->exec("
            INSERT IGNORE INTO atom_extension_setting (extension_id, setting_key, setting_value, setting_type, description, is_system) VALUES
            (NULL, 'grace_period_days', '30', 'integer', 'Days before deleted extension data is permanently removed', 1),
            (NULL, 'backup_on_uninstall', 'ask', 'string', 'Backup behavior: ask, always, never', 1),
            (NULL, 'preserve_shared_data', '1', 'boolean', 'Preserve shared table data on uninstall', 1),
            (NULL, 'framework_version', '1.0.0', 'string', 'Current atom-framework version', 1),
            (NULL, 'extensions_path', '/usr/share/nginx/atom/plugins', 'string', 'Path to extensions directory', 1),
            (NULL, 'backup_path', '/usr/share/nginx/atom/data/backups/extensions', 'string', 'Path for extension backups', 1)
        ");

	// Seed default plugins
        $pdo->exec("
            INSERT IGNORE INTO atom_plugin (name, class_name, is_enabled, is_core, load_order, category) VALUES
            ('arDominionB5Plugin', 'arDominionB5PluginConfiguration', 1, 1, 1, 'theme'),
            ('arOaiPlugin', 'arOaiPluginConfiguration', 1, 1, 10, 'core'),
            ('arRestApiPlugin', 'arRestApiPluginConfiguration', 1, 1, 11, 'core'),
            ('sfIsadPlugin', 'sfIsadPluginConfiguration', 1, 1, 20, 'metadata'),
            ('sfIsdfPlugin', 'sfIsdfPluginConfiguration', 1, 1, 21, 'metadata'),
            ('sfIsaarPlugin', 'sfIsaarPluginConfiguration', 1, 1, 22, 'metadata'),
            ('sfIsdiahPlugin', 'sfIsdiahPluginConfiguration', 1, 1, 23, 'metadata'),
            ('sfEacPlugin', 'sfEacPluginConfiguration', 1, 1, 24, 'metadata'),
            ('sfEadPlugin', 'sfEadPluginConfiguration', 1, 1, 25, 'metadata'),
            ('sfDcPlugin', 'sfDcPluginConfiguration', 1, 1, 26, 'metadata'),
            ('sfModsPlugin', 'sfModsPluginConfiguration', 1, 1, 27, 'metadata'),
            ('sfRadPlugin', 'sfRadPluginConfiguration', 1, 1, 28, 'metadata'),
            ('sfSkosPlugin', 'sfSkosPluginConfiguration', 1, 1, 29, 'metadata'),
            ('arDacsPlugin', 'arDacsPluginConfiguration', 1, 1, 30, 'metadata'),
            ('sfWebBrowserPlugin', 'sfWebBrowserPluginConfiguration', 1, 1, 40, 'core')
        ");

        // Log installation
        $pdo->exec("
            INSERT INTO atom_extension_audit (extension_name, action, details) VALUES
            ('atom-framework', 'installed', '{\"version\": \"1.0.0\", \"tables\": 9}')
        ");
    }

    public function down(): void
    {
        $pdo = Capsule::connection()->getPdo();
        $pdo->exec("DROP TABLE IF EXISTS atom_extension_admin");
        $pdo->exec("DROP TABLE IF EXISTS atom_extension_menu");
        $pdo->exec("DROP TABLE IF EXISTS atom_extension_widget");
        $pdo->exec("DROP TABLE IF EXISTS atom_extension_pending_deletion");
        $pdo->exec("DROP TABLE IF EXISTS atom_extension_audit");
        $pdo->exec("DROP TABLE IF EXISTS atom_extension_setting");
        $pdo->exec("DROP TABLE IF EXISTS atom_extension");
        $pdo->exec("DROP TABLE IF EXISTS atom_plugin_audit");
        $pdo->exec("DROP TABLE IF EXISTS atom_plugin");
    }
}
