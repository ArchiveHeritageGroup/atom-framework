-- ============================================================
-- Migration: Add GLAM Display Templates
-- Adds Library, Museum, DAM, Gallery templates to taxonomy 70
-- ============================================================

-- Library template
INSERT INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM term WHERE taxonomy_id = 70 AND code = 'library'
);
SET @library_id = (SELECT id FROM term WHERE taxonomy_id = 70 AND code = 'library');
SET @library_id = COALESCE(@library_id, LAST_INSERT_ID());
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture) VALUES (@library_id, 70, 'library', 'en');
INSERT IGNORE INTO term_i18n (id, culture, name) VALUES (@library_id, 'en', 'Library (MARC-inspired)');
INSERT IGNORE INTO slug (object_id, slug) VALUES (@library_id, 'library-template');

-- Museum template
INSERT INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM term WHERE taxonomy_id = 70 AND code = 'museum'
);
SET @museum_id = (SELECT id FROM term WHERE taxonomy_id = 70 AND code = 'museum');
SET @museum_id = COALESCE(@museum_id, LAST_INSERT_ID());
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture) VALUES (@museum_id, 70, 'museum', 'en');
INSERT IGNORE INTO term_i18n (id, culture, name) VALUES (@museum_id, 'en', 'Museum (Spectrum)');
INSERT IGNORE INTO slug (object_id, slug) VALUES (@museum_id, 'museum-template');

-- DAM template
INSERT INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM term WHERE taxonomy_id = 70 AND code = 'dam'
);
SET @dam_id = (SELECT id FROM term WHERE taxonomy_id = 70 AND code = 'dam');
SET @dam_id = COALESCE(@dam_id, LAST_INSERT_ID());
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture) VALUES (@dam_id, 70, 'dam', 'en');
INSERT IGNORE INTO term_i18n (id, culture, name) VALUES (@dam_id, 'en', 'Digital Asset Management');
INSERT IGNORE INTO slug (object_id, slug) VALUES (@dam_id, 'dam-template');

-- Gallery template
INSERT INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM term WHERE taxonomy_id = 70 AND code = 'gallery'
);
SET @gallery_id = (SELECT id FROM term WHERE taxonomy_id = 70 AND code = 'gallery');
SET @gallery_id = COALESCE(@gallery_id, LAST_INSERT_ID());
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture) VALUES (@gallery_id, 70, 'gallery', 'en');
INSERT IGNORE INTO term_i18n (id, culture, name) VALUES (@gallery_id, 'en', 'Gallery (VRA Core)');
INSERT IGNORE INTO slug (object_id, slug) VALUES (@gallery_id, 'gallery-template');
