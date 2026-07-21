# GLAM/DAM Sector-Specific Import - Issue Tracker

**Priority:** 🟠 HIGH  
**Issue Type:** Feature Gap / Incomplete Development  
**Created:** 2026-01-15  
**Status:** Open - NEEDS DEVELOPMENT

---

## 1. Problem Statement

The `ahgDataMigrationPlugin` currently exports ALL data to ISAD-G (Archives) CSV format, regardless of the target sector. This means:

- Museum data (Collections Procedures) → outputs ISAD fields ❌
- Library data (MARC) → outputs ISAD fields ❌
- Gallery data (CCO) → outputs ISAD fields ❌
- DAM data (Dublin Core) → outputs ISAD fields ❌

**Only Archives data exports correctly.**

---

## 2. Current State

| Component | Status | Notes |
|-----------|--------|-------|
| Upload CSV/XML | ✅ Done | Auto-detects source system |
| Field Mapping UI | ✅ Done | Visual mapper |
| Sector Definitions | ✅ Done | Archives, Museum, Library, Gallery, DAM defined |
| Save/Load Mappings | ✅ Done | Database stored |
| Preview Data | ✅ Done | Shows transformed records |
| **Export to Archives CSV** | ✅ Done | ISAD-G format |
| **Export to Museum CSV** | ❌ NOT DONE | Collections Procedures fields |
| **Export to Library CSV** | ❌ NOT DONE | MARC/RDA fields |
| **Export to Gallery CSV** | ❌ NOT DONE | CCO/VRA fields |
| **Export to DAM CSV** | ❌ NOT DONE | Dublin Core/IPTC fields |
| **Import to AtoM (non-Archives)** | ❓ UNKNOWN | Does AtoM support this? |

---

## 3. Required Development

### Phase 1: Investigation
- [ ] Check AtoM core CSV import capabilities
- [ ] Check each sector plugin for import functionality
- [ ] Document what fields each sector needs

### Phase 2: Sector-Specific CSV Export
- [ ] Create Museum CSV exporter (Collections Procedures fields)
- [ ] Create Library CSV exporter (MARC/RDA fields)
- [ ] Create Gallery CSV exporter (CCO/VRA fields)
- [ ] Create DAM CSV exporter (Dublin Core/IPTC fields)

### Phase 3: Sector-Specific Import Jobs
- [ ] Create/extend Museum CSV import job
- [ ] Create/extend Library CSV import job
- [ ] Create/extend Gallery CSV import job
- [ ] Create/extend DAM CSV import job

---

## 4. Templates Created

CSV templates with correct field names:
- `archives_isadg.csv` - 39 fields
- `museum_spectrum.csv` - 47 fields  
- `library_marc.csv` - 34 fields
- `gallery_cco.csv` - 36 fields
- `dam_iptc.csv` - 52 fields

Location: `/usr/share/nginx/archive/uploads/import_templates/`
