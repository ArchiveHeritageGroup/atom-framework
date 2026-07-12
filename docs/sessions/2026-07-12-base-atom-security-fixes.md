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
