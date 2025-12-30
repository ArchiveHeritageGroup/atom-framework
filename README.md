# AtoM Framework

Laravel Query Builder integration for Access to Memory (AtoM) 2.10

## Overview

This framework provides a modern Laravel-based foundation for AtoM 2.10, enabling:
- Laravel Query Builder (Illuminate\Database) instead of Propel ORM
- Extension Manager CLI for plugin management
- Shared services across all AHG plugins
- Repository/Service pattern architecture

## Requirements

- AtoM 2.10.x
- PHP 8.1+
- MySQL 8.0+
- Composer

## Installation
```bash
cd /usr/share/nginx/atom
git clone https://github.com/ArchiveHeritageGroup/atom-framework.git
cd atom-framework && composer install
bash bin/install
```

## Structure
```
atom-framework/
├── bin/                    # CLI tools
│   ├── atom                # Main CLI entry point
│   ├── install             # Installation script
│   ├── release             # Version management
│   ├── deploy.sh           # Deployment helper
│   └── fix-autoloader.sh   # Autoloader fix
├── config/                 # Configuration templates
├── database/
│   ├── install.sql         # Database schema
│   └── migrations/         # Migration files
├── scripts/                # Cron/maintenance scripts
│   ├── run-backup.sh
│   └── cleanup-backups.sh
└── src/
    ├── Extensions/         # Extension Manager
    ├── Helpers/            # Helper classes
    ├── Repositories/       # Base repositories
    └── Services/
        ├── Shared/         # Shared services across plugins
        │   ├── ahgFaceDetectionService.php
        │   ├── ahgMetadataExtractionTrait.php
        │   └── ahgUniversalMetadataExtractor.php
        └── ...             # Framework services
```

## CLI Commands
```bash
# Extension management
php bin/atom extension:discover
php bin/atom extension:enable <plugin>
php bin/atom extension:disable <plugin>

# Framework management
php bin/atom framework:version
php bin/atom framework:update
```

## Related

- [atom-ahg-plugins](https://github.com/ArchiveHeritageGroup/atom-ahg-plugins) - AHG Plugins collection
- [AtoM Documentation](https://www.accesstomemory.org/docs/)

## License

GPL-3.0 - See [LICENSE](LICENSE)

## Author

The Archive and Heritage Group
