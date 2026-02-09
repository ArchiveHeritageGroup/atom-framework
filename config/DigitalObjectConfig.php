<?php

declare(strict_types=1);

namespace AtomExtensions\Config;

/**
 * Digital Object Configuration Helper
 *
 * Provides centralized access to digital object configuration values.
 * Replaces hardcoded Qubit constants with configurable values.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class DigitalObjectConfig
{
    protected static ?array $config = null;

    /**
     * Load configuration
     */
    protected static function load(): array
    {
        if (self::$config === null) {
            $configPath = dirname(__DIR__, 2) . '/config/digital_object.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            } else {
                self::$config = self::defaults();
            }
        }

        return self::$config;
    }

    /**
     * Get a config value by dot notation key
     */
    public static function get(string $key, $default = null)
    {
        $config = self::load();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Default configuration values
     */
    protected static function defaults(): array
    {
        return [
            'usage' => [
                'master' => 140,
                'reference' => 141,
                'thumbnail' => 142,
                'chapters' => 195,
                'subtitles' => 196,
            ],
            'media_type' => [
                'audio' => 135,
                'image' => 136,
                'text' => 137,
                'video' => 138,
                'other' => 139,
            ],
            'taxonomy' => [
                'media_type' => 46,
                'digital_object_usage' => 47,
                'subject' => 35,
                'level_of_description' => 34,
            ],
            'class_name' => [
                'digital_object' => 'QubitDigitalObject',
                'information_object' => 'QubitInformationObject',
                'actor' => 'QubitActor',
                'repository' => 'QubitRepository',
                'event' => 'QubitEvent',
                'property' => 'QubitProperty',
                'term' => 'QubitTerm',
                'relation' => 'QubitRelation',
                'object_term_relation' => 'QubitObjectTermRelation',
                'setting' => 'QubitSetting',
            ],
            'term' => [
                'creation_id' => 111,
                'name_access_point_id' => 177,
                'publication_status_draft' => 159,
                'publication_status_published' => 160,
            ],
            'root' => [
                'information_object' => 1,
                'actor' => 3,
                'term_subject' => 110,
            ],
            'dimensions' => [
                'reference_max_width' => 480,
                'reference_max_height' => 480,
                'thumbnail_max_width' => 100,
                'thumbnail_max_height' => 100,
            ],
            'upload' => [
                'jpeg_quality' => 85,
                'extract_metadata_on_upload' => true,
            ],
            'formats' => [
                'thumbnailable' => [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'image/tiff',
                ],
                'web_compatible' => [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'image/svg+xml',
                ],
            ],
            'metadata' => [
                'apply_to_title' => false,
                'apply_to_description' => false,
                'apply_creator' => true,
                'apply_date' => true,
                'apply_gps' => true,
            ],
        ];
    }

    // =========================================================================
    // Usage Types
    // =========================================================================

    public static function usageMaster(): int
    {
        return self::get('usage.master', 140);
    }

    public static function usageReference(): int
    {
        return self::get('usage.reference', 141);
    }

    public static function usageThumbnail(): int
    {
        return self::get('usage.thumbnail', 142);
    }

    public static function usageChapters(): int
    {
        return self::get('usage.chapters', 195);
    }

    public static function usageSubtitles(): int
    {
        return self::get('usage.subtitles', 196);
    }

    // =========================================================================
    // Media Types
    // =========================================================================

    public static function mediaAudio(): int
    {
        return self::get('media_type.audio', 135);
    }

    public static function mediaImage(): int
    {
        return self::get('media_type.image', 136);
    }

    public static function mediaText(): int
    {
        return self::get('media_type.text', 137);
    }

    public static function mediaVideo(): int
    {
        return self::get('media_type.video', 138);
    }

    public static function mediaOther(): int
    {
        return self::get('media_type.other', 139);
    }

    /**
     * Get media type ID from MIME type
     */
    public static function mediaTypeFromMime(string $mimeType): int
    {
        if (strpos($mimeType, 'image/') === 0) {
            return self::mediaImage();
        }
        if (strpos($mimeType, 'audio/') === 0) {
            return self::mediaAudio();
        }
        if (strpos($mimeType, 'video/') === 0) {
            return self::mediaVideo();
        }
        if (strpos($mimeType, 'text/') === 0 || $mimeType === 'application/pdf') {
            return self::mediaText();
        }

        return self::mediaOther();
    }

    // =========================================================================
    // Taxonomies
    // =========================================================================

    public static function taxonomyMediaType(): int
    {
        return self::get('taxonomy.media_type', 46);
    }

    public static function taxonomyDigitalObjectUsage(): int
    {
        return self::get('taxonomy.digital_object_usage', 47);
    }

    public static function taxonomySubject(): int
    {
        return self::get('taxonomy.subject', 35);
    }

    public static function taxonomyLevelOfDescription(): int
    {
        return self::get('taxonomy.level_of_description', 34);
    }

    // =========================================================================
    // Class Names
    // =========================================================================

    public static function classDigitalObject(): string
    {
        return self::get('class_name.digital_object', 'QubitDigitalObject');
    }

    public static function classInformationObject(): string
    {
        return self::get('class_name.information_object', 'QubitInformationObject');
    }

    public static function classActor(): string
    {
        return self::get('class_name.actor', 'QubitActor');
    }

    public static function classRepository(): string
    {
        return self::get('class_name.repository', 'QubitRepository');
    }

    public static function classEvent(): string
    {
        return self::get('class_name.event', 'QubitEvent');
    }

    public static function classProperty(): string
    {
        return self::get('class_name.property', 'QubitProperty');
    }

    public static function classTerm(): string
    {
        return self::get('class_name.term', 'QubitTerm');
    }

    public static function classRelation(): string
    {
        return self::get('class_name.relation', 'QubitRelation');
    }

    public static function classObjectTermRelation(): string
    {
        return self::get('class_name.object_term_relation', 'QubitObjectTermRelation');
    }

    public static function classSetting(): string
    {
        return self::get('class_name.setting', 'QubitSetting');
    }

    // =========================================================================
    // Terms
    // =========================================================================

    public static function termCreationId(): int
    {
        return self::get('term.creation_id', 111);
    }

    public static function termNameAccessPointId(): int
    {
        return self::get('term.name_access_point_id', 177);
    }

    public static function termPublicationStatusDraft(): int
    {
        return self::get('term.publication_status_draft', 159);
    }

    public static function termPublicationStatusPublished(): int
    {
        return self::get('term.publication_status_published', 160);
    }

    // =========================================================================
    // Root IDs
    // =========================================================================

    public static function rootInformationObject(): int
    {
        return self::get('root.information_object', 1);
    }

    public static function rootActor(): int
    {
        return self::get('root.actor', 3);
    }

    public static function rootTermSubject(): int
    {
        return self::get('root.term_subject', 110);
    }

    // =========================================================================
    // Dimensions
    // =========================================================================

    public static function referenceMaxWidth(): int
    {
        return self::get('dimensions.reference_max_width', 480);
    }

    public static function referenceMaxHeight(): int
    {
        return self::get('dimensions.reference_max_height', 480);
    }

    public static function thumbnailMaxWidth(): int
    {
        return self::get('dimensions.thumbnail_max_width', 100);
    }

    public static function thumbnailMaxHeight(): int
    {
        return self::get('dimensions.thumbnail_max_height', 100);
    }

    // =========================================================================
    // Upload Settings
    // =========================================================================

    /**
     * Get upload directory - uses PathResolver, no hardcoded path
     */
    public static function uploadDirectory(): string
    {
        return \AtomFramework\Helpers\PathResolver::getUploadsDir();
    }

    public static function jpegQuality(): int
    {
        return self::get('upload.jpeg_quality', 85);
    }

    public static function extractMetadataOnUpload(): bool
    {
        return self::get('upload.extract_metadata_on_upload', true);
    }

    // =========================================================================
    // Formats
    // =========================================================================

    public static function thumbnailableFormats(): array
    {
        return self::get('formats.thumbnailable', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/tiff',
        ]);
    }

    public static function webCompatibleFormats(): array
    {
        return self::get('formats.web_compatible', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ]);
    }

    /**
     * Check if MIME type can have thumbnail generated
     */
    public static function isThumbnailable(string $mimeType): bool
    {
        return in_array($mimeType, self::thumbnailableFormats());
    }

    /**
     * Check if MIME type is web compatible
     */
    public static function isWebCompatible(string $mimeType): bool
    {
        return in_array($mimeType, self::webCompatibleFormats());
    }

    // =========================================================================
    // Metadata
    // =========================================================================

    public static function applyMetadataToTitle(): bool
    {
        return self::get('metadata.apply_to_title', false);
    }

    public static function applyMetadataToDescription(): bool
    {
        return self::get('metadata.apply_to_description', false);
    }

    public static function applyMetadataCreator(): bool
    {
        return self::get('metadata.apply_creator', true);
    }

    public static function applyMetadataDate(): bool
    {
        return self::get('metadata.apply_date', true);
    }

    public static function applyMetadataGps(): bool
    {
        return self::get('metadata.apply_gps', true);
    }

    // =========================================================================
    // MIME Extensions
    // =========================================================================

    public static function mimeExtensions(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/flac' => 'flac',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'text/xml' => 'xml',
            'application/json' => 'json',
        ];
    }

    /**
     * Get file extension for MIME type
     */
    public static function getExtensionForMime(string $mimeType): string
    {
        $extensions = self::mimeExtensions();

        return $extensions[$mimeType] ?? 'bin';
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Generate storage path from ID
     */
    public static function generateStoragePath(int $id): string
    {
        $parts = str_split(str_pad((string) $id, 9, '0', STR_PAD_LEFT), 3);

        return implode('/', $parts);
    }

    /**
     * Get all usage IDs as array
     */
    public static function getAllUsageIds(): array
    {
        return [
            'master' => self::usageMaster(),
            'reference' => self::usageReference(),
            'thumbnail' => self::usageThumbnail(),
            'chapters' => self::usageChapters(),
            'subtitles' => self::usageSubtitles(),
        ];
    }

    /**
     * Get all media type IDs as array
     */
    public static function getAllMediaTypeIds(): array
    {
        return [
            'audio' => self::mediaAudio(),
            'image' => self::mediaImage(),
            'text' => self::mediaText(),
            'video' => self::mediaVideo(),
            'other' => self::mediaOther(),
        ];
    }

    /**
     * Reset config (for testing)
     */
    public static function reset(): void
    {
        self::$config = null;
    }
}