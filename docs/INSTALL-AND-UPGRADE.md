# Installing and upgrading the AHG stack on AtoM

Written 1 September 2026, from an evening spent reconstructing this procedure from
incident write-ups because it had never been set down. Everything here was done,
not recalled.

## Read this first: base AtoM is never modified

`bin/install` can overwrite base AtoM files. **It must not.** Base patches are
opt-in and stay off:

```bash
./bin/install                      # correct
./bin/install --with-base-patches  # NEVER
```

Three of the installer's steps are gated behind that flag - 3 (ProjectConfiguration),
7 (QubitMetadataRoute) and 11 (AtoM core patches). Watch for these lines and stop
if any of them does something:

```
Base patches: disabled (base AtoM will not be modified)
Step 3:  Left untouched
Step 7:  QubitMetadataRoute patch skipped
Step 11: Skipped. Base AtoM is left exactly as installed.
```

Why it matters: PSIS reached **29 changed base files against the four ever
authorised** through this flag - seven months, nine sittings, each one a security
fix that looked reasonable alone. Nobody decided to fork the customer's AtoM; the
flag just made it the easiest path each time.

⚠️ **KM currently gives the OPPOSITE advice.** Asked directly, it recommends
`--with-base-patches` on stock instances and says to avoid it on PSIS. Both halves
are inverted. It is RAG over session logs, not a procedure - trust it for history,
never for method.

## The two instance shapes, and why it changes everything

```bash
grep -c 'loadPluginsFromDatabase' <atom-root>/config/ProjectConfiguration.class.php
```

**`0` = STOCK.** Plugins load from the **serialized `plugins` row in
`setting_i18n` id=1**. The `atom_plugin` table is **INERT** - the plugin admin
screen shows plugins as enabled that do not load. This is the intended shape:
Step 3 deliberately no longer installs the AHG ProjectConfiguration, because
`ahgCorePlugin` bootstraps the framework itself.

**`1+` = AHG.** `atom_plugin` is the source of truth.

⚠️ **On a stock instance, never answer "what does this run?" from `atom_plugin`
or the admin screen.** Read the serialized list:

```bash
mysql -u <u> -p <db> -N --raw -e \
  "SELECT si.value FROM setting s JOIN setting_i18n si ON si.id=s.id WHERE s.name='plugins' LIMIT 1;" \
  | php -r '$l=unserialize(trim(file_get_contents("php://stdin"))); echo count($l)."\n"; print_r($l);'
```

Measured 1 Sep 2026: a box whose `atom_plugin` claimed 36 enabled was loading 34,
with IIIF, Mirador, Seadragon and UiOverrides marked enabled and **not loading at
all**. Nobody had connected the missing image viewer to a cause.

## Fresh install

1. Install base AtoM per Artefactual's own documentation. Do not deviate.
2. Clone `atom-framework` and `atom-ahg-plugins` into the AtoM root.
3. `composer install --no-dev` in `atom-framework`.
4. `./bin/install` (no flags).
5. `php symfony cc`, restart php-fpm.

`php8.3-gd` is required and is **missing from AtoM's own package list**.

## Upgrading an existing instance

### What a git pull does and does not do

**A pull moves files. Nothing else.** It does not create symlinks, run
`install.sql`, add to the plugin list, or write `atom_plugin` rows.

- Changing code in a plugin the instance **already has**: the pull IS the deploy.
- Adding a plugin the instance **does not have**: the pull only delivers code.
  Installation is separate.

### The procedure

```bash
# 1. BACK UP. --no-tablespaces or it fails on PROCESS privilege.
mysqldump -u <u> -p --no-tablespaces --single-transaction --routines --triggers <db> > dump.sql
tail -1 dump.sql        # MUST read "-- Dump completed"

# 2. Record the tag BEFORE. Run git AS THE TREE OWNER.
sudo -u www-data git -C atom-framework describe --tags --abbrev=0
sudo -u www-data git -C atom-ahg-plugins describe --tags --abbrev=0

# 3. Pull both
for r in atom-framework atom-ahg-plugins; do
  sudo -u www-data git -C $r status --short          # must be empty
  sudo -u www-data git -C $r fetch origin --tags
  sudo -u www-data git -C $r reset --hard origin/main
done

# 4. Record the tag AFTER. It MUST have moved.
# 5. Runtime plugin, if present - see below
# 6. ./bin/install   (no flags)
# 7. php symfony cc ; systemctl reload php8.3-fpm
```

