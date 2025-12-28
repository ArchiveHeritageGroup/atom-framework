# AtoM Framework

Laravel Query Builder integration for Access to Memory (AtoM) 2.10, providing modern database access patterns while maintaining full compatibility with AtoM's Symfony architecture.

## Requirements

- AtoM 2.10
- PHP 8.1+
- MySQL 8.0+
- Composer

## Quick Install
```bash
cd /usr/share/nginx/atom

# Clone repositories
git clone https://github.com/ArchiveHeritageGroup/atom-framework.git
git clone https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git

# Install dependencies
cd atom-framework
composer install

# Run installer
bash bin/install

# Restart PHP
sudo systemctl restart php8.3-fpm
```

## Architecture
```
/usr/share/nginx/atom/                    # AtoM root
├── atom-framework/                       # This repository - Laravel core
│   ├── bin/install                       # Installer script
│   ├── bootstrap.php                     # Framework loader
│   ├── src/
│   │   ├── Helpers/                      # CultureHelper, SlugHelper, etc.
│   │   ├── Repositories/                 # Data access classes
│   │   └── Services/                     # Business logic
│   ├── config/
│   └── vendor/                           # Composer dependencies
│
├── atom-ahg-plugins/                     # Plugin repository (separate clone)
│   ├── arAHGThemeB5Plugin/               # Bootstrap 5 theme
│   ├── arDisplayPlugin/                  # Display profiles & modes
│   ├── arSecurityClearancePlugin/        # Security classification
│   ├── arResearchPlugin/                 # Researcher management
│   └── arAccessRequestPlugin/            # Access request workflow
│
├── plugins/                              # AtoM plugins directory
│   ├── arAHGThemeB5Plugin -> ../atom-ahg-plugins/arAHGThemeB5Plugin
│   ├── arDisplayPlugin -> ../atom-ahg-plugins/arDisplayPlugin
│   └── ...                               # Symlinks created by installer
│
└── config/
    └── ProjectConfiguration.class.php    # Modified to load framework
```

## What Gets Installed

The installer creates database tables, symlinks, and configuration:

### Database Tables

| Category | Tables | Purpose |
|----------|--------|---------|
| **Core Framework** | `atom_plugin`, `atom_plugin_audit`, `ahg_settings` | Plugin management and AHG settings storage |
| **Display Plugin** | `display_profile`, `display_profile_i18n`, `display_level`, `display_level_i18n`, `display_field`, `display_field_i18n`, `display_mode_global`, `user_display_preference`, `display_object_config`, `display_object_profile`, `display_collection_type`, `display_collection_type_i18n` | Configurable display profiles, levels of description, user view preferences |
| **Security Clearance** | `security_classification`, `user_security_clearance`, `user_security_clearance_log`, `object_security_classification`, `security_compartment`, `security_access_log`, `security_audit_log`, `security_clearance_history`, `security_compliance_log`, `security_declassification_schedule`, `security_retention_schedule`, `security_2fa_session`, `security_access_condition_link`, `watermark_type`, `watermark_setting`, `custom_watermark`, `object_watermark_setting`, `security_watermark_log`, `user_compartment_access` | Security classification levels, user clearances, object classification, audit trails, watermarks |
| **Research Plugin** | `research_researcher`, `research_reading_room`, `research_booking`, `research_material_request`, `research_collection`, `research_collection_item`, `research_annotation`, `research_saved_search`, `research_citation_log`, `research_password_reset` | Researcher registration, reading room bookings, collections, annotations |
| **Access Request** | `access_request`, `access_request_approver`, `access_request_log`, `access_request_scope`, `access_request_justification`, `access_justification_template`, `object_access_grant`, `security_access_request` | Access request workflow, approvers, grants |
| **Email** | `email_setting` | SMTP configuration for notifications |
| **IIIF** | `iiif_viewer_settings` | IIIF viewer configuration |

### Plugins Enabled

The installer adds these plugins to AtoM's plugin configuration:

- **arDisplayPlugin** - Display mode switching and extended levels
- **arSecurityClearancePlugin** - Security classification system
- **arResearchPlugin** - Researcher portal and bookings
- **arAccessRequestPlugin** - Access request workflow
- **arAHGThemeB5Plugin** - Custom Bootstrap 5 theme

### Default Data

- 5 security classification levels: Unclassified, Restricted, Confidential, Secret, Top Secret
- 3 watermark types: Draft, Confidential, Restricted
- Default display profile for archival records
- Default SMTP settings structure

## Post-Installation

### Switching Themes

Go to **Admin → Themes** to switch between:
- **arDominionB5Plugin** - AtoM default Bootstrap 5 theme
- **arAHGThemeB5Plugin** - AHG custom theme with enhanced features

### Enable Optional Features
```sql
-- Enable DAM Tools (TIFF to PDF, 3D thumbnails)
INSERT INTO ahg_settings (setting_key, setting_value, setting_group) 
VALUES ('dam_tools_enabled', '1', 'general');
```

## How It Works

1. **atom-framework** provides Laravel Query Builder via Composer
2. **atom-ahg-plugins** contains the actual AtoM plugins
3. The installer creates symlinks from `plugins/` to `atom-ahg-plugins/`
4. ProjectConfiguration.class.php loads the framework bootstrap
5. Plugins use framework services for modern database operations

## Version

v1.0.0

## License

GPL-3.0

## Author

The Archive and Heritage Group
