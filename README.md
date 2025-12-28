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

## What Gets Installed

### Database Tables
- **Core Framework**: atom_plugin, atom_plugin_audit, ahg_settings
- **Display Plugin**: display_profile, display_level, display_field, user_display_preference
- **Security Clearance**: security_classification, user_security_clearance, object_security_classification
- **Research Plugin**: research_researcher, research_booking, research_reading_room, research_collection
- **Access Request**: access_request, access_request_approver, object_access_grant
- **Email Settings**: email_setting

### Plugins Enabled
- arDisplayPlugin
- arSecurityClearancePlugin
- arResearchPlugin
- arAccessRequestPlugin
- arAHGThemeB5Plugin

### Default Data
- 5 security classification levels (Unclassified → Top Secret)
- Default watermark types
- Default display profile

## Configuration

The installer automatically:
1. Creates all database tables
2. Creates symlinks for plugins
3. Updates ProjectConfiguration.class.php
4. Copies theme assets to dist/
5. Enables plugins in setting_i18n

## Switching Themes

Go to **Admin → Themes** to switch between:
- arDominionB5Plugin (AtoM default)
- arAHGThemeB5Plugin (AHG custom theme)

## Directory Structure
```
atom-framework/
├── bin/
│   └── install          # Installation script
├── bootstrap.php        # Framework loader
├── src/
│   ├── Helpers/         # CultureHelper, etc.
│   ├── Repositories/    # Data access classes
│   └── Services/        # Business logic
├── config/
│   └── database.php     # DB configuration
└── vendor/              # Composer dependencies
```

## Version

v1.0.0 - Base release

## License

GPL-3.0

## Author

The Archive and Heritage Group
