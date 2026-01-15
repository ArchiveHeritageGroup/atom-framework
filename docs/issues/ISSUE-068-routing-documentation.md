# Issue #68: Routing Documentation & Standardization

**GitHub:** https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/issues/68  
**Priority:** HIGH  
**Status:** Open

## Problem

15 plugins have DUAL routing (both routing.yml AND Configuration.php), causing conflicts.

## Conflicting Plugins

| Plugin | routing.yml | Config.php | Priority |
|--------|-------------|------------|----------|
| ahgThemeB5Plugin | 81 | 20 | CRITICAL |
| ahgMuseumPlugin | 27 | 32 | HIGH |
| ahgSpectrumPlugin | 26 | 4 | HIGH |
| ahgDAMPlugin | 11 | 12 | HIGH |
| ahgResearchPlugin | 13 | 32 | MEDIUM |
| ahgDisplayPlugin | 13 | 12 | MEDIUM |
| ahgExtendedRightsPlugin | 14 | 14 | MEDIUM |
| ahgAccessRequestPlugin | 13 | 14 | MEDIUM |
| ahgIiifCollectionPlugin | 12 | 12 | MEDIUM |
| ahgConditionPlugin | 2 | 17 | LOW |
| ahgDonorAgreementPlugin | 1 | 9 | LOW |
| ahgFavoritesPlugin | 4 | 4 | LOW |
| ahgGalleryPlugin | 5 | 4 | LOW |
| ahgSecurityClearancePlugin | 1 | 7 | LOW |
| ahgVendorPlugin | 16 | 12 | LOW |

## Fix Strategy

**RULE:** Each plugin uses EITHER routing.yml OR Configuration.php - NEVER BOTH

**Recommended:** Keep Configuration.php (prependRoute), remove routing.yml duplicates

## Audit Script
```bash
/usr/share/nginx/archive/atom-framework/bin/audit-routes.sh
```
