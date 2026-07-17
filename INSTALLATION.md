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

> On Ubuntu, if you install `php8.3-fpm` from the `ondrej/php` PPA, its unit ships
> `ProtectSystem=full` (mounts `/usr` read-only). If AtoM lives under `/usr/share/nginx`,
> add a drop-in granting write access:
> `/etc/systemd/system/php8.3-fpm.service.d/atom.conf` with
> `[Service]` and `ReadWritePaths=/usr/share/nginx/atom`, then `systemctl daemon-reload`
> and restart php8.3-fpm.

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

## Post-install

```bash
cd <atom-root>
sudo -u www-data php symfony cc                      # clear the Symfony cache
sudo -u www-data php symfony display:auto-detect     # assign GLAM display types to descriptions
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
