# CLAUDE.md - MANDATORY RULES FOR ATOM AHG FRAMEWORK

> ⚠️ **READ THIS ENTIRE FILE BEFORE MAKING ANY CHANGES**
> ⚠️ **FAILURE TO FOLLOW THESE RULES WILL BREAK THE SYSTEM**

---

## 🚨 HERATIO STANDALONE MODE — ABSOLUTE QUALITY STANDARD

**NO MVP. NO PARTIAL. NO "BASIC VIEWER". NO SHORTCUTS.**

When Heratio standalone mode (`.heratio_enabled`) is activated, every page, screen, and feature
MUST be **100% identical** to what Symfony/AtoM delivers. This means:

1. **Full screens** — every page must render completely, with the same layout, sidebar, breadcrumbs, digital object viewers, carousels, facets, and metadata panels as base AtoM + ahgThemeB5Plugin.
2. **Full functionality** — edit, delete, copy, move, import, export, search, browse, admin, settings — everything must work. No "read-only MVP".
3. **Full theme** — the Bootstrap 5 theme (ahgThemeB5Plugin) must render identically. No stripped-down HTML wrappers. No inline `<div class="container">` fallbacks.
4. **Full menus** — all navigation dropdowns (Add, Manage, Import, Admin, AHG Plugins, user profile) must show every item that Symfony shows, based on user permissions and enabled plugins.
5. **Full digital objects** — images, thumbnails, reference copies, IIIF viewer, media player, 3D viewer — all must render as they do in Symfony mode.
6. **Full settings** — AHG Settings must show all sections for all enabled plugins, not a subset.

**If a feature cannot be implemented to full parity, do NOT implement a degraded version.**
Instead, document what is missing and leave that route handled by Symfony (dual-stack fallback).

**The StandaloneViewerController (generic entity viewer) is a TEMPORARY diagnostic tool only.**
It must NEVER be the production rendering path for any entity type. Production entity views
must use the same templates (or Blade equivalents) that ahgThemeB5Plugin uses.

**Test criterion:** A user must not be able to tell whether they are on Symfony or Heratio
by looking at any page. If they can tell, it is not ready.

---

## 🚫 HARD STOPS - NEVER DO THESE

### Files You Must NEVER Modify Without Explicit Approval
```
bin/install
bin/release
database/install.sql
config/ProjectConfiguration.class.php.template
```

**Action Required:** Always show proposed changes and wait for user confirmation.

### Code Patterns You Must NEVER Use

1. **NEVER** add `INSERT INTO atom_plugin` statements in any `data/install.sql` file
2. **NEVER** use Laravel Query Builder in Plugin Manager - use PDO/Propel only
3. **NEVER** modify core AtoM table schemas (object, information_object, actor, term, taxonomy, user, repository, digital_object)
4. **NEVER** use `ADD COLUMN IF NOT EXISTS` - MySQL version doesn't support it
5. **NEVER** use `render_title()` or `__toString()` in templates
6. **NEVER** use `[$resource, 'module'=>'x']` URL syntax in templates

---

## 🔒 LOCKED PLUGINS - DO NOT MODIFY

These plugins are stable and locked. **Do not modify any files within these plugins:**

- `ahgLibraryPlugin`
- `ahgBackupPlugin`
- `ahgAuditTrailPlugin`
- `ahgResearchPlugin`
- `ahgThemeB5Plugin`
- `ahgSecurityClearancePlugin`

If changes seem necessary, **STOP and ask the user first**.

---

## ✅ REQUIRED BEFORE ANY CODE CHANGE

### Pre-Change Checklist
- [ ] Read this entire CLAUDE.md file
- [ ] Check attached documents (Extension System Roadmap.docx, AtoM AHG Framework.docx)
- [ ] Verify the file is NOT in the locked list
- [ ] Determine correct database approach (PDO vs Laravel Query Builder)
- [ ] Show proposed changes to user
- [ ] Wait for explicit approval
- [ ] Plan to test on 192.168.0.112 first

---

## 🏗️ ARCHITECTURE RULES

### Database Access Patterns

| Context | Use This | NOT This |
|---------|----------|----------|
| Plugin Manager | PDO/Propel | Laravel Query Builder |
| Everything else | Laravel Query Builder | Raw PDO |
| atom_plugin table | PDO only | Laravel |
| atom_plugin_audit table | PDO only | Laravel |

### Why Plugin Manager Uses PDO
Symfony autoloader conflicts prevent Laravel from working properly when managing the `atom_plugin` and `atom_plugin_audit` tables. This is a **technical requirement**, not a preference.

```php
// ✅ CORRECT - Plugin Manager
$conn = Propel::getConnection();
$stmt = $conn->prepare("SELECT * FROM atom_plugin WHERE is_enabled = 1");
$stmt->execute();

// ❌ WRONG - Plugin Manager
DB::table('atom_plugin')->where('is_enabled', 1)->get();
```

### Template Syntax Rules

```php
// ✅ CORRECT URL syntax
url_for(['module' => 'informationobject', 'slug' => $resource->slug])

// ❌ WRONG URL syntax
url_for([$resource, 'module' => 'informationobject'])

// ✅ CORRECT title display
$resource->title ?? $resource->slug

// ❌ WRONG title display
render_title($resource)
$resource->__toString()
```

### Namespace Convention
```php
namespace AtomExtensions\Repositories;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
```

---

## 📁 SERVER CONFIGURATION

### Paths
```bash
ARCHIVE_PATH="/usr/share/nginx/archive"
FRAMEWORK_PATH="${ARCHIVE_PATH}/atom-framework"
PLUGIN_PATH="${ARCHIVE_PATH}/plugins"
AHG_PLUGINS_PATH="${ARCHIVE_PATH}/atom-ahg-plugins"
```

### Database Credentials
- **Server 112 (Dev):** host=localhost, user=root, pass=(none), db=archive
- **Server 154 (Test):** host=localhost, user=atom, pass=AtoM@123, db=atom

### Testing Workflow
1. Always test on 192.168.0.112 FIRST
2. Verify functionality works
3. Then push to 192.168.0.154 for validation

---

## 🔧 PLUGIN SYSTEM

### Plugin Loading
- **Source of truth:** `atom_plugin` table
- **Legacy (UI only):** `setting_i18n` id=1
- **Function:** `loadPluginsFromDatabase()` in ProjectConfiguration

### Plugin Data Files
Plugin `data/install.sql` files should contain:
- ✅ Taxonomy terms
- ✅ Default settings
- ✅ Controlled vocabulary

Plugin `data/install.sql` files must NEVER contain:
- ❌ `INSERT INTO atom_plugin` statements

### Enabling Plugins
Plugins are enabled manually via CLI:
```bash
php bin/atom extension:enable ahgPluginName
```

---

## 📝 CODE STYLE

### PHP CS Fixer Compliance
All code must be PHP CS Fixer compliant.

### File Creation Method
Use bash heredoc for new files:
```bash
cat > /path/to/file.php << 'EOF'
<?php
// content here
