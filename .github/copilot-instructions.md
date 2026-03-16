# GitHub Copilot Instructions — atom-framework

## Project Identity
This is the **AtoM AHG Framework** — a Laravel Query Builder integration layer for AtoM 2.10 (Symfony 1.4). Developed by The Archive and Heritage Group for GLAM institutions (Galleries, Libraries, Archives, Museums).

## Stack — Be Exact
- **AtoM:** 2.10 (Symfony 1.4 base — do NOT suggest Symfony 4/5/6 patterns)
- **Database ORM:** Laravel Query Builder (`Illuminate\Database\Capsule\Manager as DB`) — NOT Eloquent, NOT raw PDO (except Plugin Manager)
- **Exception:** Plugin Manager uses `Propel::getConnection()` + PDO due to Symfony autoloader conflicts
- **PHP:** 8.3 — use modern PHP (typed properties, match expressions, nullsafe operator)
- **MySQL:** 8.0 — `ADD COLUMN IF NOT EXISTS` does NOT work — use conditional checks instead
- **Frontend:** Bootstrap 5 only — do NOT suggest jQuery UI, Tailwind, or Bootstrap 4
- **Icons:** Bootstrap Icons (`bi-*`) primary, FontAwesome fallback

## Namespace & Imports
```php
namespace AtomExtensions\Repositories;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
```

## Critical Coding Rules
- All code must be **PHP CS Fixer compliant**
- NEVER use `render_title()` or `__toString()` — use `$resource->title ?? $resource->slug`
- NEVER use `[$resource, 'module' => 'x']` template pattern
- ALWAYS use `url_for(['module' => 'x', 'slug' => $resource->slug])`
- NEVER add `INSERT INTO atom_plugin` in `data/install.sql` files — plugins are enabled manually via CLI
- NEVER modify `install.sql` or `bin/install` — always propose changes and ask for approval first

## Database Tables — Never Modify Schema
These are core AtoM tables — never alter them:
`object`, `information_object`, `actor`, `term`, `taxonomy`, `setting`, `setting_i18n`, `user`, `repository`, `digital_object`

## Plugin Loading — Critical
- **Source of truth:** `atom_plugin` table (`is_enabled = 1`)
- `setting_i18n` id=1 is LEGACY — only for sfPluginAdmin UI compatibility
- `ProjectConfiguration.class.php` is NEVER patched — always replaced from template
- Template location: `atom-framework/config/ProjectConfiguration.class.php.template`

## Server Context
- Dev server: `192.168.0.112`
- AtoM root: `/usr/share/nginx/archive`
- Framework: `/usr/share/nginx/archive/atom-framework`
- Plugins: `/usr/share/nginx/archive/atom-ahg-plugins`
- Database: `archive` (user: `root`, no password on dev)
- Database config: `/usr/share/nginx/archive/config/config.php`

## What NOT to Suggest
- Do not suggest Docker, Laravel Artisan, or standalone Laravel patterns
- Do not suggest modifying core AtoM files
- Do not suggest Eloquent models — use Query Builder only
- Do not suggest Bootstrap 4 or jQuery UI components
