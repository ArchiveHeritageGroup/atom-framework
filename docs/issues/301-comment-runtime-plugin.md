Hit this twice on 2026-08-25, in both directions, with measurements.

## It is partial, which is what makes it dangerous

Framework **v2.18.14** was deployed to archaeology: correct tag, correct `version.json`, `git log` clean. `LanguageService::getAll()` still returned the pre-fix result. Meanwhile the routing fix released in the *same* deploy worked immediately.

The split is by how a class is loaded:

- **PSR-4 `AtomFramework\*` classes** resolve to `atom-ahg-plugins/ahgRuntimePlugin/src/` — the generated copy. Stale until rebuilt.
- **Classes pulled in by explicit `require_once`** from `$rootDir/atom-framework` — the routing classes loaded by `PluginLoader::loadRoutingClasses()` and `ahgCorePluginConfiguration::bootstrapFramework()` — come from the fresh framework.

So on one deploy, `AhgSafeMetadataRoute` was live and `LanguageService` was not. **A framework fix appearing to work proves nothing about whether the framework deployed.**

## The check that settles it

Version indicators all agree with each other and are all wrong. The only reliable test is asking the running process where the class came from:

```php
$r = new ReflectionClass('\AtomFramework\Services\LanguageService');
echo $r->getFileName();
// .../ahgRuntimePlugin/src/Services/LanguageService.php   <- stale
// .../atom-framework/src/Services/LanguageService.php     <- fresh
```

## Rebuild, and run it as www-data

```bash
cd <root>/atom-framework && sudo -u www-data bin/build-runtime-plugin
systemctl reload php8.3-fpm && sudo -u www-data php symfony cc
```

It regenerates `../atom-ahg-plugins/ahgRuntimePlugin` (491 php files, 58 MB). Safe by design — it `rm -rf`s the target but refuses unless `.generated-by-build-runtime-plugin` is present, so it cannot eat a hand-made directory. Run it as `www-data`: root leaves root-owned files that `ProtectSystem=full` then cannot write.

The file count is itself a useful signal — 490 before adding `ErrorLogWriter`, 491 after, so a rebuild that does not move the count did not pick up a new class.

## Two more reasons it stays invisible

- **`ahgRuntimePlugin` is not in git.** `git ls-files ahgRuntimePlugin` returns 0 despite the `!/ahg*/` rule in `.gitignore`, so no `git pull` or `reset --hard` can ever refresh it.
- **An instance without the `plugins/ahgRuntimePlugin` symlink is unaffected**, because it loads `atom-framework/src` directly. So the fault only appears on installs shaped like a customer deployment — the ones where it matters most and where it is least likely to be noticed.

## Mechanised

`atom-framework/bin/deploy-check` step 2 compares the newest `.php` mtime in `atom-framework/src` against the generated copy and fails the deploy when the framework is newer. It reads clean on both instances now; against this morning's timestamps (generated 08-23 21:13 vs framework 08-24 11:41) it fails, which is the case it exists for.
