# security.yml guard test - audit, baseline, CI ratchet

**Date:** 2026-08-03
**Release:** atom-framework v2.13.58
**Follows:** #263 (security.yml sweep, plugins v3.88.30)

## Why

`apps/qubit/config/security.yml` sets `default: is_secure: false`. A module with
no `modules/<name>/config/security.yml` therefore inherits *public*. The absence
of a file is the vulnerability, which makes it invisible in review - there is no
line of code to look at and nothing that errors.

The #263 sweep fixed 11 modules by hand. Hand-sweeping does not survive the next
plugin someone adds, so this ships the mechanism instead of another sweep.

## What shipped

Three scripts, following the existing `bin/audit-propel` trio convention.

| Script | Role |
|--------|------|
| `bin/audit-security-yml` | Reports modules with actions but neither a `security.yml` nor a code-level guard |
| `bin/audit-security-yml-baseline` | Records the current set to `.security-yml-baseline.txt` |
| `bin/audit-security-yml-check` | Fails only when a module is **added** to that set |

Current baseline: **39** modules (4 base, 35 plugin). That is a backlog, not an
approval - each one is public today.

A module counts as guarded by either a `config/security.yml` or a code-level
check (`isAdministrator`, `forward('admin','secure')`, `AclService::`,
`hasGroup`, `checkAdminAccess`, `requireAuth`).

## Design notes worth keeping

**The check refuses to pass when it scanned nothing.** The framework repo's own
CI checkout contains neither `apps/qubit` nor `atom-ahg-plugins`, so a naive
wiring would have produced a permanent green tick from a run that examined zero
modules - the same silent-pass shape as the missing `security.yml` it exists to
catch. `audit-security-yml scopes` reports which trees were present, and the
check exits 1 when that list is empty.

**Baseline comparison is scoped to what was actually scanned.** A plugins-only
checkout compares 35-against-35 rather than reporting the 4 base modules as
newly fixed. `ATOM_ROOT` / `APPS_DIR` / `PLUGINS_DIR` are overridable so CI can
point at checkouts that are not siblings on disk.

**The failure message steers away from two traps this codebase already hit:** it
shows the double-bracket OR form, because `credentials: [editor]` as a
single-item list 403s administrators; and it offers `is_secure: false` as an
explicit declaration, so "this module is genuinely public" becomes a written
decision rather than an absent file.

## Verified

Five paths, all exercised against the live tree:

1. Clean run, both scopes - passes
2. New unguarded module added - exit 1, names the module, prints the fix
3. Baselined module gains a `security.yml` - reports the improvement, still passes
4. Plugins-only checkout - 35 vs 35, no false "fixed"
5. Neither tree present - exit 1, refuses to report OK

## Archeology mirror

Scripts mirrored to `/usr/share/nginx/archeology/atom-framework/bin/` (md5
verified) with its own baseline: **48** modules.

The 9-module gap against PSIS is exactly this session's #263 fixes -
heritageAccounting x4, ahgLibrary x3 (`copyCataloguing`, `kbartVendor`,
`z3950`), `scanManage`, `storageManage`. Archeology sits at plugins **v3.88.9**
against PSIS's **v3.88.36**, so it has not pulled them. Whether that is live
exposure depends on which plugins are enabled there; the credentials in
`config/propel.ini` are stale, so that was not confirmed.

## Not done

Wiring. The check cannot go into the framework's `ci-cd.yml` (nothing to scan
there). Two candidates, neither applied:

- `bin/release` in the plugins repo - both trees are side by side on this host,
  needs no secret, and blocks at the point where it can still be fixed.
  `bin/release` is on the never-modify-without-approval list.
- The plugins repo `security.yml` workflow - needs a second `actions/checkout`
  of `atom-framework`, which works unauthenticated only if that repo is public.
