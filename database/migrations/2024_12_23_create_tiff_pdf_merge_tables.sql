-- ============================================================================
-- TIFF to PDF Merge System - Database Schema
-- ============================================================================

-- Main merge job table
CREATE TABLE IF NOT EXISTS tiff_pdf_merge_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NULL,
    user_id INT NOT NULL,
    job_name VARCHAR(255) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    total_files INT DEFAULT 0,
    processed_files INT DEFAULT 0,
    output_filename VARCHAR(255) NULL,
    output_path VARCHAR(1024) NULL,
    output_digital_object_id INT NULL,
    pdf_standard ENUM('pdf', 'pdfa-1b', 'pdfa-2b', 'pdfa-3b') DEFAULT 'pdfa-2b',
    compression_quality INT DEFAULT 85,
    page_size ENUM('auto', 'a4', 'letter', 'legal', 'a3') DEFAULT 'auto',
    orientation ENUM('auto', 'portrait', 'landscape') DEFAULT 'auto',
    dpi INT DEFAULT 300,
    preserve_originals TINYINT(1) DEFAULT 1,
    attach_to_record TINYINT(1) DEFAULT 1,
    error_message TEXT NULL,
    options JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_tpm_job_status (status),
    INDEX idx_tpm_job_user (user_id),
    INDEX idx_tpm_job_info_object (information_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual files in a merge job
CREATE TABLE IF NOT EXISTS tiff_pdf_merge_file (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merge_job_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(1024) NOT NULL,
    file_size BIGINT DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT 'image/tiff',
    width INT NULL,
    height INT NULL,
    bit_depth INT NULL,
    color_space VARCHAR(50) NULL,
    page_order INT DEFAULT 0,
    status ENUM('uploaded', 'processing', 'processed', 'failed') DEFAULT 'uploaded',
    error_message TEXT NULL,
    checksum_md5 VARCHAR(32) NULL,
    metadata JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tpm_file_job (merge_job_id),
    INDEX idx_tpm_file_order (merge_job_id, page_order),
    FOREIGN KEY (merge_job_id) REFERENCES tiff_pdf_merge_job(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings for TIFF to PDF conversion
CREATE TABLE IF NOT EXISTS tiff_pdf_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO tiff_pdf_settings (setting_key, setting_value, setting_type, description) VALUES
('enabled', '1', 'boolean', 'Enable TIFF to PDF merge functionality'),
('max_files_per_job', '100', 'integer', 'Maximum number of files per merge job'),
('max_file_size_mb', '500', 'integer', 'Maximum file size in MB per file'),
('default_pdf_standard', 'pdfa-2b', 'string', 'Default PDF standard'),
('default_dpi', '300', 'integer', 'Default DPI for output PDF'),
('default_quality', '85', 'integer', 'Default compression quality 1-100'),
('temp_directory', '/tmp/tiff-pdf-merge', 'string', 'Temporary directory for processing'),
('imagemagick_path', '/usr/bin/convert', 'string', 'Path to ImageMagick convert binary'),
('ghostscript_path', '/usr/bin/gs', 'string', 'Path to Ghostscript binary'),
('allowed_extensions', '["tif","tiff","jpg","jpeg","png","bmp","gif"]', 'json', 'Allowed file extensions'),
('auto_process', '1', 'boolean', 'Automatically process jobs'),
('cleanup_temp_hours', '24', 'integer', 'Hours to keep temp files')
ON DUPLICATE KEY UPDATE updated_at = NOW();