⚠️ **Print the tag before AND after, every time.** A fetch that fails still lets
`reset --hard` exit 0 with the tag unmoved. On 1 Sep a framework deploy failed
five times on `dubious ownership` and printed nothing; the operator believed it
had worked and the bug it was meant to fix stayed live.

⚠️ **`version.json` is gitignored.** It still reads the OLD version after a
successful pull. The tag is the truth; any deploy check reading `version.json`
will report failure on a good upgrade.

### ⚠️ ahgRuntimePlugin shadows the framework

```bash
ls -d <atom-root>/plugins/ahgRuntimePlugin
```

If present, every PSR-4 `AtomFramework\*` / `AtomExtensions\*` class loads from
that **generated copy**, not from `atom-framework/src`. **A framework release does
nothing until it is rebuilt:**

```bash
cd atom-framework && sudo -u www-data bin/build-runtime-plugin
```

Run as `www-data` - root leaves root-owned files that `ProtectSystem=full` cannot
write. Prefer **link mode** (now the default): the plugin becomes a symlink and
the trap cannot recur on that box.

What makes it vicious: classes loaded by explicit `require_once` come from the
fresh framework, so part of a release works immediately while the rest is stale.
**A framework fix appearing to work proves nothing about whether the framework
deployed.**

## Adding a plugin to an existing instance

```bash
php bin/atom extension:install <name>   # if never installed - symlink + schema
php bin/atom extension:enable  <name>   # symlink + atom_plugin + serialized list
```

`install` first, then `enable`; `enable` refuses a plugin that was never installed.
The installer prompts `[Y/n]` for table creation - **answer it before typing the
next command**, or the next command becomes the answer and the plugin installs
with no tables.

Then **verify against the serialized list, not the tool's output**. The list update
has three silent exits (no matching culture row, empty value, and a bare
`catch (\Exception $e) {}`), any of which leaves `atom_plugin` updated, the load
list untouched, and a success message on screen.

## ⚠️ Reconcile the two plugin lists BEFORE cutover

An AHG instance has **two** plugin lists and they can disagree while both look
right. Measured on a client production instance 1 Sep 2026: `atom_plugin` said 35
enabled, the serialized list said 35 - and **16 entries differed in each
direction**.

Only one of them governs, and which one depends on `ProjectConfiguration`. A fresh
2.10.2 install is STOCK, so an upgrade that follows the documented
install-fresh-and-restore path **silently swaps which list governs**. The stale
list takes over.

What that looked like on the rehearsal: the instance lost `ahgUiOverridesPlugin`,
which owns `informationobjectHelper.php`, so every library, museum, gallery and
DAM record page returned 500 - while the homepage, login and browse all answered
200. A cutover smoke-tested on the front page would have looked completely clean.

**Before cutover, diff them and decide which 35 is correct:**

```bash
mysql -u <u> -p <db> -N -e "SELECT name FROM atom_plugin WHERE is_enabled=1 ORDER BY name;" > /tmp/a.txt
mysql -u <u> -p <db> -N --raw -e \
  "SELECT si.value FROM setting s JOIN setting_i18n si ON si.id=s.id WHERE s.name='plugins' LIMIT 1;" \
  | php -r '$l=unserialize(trim(file_get_contents("php://stdin"))); sort($l); echo implode("\n",$l);' > /tmp/b.txt
diff /tmp/a.txt /tmp/b.txt
```

Then write the agreed set to **both**, and verify by loading a record of **each
display standard in use** - not the homepage.

```sql
SELECT ti.name AS standard, COUNT(*) FROM information_object io
JOIN term_i18n ti ON ti.id = io.display_standard_id AND ti.culture='en'
GROUP BY ti.name;
```

Every standard in that result needs a record opened after cutover. A plugin whose
helper is missing fails only on the pages that use it.

## Schema

