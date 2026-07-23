# extension:install idempotency + digital-object pipeline (imagick)

**Date:** 2026-07-17
**Repo:** atom-framework v2.13.34
**Context:** clean-room hardening on VM atom210 (all 111 plugins).

## #3 - `extension:install` was not idempotent

`ExtensionManager::install()` throws `"Extension 'X' is already installed."`
when the plugin already has an `atom_plugin` row (unless pending removal). The
CLI command called `install()` then `enable()` in sequence, so on an
already-installed-but-**disabled** plugin the throw aborted before `enable()` -
the plugin stayed disabled. In a bulk `extension:install` sweep this left a
couple of plugins installed-but-not-enabled (ahgCustomFields, ahgResearcher),
and the "already installed" text masked it.

**Fix (`src/Console/ExtensionCommand.php`):** the command now checks
`isInstalled()`/`isEnabled()` (both public on the manager) and:
- skips `install()` if already installed (avoids the throw),
- calls `enable()` only if not already enabled.

So `extension:install <name>` always ends **installed AND enabled**, matching
its documented "auto-enables" behaviour, and is safe to re-run.

**Verified (VM):** disabled ahgLibraryPlugin → `extension:install` re-enabled it
(is_enabled 0→1); re-run on the enabled plugin = clean no-op.

## #4 - digital-object derivative pipeline

The clean base install lacked `php8.3-imagick`, so image uploads produced no
derivatives. Installed `php8.3-imagick` (JPEG/PNG/TIFF/PDF/GIF), restarted
php-fpm + worker.

**Verified (VM, no residue):** imported an IO with a 1200×900 JPEG →
AtoM/Imagick generated master (1200×900), reference (480×360), thumbnail
(270×203), all valid JPEGs on disk under `uploads/r/...`. Test IO + digital
objects + files deleted afterward (catalog back to empty).

**Doc:** added `php8.3-imagick` to INSTALLATION.md prerequisites.
