<?php
declare(strict_types=1);

namespace AtomExtensions\Constants;

/**
 * Level of Description Constants.
 */
class LevelConstants
{
    // Archive levels (ISAD standard)
    public const RECORD_GROUP_ID = 434;
    public const FONDS_ID = 236;
    public const SUBFONDS_ID = 237;
    public const COLLECTION_ID = 238;
    public const SERIES_ID = 239;
    public const SUBSERIES_ID = 240;
    public const FILE_ID = 241;
    public const ITEM_ID = 242;
    public const PART_ID = 299;
    
    // Museum levels
    public const OBJECT_ID = 500;
    public const ARTWORK_ID = 1750;
    public const ARTIFACT_ID = 1751;
    public const SPECIMEN_ID = 1752;
    public const INSTALLATION_ID = 512;
    
    // Library levels
    public const BOOK_ID = 1700;
    public const MONOGRAPH_ID = 1701;
    public const PERIODICAL_ID = 1702;
    public const JOURNAL_ID = 1703;
    public const MANUSCRIPT_ID = 1704;
    public const ARTICLE_ID = 1759;
    
    // DAM levels
    public const PHOTOGRAPH_ID = 1753;
    public const AUDIO_ID = 1754;
    public const VIDEO_ID = 1755;
    public const IMAGE_ID = 1756;
    public const MODEL_3D_ID = 1757;
    public const DATASET_ID = 1758;
    public const DOCUMENT_ID = 1161;
    
    // MIME type to level mapping
    public const MIME_MAPPING = [
        // Images -> Photograph or Image
        'image/jpeg' => self::PHOTOGRAPH_ID,
        'image/tiff' => self::PHOTOGRAPH_ID,
        'image/png' => self::IMAGE_ID,
        'image/gif' => self::IMAGE_ID,
        'image/webp' => self::IMAGE_ID,
        'image/svg+xml' => self::IMAGE_ID,
        
        // Audio
        'audio/mpeg' => self::AUDIO_ID,
        'audio/wav' => self::AUDIO_ID,
        'audio/ogg' => self::AUDIO_ID,
        'audio/flac' => self::AUDIO_ID,
        'audio/aac' => self::AUDIO_ID,
        
        // Video
        'video/mp4' => self::VIDEO_ID,
        'video/webm' => self::VIDEO_ID,
        'video/quicktime' => self::VIDEO_ID,
        'video/x-msvideo' => self::VIDEO_ID,
        'video/x-matroska' => self::VIDEO_ID,
        
        // Documents
        'application/pdf' => self::DOCUMENT_ID,
        'application/msword' => self::DOCUMENT_ID,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::DOCUMENT_ID,
        
        // 3D Models
        'model/gltf+json' => self::MODEL_3D_ID,
        'model/gltf-binary' => self::MODEL_3D_ID,
        'model/obj' => self::MODEL_3D_ID,
        'model/stl' => self::MODEL_3D_ID,
        
        // Data
        'text/csv' => self::DATASET_ID,
        'application/json' => self::DATASET_ID,
        'application/xml' => self::DATASET_ID,
    ];
    
    /**
     * Detect level from MIME type.
     */
    public static function detectFromMimeType(string $mimeType): ?int
    {
        // Direct match
        if (isset(self::MIME_MAPPING[$mimeType])) {
            return self::MIME_MAPPING[$mimeType];
        }
        
        // Pattern matching
        if (str_starts_with($mimeType, 'image/')) {
            return self::IMAGE_ID;
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return self::AUDIO_ID;
        }
        if (str_starts_with($mimeType, 'video/')) {
            return self::VIDEO_ID;
        }
        if (str_starts_with($mimeType, 'model/')) {
            return self::MODEL_3D_ID;
        }
        
        return null;
    }
}
