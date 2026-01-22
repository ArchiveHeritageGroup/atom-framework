-- =====================================================
-- Media Metadata & Transcription Database Schema
-- =====================================================

-- =====================================================
-- Media Metadata Table
-- =====================================================

CREATE TABLE IF NOT EXISTS media_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL UNIQUE,
    object_id INT NOT NULL,
    
    -- Basic info
    media_type ENUM('audio', 'video') NOT NULL,
    format VARCHAR(20),
    file_size BIGINT,
    
    -- Duration and bitrate
    duration DECIMAL(12,3),  -- seconds with milliseconds
    bitrate INT,             -- bits per second
    
    -- Audio properties
    audio_codec VARCHAR(50),
    audio_sample_rate INT,
    audio_channels INT,
    audio_bits_per_sample INT,
    audio_channel_layout VARCHAR(50),
    
    -- Video properties
    video_codec VARCHAR(50),
    video_width INT,
    video_height INT,
    video_frame_rate DECIMAL(10,3),
    video_pixel_format VARCHAR(50),
    video_aspect_ratio VARCHAR(20),
    
    -- Embedded tags/metadata
    title VARCHAR(500),
    artist VARCHAR(500),
    album VARCHAR(500),
    genre VARCHAR(255),
    year VARCHAR(50),
    copyright TEXT,
    comment TEXT,
    
    -- Device/software info
    make VARCHAR(255),
    model VARCHAR(255),
    software VARCHAR(255),
    
    -- Location
    gps_coordinates VARCHAR(100),
    
    -- Raw data (full JSON from all extractors)
    raw_metadata JSON,
    consolidated_metadata JSON,
    
    -- Waveform
    waveform_path VARCHAR(500),
    
    -- Timestamps
    extracted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_object (object_id),
    INDEX idx_digital_object (digital_object_id),
    INDEX idx_media_type (media_type),
    INDEX idx_format (format),
    FULLTEXT INDEX ft_tags (title, artist, album, genre, comment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Snippets Table
-- =====================================================

CREATE TABLE IF NOT EXISTS media_snippets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    object_id INT NOT NULL,
    
    -- Snippet info
    title VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- Time range
    start_time DECIMAL(12,3) NOT NULL,
    end_time DECIMAL(12,3) NOT NULL,
    duration DECIMAL(12,3),
    
    -- Derivatives
    export_path VARCHAR(500),       -- Exported clip file
    thumbnail_path VARCHAR(500),    -- Thumbnail image
    
    -- User info
    created_by INT,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_digital_object (digital_object_id),
    INDEX idx_object (object_id),
    INDEX idx_time (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Derivatives Table
-- =====================================================

CREATE TABLE IF NOT EXISTS media_derivatives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    
    -- Derivative info
    derivative_type ENUM('thumbnail', 'poster', 'preview', 'waveform') NOT NULL,
    derivative_index INT DEFAULT 0,  -- For multiple posters, etc.
    path VARCHAR(500) NOT NULL,
    metadata JSON,                   -- Additional info (time, size, etc.)
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_digital_object (digital_object_id),
    INDEX idx_type (derivative_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Processor Settings
-- =====================================================

CREATE TABLE IF NOT EXISTS media_processor_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    setting_group VARCHAR(50) DEFAULT 'general',
    description VARCHAR(500),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO media_processor_settings (setting_key, setting_value, setting_type, setting_group, description) VALUES
-- Thumbnail settings
('thumbnail_enabled', '1', 'boolean', 'thumbnail', 'Enable automatic thumbnail generation'),
('thumbnail_time', '5', 'integer', 'thumbnail', 'Seconds into video for thumbnail capture'),
('thumbnail_width', '480', 'integer', 'thumbnail', 'Thumbnail width in pixels'),
('thumbnail_height', '270', 'integer', 'thumbnail', 'Thumbnail height in pixels'),

-- Preview clip settings
('preview_enabled', '1', 'boolean', 'preview', 'Enable preview clip generation'),
('preview_start', '0', 'integer', 'preview', 'Preview start time in seconds'),
('preview_duration', '30', 'integer', 'preview', 'Preview duration in seconds'),
('preview_video_bitrate', '1M', 'string', 'preview', 'Video bitrate for preview'),
('preview_audio_bitrate', '128k', 'string', 'preview', 'Audio bitrate for preview'),

-- Waveform settings
('waveform_enabled', '1', 'boolean', 'waveform', 'Enable waveform generation'),
('waveform_width', '1800', 'integer', 'waveform', 'Waveform image width'),
('waveform_height', '140', 'integer', 'waveform', 'Waveform image height'),
('waveform_color', '#2980b9', 'string', 'waveform', 'Waveform color (hex)'),

-- Poster settings
('poster_enabled', '1', 'boolean', 'poster', 'Enable poster image generation'),
('poster_times', '[1, 10, 30]', 'json', 'poster', 'Time points for poster capture'),
('poster_width', '1280', 'integer', 'poster', 'Poster width in pixels'),
('poster_height', '720', 'integer', 'poster', 'Poster height in pixels'),

-- Audio preview
('audio_preview_enabled', '1', 'boolean', 'audio', 'Enable audio preview generation'),
('audio_preview_duration', '30', 'integer', 'audio', 'Audio preview duration in seconds'),

-- Transcription
('transcription_enabled', '1', 'boolean', 'transcription', 'Enable speech-to-text'),
('whisper_model', 'medium', 'string', 'transcription', 'Whisper model size'),
('default_language', 'en', 'string', 'transcription', 'Default transcription language'),
('auto_detect_language', '1', 'boolean', 'transcription', 'Auto-detect spoken language')

ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =====================================================
-- Media Chapters Table
-- =====================================================

CREATE TABLE IF NOT EXISTS media_chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_metadata_id INT NOT NULL,
    chapter_index INT NOT NULL,
    start_time DECIMAL(12,3) NOT NULL,
    end_time DECIMAL(12,3),
    title VARCHAR(500),
    
    FOREIGN KEY (media_metadata_id) REFERENCES media_metadata(id) ON DELETE CASCADE,
    INDEX idx_metadata (media_metadata_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Transcription Table
-- =====================================================

CREATE TABLE IF NOT EXISTS media_transcription (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL UNIQUE,
    object_id INT NOT NULL,
    
    -- Language
    language VARCHAR(10) DEFAULT 'en',
    
    -- Full text
    full_text LONGTEXT,
    
    -- Detailed transcription data (JSON with timestamps)
    transcription_data JSON,
    
    -- Statistics
    segment_count INT,
    duration DECIMAL(12,3),
    confidence DECIMAL(5,2),  -- Average confidence percentage
    
    -- Model info
    model_used VARCHAR(50),
    
    -- Subtitle file paths
    vtt_path VARCHAR(500),
    srt_path VARCHAR(500),
    txt_path VARCHAR(500),
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_object (object_id),
    INDEX idx_digital_object (digital_object_id),
    INDEX idx_language (language),
    FULLTEXT INDEX ft_text (full_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Speakers Table (for speaker diarization)
-- =====================================================

CREATE TABLE IF NOT EXISTS media_speakers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transcription_id INT NOT NULL,
    speaker_id VARCHAR(50) NOT NULL,  -- SPEAKER_00, SPEAKER_01, etc.
    speaker_name VARCHAR(255),         -- User-assigned name
    total_duration DECIMAL(12,3),     -- Total speaking time
    segment_count INT,
    
    FOREIGN KEY (transcription_id) REFERENCES media_transcription(id) ON DELETE CASCADE,
    INDEX idx_transcription (transcription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Media Processing Queue
-- =====================================================

CREATE TABLE IF NOT EXISTS media_processing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    object_id INT NOT NULL,
    
    -- Task type
    task_type ENUM('metadata_extraction', 'transcription', 'waveform', 'thumbnail') NOT NULL,
    
    -- Options
    task_options JSON,
    
    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    priority INT DEFAULT 0,
    
    -- Progress
    progress INT DEFAULT 0,
    progress_message VARCHAR(500),
    
    -- Error info
    error_message TEXT,
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME,
    completed_at DATETIME,
    
    INDEX idx_status (status),
    INDEX idx_task_type (task_type),
    INDEX idx_priority (priority DESC),
    INDEX idx_digital_object (digital_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Integration Settings
-- =====================================================

INSERT INTO iiif_viewer_settings (setting_key, setting_value, setting_type, description) VALUES
('enable_media_extraction', '1', 'boolean', 'Auto-extract metadata from audio/video uploads'),
('enable_transcription', '1', 'boolean', 'Enable speech-to-text transcription'),
('whisper_model', 'medium', 'string', 'Whisper model size (tiny, base, small, medium, large)'),
('default_transcription_language', 'en', 'string', 'Default language for transcription'),
('auto_detect_language', '1', 'boolean', 'Auto-detect spoken language'),
('generate_waveform', '1', 'boolean', 'Generate waveform images for audio files'),
('max_transcription_duration', '7200', 'integer', 'Maximum duration in seconds for transcription')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