Plugin schema is applied with `CREATE TABLE IF NOT EXISTS`, which **never alters an
existing table**. A table created by an older version keeps its old columns
forever, and the newer `install.sql` then fails on an INSERT naming a column that
does not exist.

**Diff columns, not table names.** Measured on one instance: 55 missing columns
across 16 tables in 11 plugins - every one invisible to a table count.

## AHG CLI tasks do not exist on a stock instance

AtoM discovers tasks at **project** level. On stock `ProjectConfiguration`,
`php symfony help:import` and every other AHG namespace **does not exist**, while
the plugins run fine in the web app. This is permanent by design, not a fault.

Drive the task with a runner:

```php
<?php
define("SF_ROOT_DIR", "<atom-root>");
require_once SF_ROOT_DIR."/config/ProjectConfiguration.class.php";
$configuration = ProjectConfiguration::getApplicationConfiguration("qubit", "cli", false);
$dispatcher = $configuration->getEventDispatcher();
$dispatcher->connect("command.log", function ($e) {
    foreach ($e->getParameters() as $m) { echo is_array($m) ? implode(" ", $m) : $m, "\n"; }
});
require_once $argv[1];
$task = new $argv[2]($dispatcher, new sfFormatter());
$opts = [];
foreach (array_slice($argv, 3) as $a) {
    if (strpos($a, "--") !== 0) continue;
    $a = substr($a, 2);
    if (false !== $p = strpos($a, "=")) $opts[substr($a,0,$p)] = substr($a,$p+1);
    else $opts[$a] = true;
}
exit((int) $task->run([], $opts));
```

Three traps, all of which look like success:

- The third argument to `getApplicationConfiguration` must stay **`false`**. `true`
  rebuilds the CLI config cache and corrupts the one the web app uses.
- **The `command.log` listener is load-bearing.** `sfTask::log()` publishes to that
  event and `sfCommandApplication` normally supplies the listener. Without it the
  task runs to completion and prints **nothing** - indistinguishable from a task
  that found no data.
- `--dry-run` is not proof. `help:import --dry-run` reported 352/0 errors because
  it `continue`s before reading file contents; the real run found two empty files.

## After any bulk import, reconcile the arithmetic

`help:import` reported *350 imported, 0 errors* and produced **349 rows**. The
single-row gap was two real faults: two documents whose filenames slugify
identically (`slug` is UNIQUE, so one silently overwrote the other, invisible
afterwards) and two zero-byte files. **The summary line was wrong and the row count
was right.** Do not stop until it closes exactly.

## Known exposure left open by not patching base

`patches/qbAclPlugin/` closes an upstream bypass (artefactual/atom#1724) where
**every user is granted `readMaster` on text objects**, returning before both the
ACL check and the PREMIS rights check - making PDF masters of draft, embargoed and
access-restricted records anonymously downloadable, and preventing PREMIS Rights
from restricting a PDF at all.

Declining base patches leaves this open. It is an upstream AtoM bug present on any
stock install. Size it per instance:

```sql
SELECT ti.name AS media_type, s.status_id, COUNT(*)
FROM digital_object d
JOIN information_object io ON io.id = d.object_id
LEFT JOIN status s ON s.object_id = io.id AND s.type_id = 158
LEFT JOIN term_i18n ti ON ti.id = d.media_type_id AND ti.culture='en'
GROUP BY ti.name, s.status_id;
```

`status_id 159` = draft. Text objects on drafts are exposed. Also check whether
nginx serves `/uploads/` directly, which bypasses the application entirely.

## Verify, always by measurement

```bash
curl -s -o /dev/null -w '%{http_code}\n' <base>/index.php/            # 302 is normal
curl -s -o /dev/null -w '%{http_code}\n' <base>/index.php/<a-record>  # 200
```

- Probe with `curl -sL`. Slugs 301 to the non-`/index.php` form and measuring the
  redirect reports zero for everything, which reads as a total outage.
- Check a record **renders**, not merely that it returns 200. AtoM serves the login
  page as HTTP 200 at the same URL.
- Check `dist/css` and `dist/js` timestamps moved. New plugins ship assets; without
  a rebuild they load server-side and look broken in the browser.
- Read `ahg_error_log` before concluding anything about a runtime fault.
