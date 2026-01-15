# Issue #67: GLAM/DAM Sector-Specific Import

**GitHub:** https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/issues/67  
**Priority:** HIGH  
**Status:** Open

## Problem

ahgDataMigrationPlugin exports ALL data to ISAD-G (Archives) CSV format only.

- Museum (Spectrum) → ISAD ❌
- Library (MARC) → ISAD ❌
- Gallery (CCO) → ISAD ❌
- DAM (Dublin Core) → ISAD ❌

## Required Development

1. Sector-specific CSV exporters
2. Sector-specific import jobs
3. Full round-trip testing

## Templates

Location: `/usr/share/nginx/archive/uploads/import_templates/`

- archives_isadg.csv
- museum_spectrum.csv
- library_marc.csv
- gallery_cco.csv
- dam_iptc.csv
