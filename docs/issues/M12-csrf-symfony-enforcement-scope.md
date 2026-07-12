# M12 - Enforce framework CSRF on the Symfony request path (SCOPE)

**Status:** Scoped, not started. Deferred out of the 2026-07-12 security-scan remediation because it is a behavioural change with a large blast radius that must be rolled out in stages, not shipped as a one-line patch.

**Severity:** MEDIUM. Same-site CSRF on mutating AhgController actions served through Symfony (`index.php`). Partially mitigated in practice by same-site session cookies and referer checks on some actions, but there is no framework-wide guarantee.

## Root cause

`atom-framework/src/Http/Controllers/AhgController.php` protects mutating requests via `enforceCsrf()`, which calls `CsrfService::enforce()`.

- **Standalone / Laravel path** (`heratio.php` → `dispatch()`, line ~409): `enforceCsrf()` **is** called. CSRF is enforced. (This path is the parked WP7 standalone mode; not the PSIS production path.)
- **Symfony path** (`index.php` → `preExecute()`, line ~36): `preExecute()` calls `boot()` but **never** calls `enforceCsrf()`. So on the production PSIS/ANC Symfony stack, CSRF is effectively **not enforced** on any AhgController subclass, despite `$csrfProtection = true` being the default.

~520 plugin classes extend `AhgController`; a naive global enable would 403 every POST/PUT/DELETE form/AJAX call that does not yet send a token.

## Why it is safe to stage (existing infrastructure)

`CsrfService` already supports a three-mode rollout via the `csrf_enforcement` AHG setting (default `enforce`):

| Mode | Behaviour |
|------|-----------|
| `off` | no checks |
| `log` | validate; on failure `error_log()` a `CSRF violation: METHOD URI (token missing/invalid)` line but **allow** the request |
| `enforce` | validate; on failure return 403 |

`CsrfService::isExempt()` already exempts GET/HEAD/OPTIONS, `Authorization: Bearer`, and `X-API-Key` requests, so pure API clients are unaffected. Helpers exist to emit tokens: `CsrfService::renderHiddenField()` (form field `_csrf_token`) and `CsrfService::getMetaTag()` (for the `X-CSRF-TOKEN` header used by AJAX).

## Rollout plan

**Phase 0 - wire it, defaulting to log (single framework change + one setting).**
1. In `AhgController::preExecute()` (Symfony branch), add `$this->enforceCsrf();` after `$this->boot();` - mirroring `dispatch()`.
2. **Before** deploying, seed `ahg_settings` row `csrf_enforcement = log` (DB write - needs approval) so the new call logs but never blocks. Do NOT rely on the `enforce` default here.
   - Note: this also drops the parked standalone path from `enforce` to `log`; acceptable while WP7 is parked, and it is restored globally in Phase 3.

**Phase 1 - observe (1-2 weeks of real traffic).**
- Harvest `CSRF violation:` lines from php-fpm error logs (and/or `ahg_error_log`). Each distinct `METHOD URI` is a form/endpoint missing a token. This produces the exact, evidence-based work-list - no guessing across 520 classes.

**Phase 2 - remediate the offending forms/AJAX (bulk of the work).**
- Server-rendered forms: add `{!! CsrfService::renderHiddenField() !!}` (Blade) / `<?php echo CsrfService::renderHiddenField(); ?>` (PHP template) inside each `<form method="post">`.
- AJAX/fetch: add `CsrfService::getMetaTag()` to the layout `<head>` and send `X-CSRF-TOKEN` from the meta tag on mutating calls. A shared JS helper in the theme keeps this DRY.
- Re-run in `log` mode until the violation log for legitimate traffic is quiet.

**Phase 3 - flip to enforce.**
- Set `csrf_enforcement = enforce`. Keep `log` fallback available for fast rollback if a missed form surfaces.

## Impact / files

- **Framework (1 line):** `AhgController::preExecute()` gains `$this->enforceCsrf();`.
- **DB (1 row):** `ahg_settings.csrf_enforcement` staged `log` → later `enforce`.
- **Templates (many, Phase 2):** hidden field / meta tag across mutating forms surfaced by Phase 1 logs. Locked plugins that appear in the logs need explicit per-plugin unlock before their templates are touched.
- **Theme (1):** layout meta tag + a small CSRF-header JS helper.

## Explicitly NOT in scope here

- No behavioural change ships without the `log` observation window first.
- No mass template edit up front - Phase 1 logs drive Phase 2 so we only touch forms that actually need it.

## Acceptance criteria

- Symfony-path mutating AhgController requests without a valid token are rejected (403) once `csrf_enforcement = enforce`.
- API (Bearer / X-API-Key) and GET traffic unaffected.
- No legitimate first-party form breaks (validated by a quiet Phase-1 log before the flip).
