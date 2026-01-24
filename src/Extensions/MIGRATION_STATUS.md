# Extensions Migration Status

This document tracks the progress of migrating extension code from `atom-framework/src/Extensions/` to their dedicated plugins.

## Completed

### Contract/Provider System (Phase 4)
- [x] Created `AtomFramework\Contracts\PiiRedactionProviderInterface`
- [x] Created `AtomFramework\Contracts\Model3DProviderInterface`
- [x] Created `AtomFramework\Contracts\IiifProviderInterface`
- [x] Created `AtomFramework\Providers` registry class
- [x] Updated `IiifManifestService` to use Providers instead of require_once

### Provider Implementations
- [x] `ahgPrivacyPlugin` - `PiiRedactionProvider` registered
- [x] `ahg3DModelPlugin` - `Model3DProvider` registered

### Plugin Hardcoding Removal (Phase 6) - 2026-01-24
- [x] Created `SectorProviderInterface` for plugins to register sector support
- [x] Created `MetadataTemplateProviderInterface` for plugins to register templates
- [x] Created `SectorRegistry` for dynamic sector registration
- [x] Created `MetadataTemplateRegistry` for dynamic template registration
- [x] Updated `LevelOfDescriptionService` to use `SectorRegistry` with fallback
- [x] Updated `QubitMetadataRoute` to use `MetadataTemplateRegistry` with fallback
- [x] Updated `AhgMetadataRoute` to use registry methods
- [x] Updated `DisplayStandardHelper` to use `MetadataTemplateRegistry`
- [x] Removed hardcoded AHG plugin references from `QubitMetadataRoute::$METADATA_PLUGINS`
- [x] Deprecated `SECTOR_PLUGINS` constant (use `SectorRegistry` instead)

## Core Framework Files (Keep in Framework)

These files manage the extension/plugin system and should remain in the framework:

| File | Purpose |
|------|---------|
| `ExtensionManager.php` | Main extension management CLI |
| `ExtensionManagerProtected.php` | Protected operations |
| `ExtensionProtection.php` | Protection mechanisms |
| `MigrationHandler.php` | Database migrations |
| `PluginFetcher.php` | GitHub plugin fetcher |
| `Handlers/` | Extension data handlers |
| `Contracts/` | Extension system contracts |

## Extensions to Migrate (Phase 5)

### IiifViewer → ahgIiifPlugin
**Status:** In progress
**Priority:** High
**Complexity:** High - actively used from framework path

Files to migrate:
- `Controllers/IiifController.php`
- `Controllers/MediaController.php`
- `Services/IiifManifestService.php`
- `Services/AnnotationService.php`
- `Services/OcrService.php`
- `Services/TranscriptionService.php`
- `Services/ViewerService.php`
- `Services/SnippetService.php`
- `Services/MediaMetadataService.php`
- `Services/MediaUploadProcessor.php`
- `Helpers/IiifViewerHelper.php`
- `Helpers/MediaHelper.php`
- `Hooks/MediaUploadHook.php`
- `public/` (routes, router, nginx configs)
- `config/` (nginx configs)

**Dependencies:**
- Uses PiiRedactionProvider (now via Providers class)
- Referenced by ahgIiifPlugin helpers
- Referenced by nginx configuration

### Ar3dViewer → ahg3DModelPlugin
**Status:** Decoupled via provider
**Priority:** Low
**Notes:**
- Model3DProvider now provides the functionality
- ahg3DModelPlugin has its own implementation
- This extension can be removed once ahg3DModelPlugin is verified complete

### Security → ahgSecurityClearancePlugin
**Status:** Pending
**Priority:** Medium

Files to migrate:
- `Services/SecurityComplianceService.php`
- `Services/AccessJustificationService.php`
- `Controllers/SecurityComplianceController.php`
- `Database/SecurityMigrations.php`
- `Routes/routes.php`
- `Views/` (blade templates)

### Privacy → ahgPrivacyPlugin
**Status:** Partially complete
**Priority:** Medium
**Notes:**
- PII redaction functionality now via provider
- ahgPrivacyPlugin has comprehensive implementation
- These views/controllers may be legacy duplicates

Files to evaluate:
- `Services/PrivacyComplianceService.php`
- `Controllers/PrivacyComplianceController.php`
- `Database/PrivacyMigrations.php`
- `Routes/routes.php`
- `Views/` (blade templates)

### Spectrum → ahgSpectrumPlugin
**Status:** Pending
**Priority:** Low

Files to migrate:
- `Services/LoanService.php`
- `Services/ProvenanceService.php`
- `Services/LabelService.php`
- `SpectrumAdapter.php`
- `Database/Migrations/`

### Grap → ahgHeritageAccountingPlugin
**Status:** Pending
**Priority:** Low
**Notes:** GRAP was renamed to Heritage Accounting Plugin

Files to migrate:
- `Services/GrapService.php`
- `GrapAdapter.php`
- `Database/Migrations/`

### Contact
**Status:** Pending
**Priority:** Low
**Notes:** Need to determine target plugin (ahgCorePlugin?)

Files to migrate:
- `Repositories/ContactInformationRepository.php`
- `Services/ContactInformationService.php`
- `templates/` (blade templates)

### ZoomPan → ahgDisplayPlugin or ahgIiifPlugin
**Status:** Pending
**Priority:** Low
**Notes:** Static assets for fallback viewer when IIIF not available

Files to migrate:
- `public/icons/` (PNG icons)
- `public/zoom-pan.css`
- `public/zoom-pan.js`

## Migration Process

When migrating an extension:

1. **Create provider interface** in `atom-framework/src/Contracts/` if cross-plugin communication needed
2. **Create provider implementation** in target plugin's `lib/Provider/`
3. **Register provider** in plugin's Configuration class
4. **Copy files** to appropriate locations in target plugin
5. **Update namespaces** (e.g., `AtomFramework\Extensions\X` → `AhgXPlugin\`)
6. **Update references** in templates and other plugins
7. **Test functionality** thoroughly
8. **Remove old files** from framework after verification
9. **Update this document**

## Notes

- The IiifViewer extension is the most complex and actively used
- Provider pattern allows gradual migration without breaking changes
- Some extensions may be duplicates of plugin code - verify before migrating
