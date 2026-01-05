-- Migration: Add default watermark types
-- Date: 2026-01-05
-- Description: Populate watermark_type table with default values

INSERT INTO `watermark_type` (`code`, `name`, `image_file`, `position`, `opacity`, `active`, `sort_order`) VALUES
('DRAFT', 'Draft', 'draft.png', 'center', 0.40, 1, 1),
('COPYRIGHT', 'Copyright', 'copyright.png', 'bottom right', 0.30, 1, 2),
('CONFIDENTIAL', 'Confidential', 'confidential.png', 'repeat', 0.40, 1, 3),
('SECRET', 'Secret', 'secret_copyright.png', 'repeat', 0.40, 1, 4),
('TOP_SECRET', 'Top Secret', 'top_secret_copyright.png', 'repeat', 0.50, 1, 5),
('NONE', 'No Watermark', '', 'none', 0.00, 1, 6),
('SAMPLE', 'Sample', 'sample.png', 'center', 0.50, 1, 7),
('PREVIEW', 'Preview Only', 'preview.png', 'center', 0.40, 1, 8),
('RESTRICTED', 'Restricted', 'restricted.png', 'repeat', 0.35, 1, 9)
ON DUPLICATE KEY UPDATE name=VALUES(name), image_file=VALUES(image_file), position=VALUES(position), opacity=VALUES(opacity);
