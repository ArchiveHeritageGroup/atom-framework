# Base-AtoM security fixes (ATOM-1..9) - 2026-07-12

**Released:** atom-framework v2.13.20. **Deliverable for Artefactual:** `stuff/AtoM_2.10.1_Security_Fixes_Completed_Artefactual_2026-07-12.docx` + `stuff/artefactual-fixes-2026-07-12/*.patch` (13 git-apply-able diffs; `stuff/` is not git-tracked).

## Context

The 2026-07-12 security scan found 9 issues in **base AtoM 2.10.1** code (normally locked). At the owner's direction these were fixed (not just flagged), applied live to PSIS + archaeology via the sanctioned `atom-framework/patches/` mechanism, documented, and packaged as completed patches for coordinated disclosure to Artefactual.

## Fixes (severity order)

| ID | Sev | File | Fix |
|----|-----|------|-----|
| ATOM-1 | HIGH | `lib/QubitXmlImport.class.php` | XXE: `substituteEntities=false`, `resolveExternals=false`, `loadXML(..., LIBXML_NONET)` |
| ATOM-2 | MED | `plugins/arRestApiPlugin/.../physicalobjectsCreateAction.class.php` | editor-credential gate on `post()` |
| ATOM-3 | MED | `apps/qubit/.../generateFindingAidAction.class.php` | `QubitAcl::check($resource,'update')` (was unauthenticated) |
| ATOM-4 | MED | `apps/qubit/.../deleteFindingAidAction.class.php` | object ACL vs `isAuthenticated()` |
| ATOM-5 | LOW | `apps/qubit/.../digitalobject/updateAction.class.php` | POST-only method guard (CSRF) |
| ATOM-6 | LOW | `apps/qubit/.../exportCsvAction.class.php` | force `publicationStatus=published` for non-editors |
| ATOM-7 | LOW | `QubitInformationObject`, `QubitActor`, `QubitCsvTransform`, `QubitSettingsFilter`, `settings/inventoryAction` | `unserialize(..., ['allowed_classes'=>false])` (5 sites) |
| ATOM-8 | LOW | `lib/model/QubitUser.php` | salt `bin2hex(random_bytes(16))` (was `md5(rand()...email)`) |
| ATOM-9 | LOW | `lib/QubitFindingAid.class.php` | `escapeshellarg()` on pdftotext path |

## Mechanism

- Each fixed file mirrored into `atom-framework/patches/<base-path>` (leading `plugins/` dropped, per qbAcl convention).
- `bin/install` Step 11 gained a loop (`SECFIX_FILES`) that re-applies all 13 on reinstall.
- Applied live to PSIS **and** archaeology base trees; PHP-lint clean; caches cleared; php-fpm restarted.

## Verification

All 13 files `php -l` clean. Smoke tests post-patch: PSIS homepage 302, `informationobject/browse` renders 200 (exercises model `__get` deserialize path + settings filter, which runs every request). Pristine originals snapshotted (base AtoM is not under git) so diffs are reproducible.

## Notes

- `stuff/` deliverable (DOCX + patches) is intentionally uncommitted (working-folder rule).
- The DOCX documents per finding: cause, consequence-if-unfixed, what-it-guards-against, and the fix, with file names/lines.

## Related: Artefactual official advisory - autocomplete access control (2026-07-14)

**This is Artefactual's OWN find, NOT one of our ATOM-1..9.** While preparing our disclosure, Johan surfaced Artefactual's published advisory for AtoM 2.5-2.10:

> An access-control issue on one or more unauthenticated endpoints could expose limited user-account metadata (usernames, email addresses, user role) and, under specific conditions, the title of **draft** archival descriptions. No passwords/tokens/files exposed. Fix = `security_yml.patch` (gist `b7875f864acb41bd39890f701e66c4a5`).

- **Root cause:** the `autocomplete` actions (`user`, `taxonomy`) fell through to `default: is_secure: false`, reachable unauthenticated. `/user/autocomplete` leaked usernames/emails/roles; actor/search autocomplete could surface draft titles.
- **The patch** (5 files): global `apps/qubit/config/security.yml` gains `autocomplete: credentials [[editor, administrator]], is_secure: true`; `actor` (new file) and `search` explicitly re-open theirs to public (`is_secure: false`); `taxonomy` flips `false→true`; `user` gains `autocomplete: credentials: administrator`.
- **Overlap with our scan:** distinct vulnerability, but same *class* as our plugin-side finding **M3/M10 (donor autocomplete IDOR)** - we caught the AHG-plugin autocompletes, Artefactual caught base `user`/`taxonomy`.
- **PSIS state (2026-07-14):** confirmed vulnerable - `user` module had no `autocomplete` rule → exposed. Raw patch downloaded to scratchpad; `patch -p1 --dry-run --forward` **clean on all 5 files** (actor security.yml absent → created; others match upstream context exactly).
- **Disposition:** stage via `atom-framework/patches/` (same mechanism as ATOM-1..9, `bin/install` Step 11 re-apply), apply live to PSIS + archaeology, verify `/user/autocomplete` returns non-200 unauthenticated. **Pending owner go-ahead to apply (base-AtoM files locked).**
- **Timing signal for our disclosure:** Artefactual is actively patching this exact subsystem now - reinforces getting our 9 findings to `security@artefactual.com` promptly, referencing this advisory as the current baseline.
