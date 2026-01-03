-- Migration: 006_add_missing_extension_columns
-- Adds missing columns to atom_extension table for existing installs
-- Columns may already exist, errors are ignored by migration runner

ALTER TABLE atom_extension ADD COLUMN license VARCHAR(50) DEFAULT 'GPL-3.0' AFTER author;
ALTER TABLE atom_extension ADD COLUMN requires_atom VARCHAR(20) AFTER requires_framework;
ALTER TABLE atom_extension ADD COLUMN requires_php VARCHAR(20) AFTER requires_atom;
ALTER TABLE atom_extension ADD COLUMN optional_dependencies JSON AFTER dependencies;
ALTER TABLE atom_extension ADD COLUMN helpers JSON AFTER shared_tables;
ALTER TABLE atom_extension ADD COLUMN install_task VARCHAR(100) AFTER helpers;
ALTER TABLE atom_extension ADD COLUMN uninstall_task VARCHAR(100) AFTER install_task;
ALTER TABLE atom_extension ADD COLUMN config_path VARCHAR(500) AFTER uninstall_task;
ALTER TABLE atom_extension ADD COLUMN disabled_at DATETIME AFTER enabled_at;
ALTER TABLE atom_extension_setting ADD COLUMN setting_group VARCHAR(100) DEFAULT 'general' AFTER setting_type;
