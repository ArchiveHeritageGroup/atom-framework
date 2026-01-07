-- Migration: Add default Level of Description Sector Mappings
-- Date: 2026-01-07
-- Description: Populates level_of_description_sector with defaults for all sectors

-- Archive sector (ISAD standard levels)
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Record group';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Fonds';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Subfonds';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Collection';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Series';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Subseries';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 70, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'File';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 80, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Item';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 90, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Part';

-- Museum sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = '3D Model';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artifact';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artwork';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Installation';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Object';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Specimen';

-- Gallery sector (add missing - Artwork already exists via other plugins)
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artwork';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Installation';

-- DAM sector (add missing - 3D Model)
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = '3D Model';

-- Verification query
SELECT los.sector, COUNT(*) as count 
FROM level_of_description_sector los 
GROUP BY los.sector 
ORDER BY los.sector;
