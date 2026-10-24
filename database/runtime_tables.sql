-- ---------------------------------------------------------------------------
-- Runtime infrastructure tables.
--
-- These belong to the framework itself rather than to any one plugin, and the
-- framework's own code reads them on ordinary requests - AhgSettingsService and
-- PluginLoader both do. They are kept here, separate from the full
-- database/install.sql, because that file creates all 273 tables in the estate
-- regardless of what is installed (issue #269) and must not be shipped whole.
--
-- bin/build-runtime-plugin concatenates this with encryption_tables.sql to make
-- ahgRuntimePlugin's install.sql. Without ahg_settings the runtime loads and the
-- site stays up, but every settings read fails with "Table 'ahg_settings' doesn't
-- exist" - logged, swallowed, and easy to miss because nothing 500s.
-- ---------------------------------------------------------------------------

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

-- ahg_password_reset: outstanding password-reset tokens, one per user.
-- The reset code used to write user.reset_token / reset_token_expiry, columns base
-- AtoM's user table does not have: the write failed into the log, the email went
-- out anyway, and the link in it crashed. Base tables are not altered, so the
-- token lives here. Only a SHA-256 hash is stored; the token exists in the email.
CREATE TABLE IF NOT EXISTS ahg_password_reset (
    user_id INT NOT NULL COMMENT 'user.id - one outstanding reset per user',
    token_hash CHAR(64) NOT NULL COMMENT 'sha256 of the emailed token',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uniq_token_hash (token_hash),
    CONSTRAINT fk_ahg_password_reset_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
