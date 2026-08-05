# Installation Guide

Install guide for the **AtoM AHG Framework** (Layer 1) and the **AHG Extensions /
plugins** (Layer 2). For the project overview, features and CLI reference see
**[README.md](README.md)**; for the full plugin catalogue and version compatibility
see the **[atom-ahg-plugins README](https://github.com/ArchiveHeritageGroup/atom-ahg-plugins#readme)**.

The AHG stack installs *on top of a working base AtoM instance* - it does not
replace AtoM.

## Prerequisites

A running base **AtoM** install (see Artefactual's official AtoM installation), plus:

- AtoM 2.10.x
- **PHP 8.3** (tested/supported; the framework's dependencies require PHP >= 8.2)
- MySQL 8.0
- Elasticsearch 7.10 (AtoM 2.10 requires ES 7.x; 7.11-8.x are not supported)
- Composer 2.x, Node.js 18+ (theme asset build)
- ImageMagick + `php8.3-imagick` (base AtoM requirement - digital-object thumbnail/reference derivatives are generated via Imagick; without it, image uploads have no derivatives)

> On Ubuntu, if you install `php8.3-fpm` from the `ondrej/php` PPA, its unit ships
> `ProtectSystem=full` (mounts `/usr` read-only). If AtoM lives under `/usr/share/nginx`,
> add a drop-in granting write access:
> `/etc/systemd/system/php8.3-fpm.service.d/atom.conf` with
> `[Service]` and `ReadWritePaths=/usr/share/nginx/atom`, then `systemctl daemon-reload`
> and restart php8.3-fpm.

## Third-party dependencies

Nothing here needs to be downloaded by hand. This section documents what gets
pulled in, so you know what to expect on an air-gapped or firewalled host.

### PHP packages (Composer)

`composer install` inside `atom-framework/` resolves **15 direct packages** (plus a
PHP version constraint) into `atom-framework/vendor/`, 85 including transitive
dependencies.
That directory is gitignored, so a fresh clone must run Composer before the
framework will boot.

| Package | Used for |
|---|---|
| `illuminate/database`, `view`, `filesystem`, `events`, `routing`, `http` | Laravel Query Builder, Blade, routing - the framework's foundation |
| `webonyx/graphql-php` | GraphQL API endpoint (ahgGraphQLPlugin) |
| `phpoffice/phpspreadsheet` | XLSX export (reports, metadata export) |
| `phpoffice/phpword` | Word export (report builder, finding aids) |
| `dompdf/dompdf` | PDF export |
| `monolog/monolog` | Logging |
| `phpmailer/phpmailer` | Outbound mail (notifications, access requests) |
| `firebase/php-jwt` | JWT signing (API tokens, share links) |
| `web-auth/webauthn-lib` | WebAuthn / passkey authentication |
| `tecnickcom/tc-lib-barcode` | Barcode and QR generation for labels |

`composer install` runs automatically from `bin/release`, `bin/ahg-installer.sh`,
`bin/atom-setup-wizard.sh` and `bin/build-deb.sh`. On a manual install you must
run it yourself, as shown below.

> Add framework dependencies to **`atom-framework/composer.json`**, never to the
> AtoM root `composer.json`. The AtoM root is not a git repository in this
> deployment, so a dependency added there exists only on the machine where it was
> installed and is silently missing everywhere else.

### JavaScript and CSS libraries

All are **vendored and committed** to `atom-ahg-plugins` - roughly 48 minified
bundles under each plugin's `web/js/` or `js/`. There is no npm install step at
deploy time and nothing is fetched from a CDN at runtime, so the interface works
without outbound internet and without widening the Content-Security-Policy.

Among them: Bootstrap, OpenSeadragon and Mirador (IIIF viewers), Annotorious,
PDF.js, three.js with model-viewer (3D), Cytoscape and 3d-force-graph (RiC
Explorer), D3, Chart.js, Leaflet, Mermaid, FlexSearch, TipTap, html2canvas,
JsBarcode and Konva.

Node.js 18+ is needed only to **rebuild** the theme's webpack bundle. The built
output is committed, so a normal install does not require Node.

### Optional external tools

Only needed if you enable the feature that uses them. Each degrades gracefully
when absent - the feature is unavailable rather than broken.

| Tool | Package (Ubuntu) | Enables |
|---|---|---|
| ImageMagick + `php8.3-imagick` | `imagemagick php8.3-imagick` | Digital-object derivatives (**base AtoM requirement**, not optional in practice) |
| Tesseract, `pdftotext` | `tesseract-ocr poppler-utils` | OCR during ingest |
| ClamAV | `clamav-daemon` | Virus scanning during ingest |
| Siegfried (`sf`) | see PRONOM/Siegfried docs | Format identification, preservation |
| Aspell | `aspell aspell-en` | Spellcheck |
| Python 3 + spaCy | via `atom-ahg-python` | NER, summarisation |
| Argos Translate | `pip install argostranslate` | Offline machine translation |
| Cantaloupe | see Cantaloupe docs | IIIF image tiling (optional; the bundled viewers work without it) |

AI features route through the AHG AI gateway rather than calling model hosts
directly; see the gateway documentation for its own requirements.

## Install (manual / git)

From the AtoM root (e.g. `/usr/share/nginx/atom`):

```bash
cd /usr/share/nginx/atom
git clone https://github.com/ArchiveHeritageGroup/atom-framework.git
git clone https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git

cd atom-framework
composer install --no-dev
bash bin/install
```

Run `bin/install` as the user that owns the AtoM tree (e.g.
`sudo -u www-data bash bin/install`). It reads the database credentials from
`<atom-root>/config/config.php`.

### What `bin/install` does
1. Create framework DB tables (+ idempotent schema ALTERs)
2. Symlink the AHG plugins into AtoM's `plugins/`
3. Install the framework `ProjectConfiguration` (loads plugins from the `atom_plugin` table)
4. Copy dist assets (JS/CSS bundles + icons)
5. Enable the theme + core plugins in `setting_i18n`
6. Clear cache, load plugin data, enable required plugins, sync versions
7. Patch `QubitMetadataRoute` for GLAM-sector routing
8. Apply the bundled base-AtoM patches (`patches/`)

### ⚠️ Step 8 overwrites 38 base AtoM files - read before installing

`patches/` mirrors the AtoM root, and step 8 copies those files over your AtoM with an
unconditional `cp -f`. **Take a backup of the AtoM tree and database first.**

**What they carry.** Several are security fixes that upstream AtoM 2.10.1 does not have:

| File | Change |
|---|---|
| `lib/model/QubitUser.php` | Password salt was `md5(rand(100000, 999999) . $email)` - a six-digit seed space keyed on a known value. Replaced with `bin2hex(random_bytes(16))`, plus Argon2id-aware verification |
| `qbAclPlugin/lib/QubitAcl.class.php` | Duplicate-role guard (the "Role 99" Zend ACL crash) |
| `lib/model/QubitActor.php`, `lib/filter/QubitSettingsFilter.class.php` | `unserialize(..., ['allowed_classes' => false])` - object-injection hardening |
| `lib/QubitFindingAid.class.php` | `escapeshellarg()` on a `pdftotext` invocation |
| `lib/model/QubitInformationObject.php` | Added lookup method (~150 lines) |

Also `apps/qubit/config/security.yml` and four module `security.yml` files (a missing
`security.yml` **fails open**), 11 South African i18n message files, several
`user`/`informationobject`/`digitalobject` actions, and `arRestApiPlugin`'s
`physicalobjectsCreateAction`.

**On an AtoM version upgrade:**

1. **The patches are lost.** Upstream files replace them and the fixes above silently
   revert - unless upstream fixed the same issues independently, which nothing verifies.
2. **Re-running `bin/install` afterwards can revert upstream's own fixes**, because it
   copies the older patched files unconditionally. **Always diff `patches/` against the
   new upstream before re-installing.**
3. **No patched file carries a marker** - `grep` will not tell you whether a given file
   is patched or pristine.

Full file list and the upstream diffs:
[issue #274](https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/issues/274).

## Post-install

```bash
cd <atom-root>
sudo -u www-data php symfony cc                      # clear the Symfony cache
sudo -u www-data php symfony display:auto-detect     # assign GLAM display types to descriptions
sudo -u www-data php symfony propel:build-nested-set # rebuild the term tree (plugins add taxonomy terms)
sudo -u www-data php symfony propel:generate-slugs   # backfill slugs for plugin-added terms (search needs them)
sudo -u www-data php symfony search:populate         # (re)build the search index
sudo -u www-data php symfony ahg:refresh-facet-cache # build the GLAM browse facet cache
sudo systemctl restart php8.3-fpm nginx
```

> `display:auto-detect` and `ahg:refresh-facet-cache` are AHG post-install steps -
> without them the GLAM Browse interface and its facets render empty even though
> the catalogue is indexed. Re-run `ahg:refresh-facet-cache` after any bulk import.

## Enable optional plugins

Core plugins are enabled by `bin/install`. Add optional sector/feature plugins by
running these from the **`atom-framework`** directory (where `bin/atom` lives):

```bash
php bin/atom extension:discover                 # list available extensions
php bin/atom extension:install <pluginName>     # install AND enable a plugin
php bin/atom extension:disable <pluginName>     # disable it again
```

## Automated installer (optional)

`bin/ahg-installer.sh` is an interactive wrapper that runs the **same** `bin/install`:

```bash
curl -fsSL https://raw.githubusercontent.com/ArchiveHeritageGroup/atom-framework/main/bin/ahg-installer.sh -o ahg-installer.sh
chmod +x ahg-installer.sh && sudo ./ahg-installer.sh
```
- Full / Quick Install -> `bin/install --interactive` / `--auto` (identical to the manual step)
- Complete Installation (new server) -> also installs OS deps + a fresh base AtoM before `bin/install`

An Ansible playbook is also provided: `ansible/atom-ahg-install.yml`.

## Uninstall

```bash
bash bin/uninstall
```

## Further documentation

- **[README.md](README.md)** - overview, features, CLI reference
- **[atom-ahg-plugins README](https://github.com/ArchiveHeritageGroup/atom-ahg-plugins#readme)** - plugin catalogue, compatibility
- Manuals: https://github.com/ArchiveHeritageGroup/atom-extensions-catalog
