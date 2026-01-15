# AtoM AHG Framework - Routing Documentation & Standards

**Priority:** 🔴 HIGH - URGENT  
**Issue Type:** Technical Debt / Documentation  
**Created:** 2026-01-14  
**Status:** Open - AUDIT COMPLETE

---

## 1. Problem Statement

Routing configuration is causing recurring issues across plugins. The audit on 2026-01-14 revealed:

- **15 plugins with DUAL routing** (both routing.yml AND Configuration.php)
- **17 plugins with module name warnings** in routing checks
- Inconsistent patterns causing unpredictable behavior
- No centralized documentation of routing standards

---

## 2. Conflicting Plugins (NEED FIX)

| Plugin | routing.yml | Config.php | Priority |
|--------|-------------|------------|----------|
| ahgThemeB5Plugin | 81 | 20 | 🔴 CRITICAL |
| ahgMuseumPlugin | 27 | 32 | 🔴 HIGH |
| ahgSpectrumPlugin | 26 | 4 | 🔴 HIGH |
| ahgDAMPlugin | 11 | 12 | 🔴 HIGH |
| ahgResearchPlugin | 13 | 32 | 🟠 MEDIUM |
| ahgDisplayPlugin | 13 | 12 | 🟠 MEDIUM |
| ahgExtendedRightsPlugin | 14 | 14 | 🟠 MEDIUM |
| ahgAccessRequestPlugin | 13 | 14 | 🟠 MEDIUM |
| ahgIiifCollectionPlugin | 12 | 12 | 🟠 MEDIUM |
| ahgConditionPlugin | 2 | 17 | 🟡 LOW |
| ahgDonorAgreementPlugin | 1 | 9 | 🟡 LOW |
| ahgFavoritesPlugin | 4 | 4 | 🟡 LOW |
| ahgGalleryPlugin | 5 | 4 | 🟡 LOW |
| ahgSecurityClearancePlugin | 1 | 7 | 🟡 LOW |
| ahgVendorPlugin | 16 | 12 | 🟡 LOW |

---

## 3. Fix Strategy

**RULE:** Each plugin uses EITHER routing.yml OR Configuration.php - NEVER BOTH

**Recommended:** Keep Configuration.php (prependRoute gives priority control), remove routing.yml duplicates

---

## 4. Audit Script

Location: `/usr/share/nginx/archive/atom-framework/bin/audit-routes.sh`
```bash
# Run audit
/usr/share/nginx/archive/atom-framework/bin/audit-routes.sh
```
