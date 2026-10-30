# Site requirements for AtoM sites running the AHG extensions

Every AtoM site that runs atom-framework and atom-ahg-plugins must meet the requirements below. They cover setup that lives on the server (cron files, nginx, php-fpm, settings rows) and so does not arrive with `git pull`. Each one was first met on one server and missed on another.

`bin/site-audit` checks every requirement on a site and prints PASS, FAIL, WARN or SKIP for each, with the fix. It changes nothing. Run it after every install or update, and whenever something looks wrong:

```bash
cd <atom-root>
bash atom-framework/bin/site-audit
```

**When a new per-site requirement is found, add it here and add its check to `bin/site-audit` in the same release.** A requirement that is only remembered gets missed on the next site.

## Requirements

| ID | Requirement | Why | Fix |
| --- | --- | --- | --- |
| R1 | atom-ahg-plugins and atom-framework are at the latest release, with no local changes | Fixes only reach a site through `git pull`; local edits block the fast-forward | `git pull --ff-only` as the checkout owner (`sudo -u <owner>`). Never add a `safe.directory` exception |
| R2 | php-fpm was restarted after the last update | Some pools never re-read changed PHP files (`opcache.validate_timestamps=0`) | `sudo systemctl restart php8.3-fpm` (restart, not reload: reload can leave the socket owned by root) |
| R3 | The socket nginx passes PHP to exists and belongs to www-data | A stale pool file or a reload leaves nginx pointing at nothing (502) | Check the pool `listen =` line against nginx `fastcgi_pass` / `upstream` |
| R4 | nginx returns 404 for private folders under `uploads/` | Ingest packages, exports, backups and agreements were downloadable (#319, 27 Oct 2026) | `stuff/ahg-private-uploads-site.sh` includes `snippets/ahg-deny-private-uploads.conf` after `root` |
| R5 | The 27 Oct 2026 migrations are applied where their plugin is installed | Security clearance pages fail without the vetting columns | `stuff/ahg-private-uploads-site.sh` applies them |
| R6 | A cron refreshes the GLAM browse filters (`display_facet_cache`) | The filters read a cache that nothing fills on its own; without it "Narrow your results by:" is empty (RARI, 29 Oct 2026) | `stuff/ahg-facet-cron.sh` writes `/etc/cron.d/<instance>-facet-cache` |
| R7 | On stock AtoM, every active user's password is in the salted form base verifies | Argon2id hashes with an empty salt cannot sign in on stock AtoM (RARI, 28 Oct 2026) | The user resets by email, or `sudo -u www-data php symfony tools:reset-password <user>` |
| R8 | New researcher notifications go to the client's staff | Notices went to AHG staff or to nobody (RARI, 28 Oct 2026) | Admin > Settings > Email: New Researcher, several addresses separated by commas |
| R9 | Access request approvers have email notifications on | Approvers are not told about requests | Security > Approvers: tick Email Notify |
| R10 | `object_term_relation` has the index `idx_otr_term_object (term_id, object_id)` | Without it, filtered browse counts on a large catalogue run for minutes (RARI, 29 Oct 2026); stock AtoM has only single-column indexes | `SET SESSION lock_wait_timeout = 10; ALTER TABLE object_term_relation ADD INDEX idx_otr_term_object (term_id, object_id), ALGORITHM=INPLACE, LOCK=NONE;` (approved by Dr Johan Pieterse, 29 Oct 2026; online, the site stays up). First check `SHOW FULL PROCESSLIST` and `KILL` any long-running SELECT on the table: the ALTER waits for its metadata lock and every new query queues behind it (RARI, 29 Oct 2026). With the 10-second lock wait it gives up instead of queueing the site; rerun once the long query is gone |
| R11 | The framework's `vendor/` matches `composer.lock` | `vendor/` is not in git, so `git pull` brings a new `composer.lock` but never the packages; security updates (#216) would sit unused | `sudo -u <vendor owner> composer install --no-dev -d <atom-root>/atom-framework`, then restart php-fpm. `stuff/ahg-site-update.sh` does this whenever `composer.lock` changed |

## Notes on running commands on a client site

- **Stock AtoM does not load plugin symfony tasks.** `php symfony display:auto-detect` answers "There are no tasks defined in the display namespace". Use the framework CLI, `php atom-framework/bin/atom <command>`.
- **Never boot the Symfony app from the command line as anyone but www-data.** It can write the real cache and leave the site serving 500s. For the facet cache, use the standalone `ahgDisplayPlugin/bin/refresh-facet-cache.php`, which loads only the database layer.
- **Checkouts belong to their owner.** Run git as that user, not as yourself and not as root.
