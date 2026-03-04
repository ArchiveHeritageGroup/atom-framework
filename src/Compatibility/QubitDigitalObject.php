<?php

/**
 * QubitDigitalObject Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants and static properties sourced from lib/model/QubitDigitalObject.php.
 */

if (!class_exists('QubitDigitalObject', false)) {
    class QubitDigitalObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'digital_object';
        protected static string $i18nTableName = '';

        // Constants
        public const GENERIC_ICON_DIR = 'generic-icons';
        public const THUMB_MIME_TYPE = 'image/jpeg';
        public const THUMB_EXTENSION = 'jpg';

        // Loaded from data/mime_types.php via static init below class
        public static $qubitMimeTypes = [];

        // Web-compatible image formats (supported in most major browsers)
        protected static $webCompatibleImageFormats = [
            'image/jpeg',
            'image/jpg',
            'image/jpe',
            'image/gif',
            'image/png',
        ];

        // Generic thumbnail icons by MIME type
        protected static $qubitGenericThumbs = [
            'application/vnd.ms-excel' => 'excel.png',
            'application/msword' => 'word.png',
            'application/vnd.ms-powerpoint' => 'powerpoint.png',
            'audio/*' => 'audio.png',
            'video/*' => 'video.png',
            'application/pdf' => 'pdf.png',
            'text/plain' => 'text.png',
            'application/rtf' => 'text.png',
            'text/richtext' => 'text.png',
            'application/x-tar' => 'archive.png',
            'application/zip' => 'archive.png',
            'application/x-rar-compressed' => 'archive.png',
            'image/jpeg' => 'image.png',
            'image/jpg' => 'image.png',
            'image/jpe' => 'image.png',
            'image/gif' => 'image.png',
            'image/png' => 'image.png',
        ];

        // Generic reference icons (catch-all)
        protected static $qubitGenericReference = [
            '*/*' => 'blank.png',
        ];

        /**
         * Derive MIME type from a filename's extension.
         *
         * @param string $filename Filename or path
         *
         * @return string|null MIME type or null if unknown
         */
        public static function deriveMimeType($filename)
        {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ('' === $extension) {
                return null;
            }

            return self::$qubitMimeTypes[$extension] ?? null;
        }

        /**
         * Get generic icon path for a MIME type and usage type.
         *
         * @param string $mimeType  MIME type
         * @param int    $usageType Usage ID (QubitTerm::THUMBNAIL_ID, REFERENCE_ID, etc.)
         *
         * @return string|null Relative path to icon
         */
        public static function getGenericIconPath($mimeType, $usageType)
        {
            $iconDir = '/images/' . self::GENERIC_ICON_DIR;

            // Check thumbnails first for thumbnail usage
            if (\QubitTerm::THUMBNAIL_ID === $usageType) {
                // Exact match
                if (isset(self::$qubitGenericThumbs[$mimeType])) {
                    return $iconDir . '/' . self::$qubitGenericThumbs[$mimeType];
                }

                // Wildcard match (e.g., audio/*)
                $type = explode('/', $mimeType)[0] ?? '';
                $wildcard = $type . '/*';
                if (isset(self::$qubitGenericThumbs[$wildcard])) {
                    return $iconDir . '/' . self::$qubitGenericThumbs[$wildcard];
                }
            }

            // Reference/fallback: use generic reference icons
            if (isset(self::$qubitGenericReference[$mimeType])) {
                return $iconDir . '/' . self::$qubitGenericReference[$mimeType];
            }

            // Final fallback: blank icon
            return $iconDir . '/' . self::$qubitGenericReference['*/*'];
        }

        /**
         * Get a generic representation stub for a MIME type.
         *
         * @param string $mimeType  MIME type
         * @param int    $usageType Usage type ID
         *
         * @return static Stub instance with path set to icon
         */
        public static function getGenericRepresentation($mimeType, $usageType)
        {
            $obj = new static();
            $iconPath = self::getGenericIconPath($mimeType, $usageType);
            $obj->path = $iconPath ? dirname($iconPath) . '/' : '';
            $obj->name = $iconPath ? basename($iconPath) : 'blank.png';
            $obj->mime_type = 'image/png';
            $obj->media_type_id = \QubitTerm::IMAGE_ID;

            return $obj;
        }

        /**
         * Get full relative path (path + name).
         *
         * @return string
         */
        public function getFullPath()
        {
            return ($this->path ?? '') . ($this->name ?? '');
        }

        /**
         * Get absolute filesystem path.
         *
         * @return string
         */
        public function getAbsolutePath()
        {
            return \sfConfig::get('sf_web_dir', '/usr/share/nginx/archive')
                . ($this->path ?? '')
                . ($this->name ?? '');
        }

        /**
         * Get child digital object by usage ID (thumbnail, reference, etc.).
         *
         * @param int $usageId Usage type ID (QubitTerm::THUMBNAIL_ID, etc.)
         *
         * @return static|null
         */
        public function getChildByUsageId($usageId)
        {
            try {
                $row = \Illuminate\Database\Capsule\Manager::table('digital_object')
                    ->where('parent_id', $this->id)
                    ->where('usage_id', $usageId)
                    ->first();

                return $row ? self::hydrate($row) : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        /**
         * Check if this digital object is a web-compatible image format.
         *
         * @return bool
         */
        public function isWebCompatibleImageFormat()
        {
            return in_array($this->mime_type ?? '', self::$webCompatibleImageFormats, true);
        }

        /**
         * Check if this digital object is an image (by media type).
         *
         * @return bool
         */
        public function isImage()
        {
            return ($this->media_type_id ?? null) == \QubitTerm::IMAGE_ID;
        }

        /**
         * Check if a MIME type can be thumbnailed.
         *
         * @return bool
         */
        public function canThumbnail()
        {
            return self::canThumbnailMimeType($this->mime_type ?? '');
        }

        /**
         * Static check if a MIME type supports thumbnail generation.
         *
         * @param string $mimeType MIME type
         *
         * @return bool
         */
        public static function canThumbnailMimeType($mimeType)
        {
            // Image formats are always thumbnailable
            if (str_starts_with($mimeType, 'image/')) {
                return true;
            }

            // PDF can be thumbnailed with Ghostscript/ImageMagick
            if ('application/pdf' === $mimeType) {
                return true;
            }

            // Video can be thumbnailed with ffmpeg
            if (str_starts_with($mimeType, 'video/')) {
                return true;
            }

            return false;
        }

        /**
         * Check if digital object should be displayed as a compound object.
         *
         * @return bool
         */
        public function showAsCompoundDigitalObject()
        {
            try {
                // Check the property table for display_as_compound flag
                $prop = \Illuminate\Database\Capsule\Manager::table('property as p')
                    ->join('property_i18n as pi', 'p.id', '=', 'pi.id')
                    ->where('p.object_id', $this->object_id ?? $this->id)
                    ->where('p.name', 'displayAsCompound')
                    ->first();

                if ($prop && isset($prop->value)) {
                    return '1' === $prop->value || 'true' === $prop->value;
                }

                return false;
            } catch (\Throwable $e) {
                return false;
            }
        }

        /**
         * Get the maximum upload size (bytes) from PHP configuration.
         *
         * @return int Size in bytes
         */
        public static function getMaxUploadSize()
        {
            $limits = [
                self::parseIniSize(ini_get('upload_max_filesize')),
                self::parseIniSize(ini_get('post_max_size')),
            ];

            // Also check app-level setting
            $appMax = \sfConfig::get('app_upload_limit', 0);
            if ($appMax > 0) {
                $limits[] = $appMax;
            }

            return min(array_filter($limits));
        }

        /**
         * Get maximum image dimensions for a given usage ID.
         *
         * @param int $usageId Usage type ID
         *
         * @return array [width, height]
         */
        public static function getImageMaxDimensions($usageId)
        {
            switch ($usageId) {
                case \QubitTerm::THUMBNAIL_ID:
                    return [
                        (int) \sfConfig::get('app_thumbnail_maxwidth', 100),
                        (int) \sfConfig::get('app_thumbnail_maxheight', 100),
                    ];

                case \QubitTerm::REFERENCE_ID:
                    return [
                        (int) \sfConfig::get('app_reference_image_maxwidth', 480),
                        (int) \sfConfig::get('app_reference_image_maxheight', 480),
                    ];

                default:
                    return [0, 0];
            }
        }

        /**
         * Parse PHP ini size strings (e.g., '8M', '512K') to bytes.
         *
         * @param string $size INI size string
         *
         * @return int Size in bytes
         */
        private static function parseIniSize($size)
        {
            $size = trim($size);
            $last = strtolower($size[strlen($size) - 1] ?? '');

            $numericValue = (int) $size;

            switch ($last) {
                case 'g':
                    $numericValue *= 1024 * 1024 * 1024;

                    break;

                case 'm':
                    $numericValue *= 1024 * 1024;

                    break;

                case 'k':
                    $numericValue *= 1024;

                    break;
            }

            return $numericValue;
        }
    }

    // Load MIME types from external data file
    QubitDigitalObject::$qubitMimeTypes = require __DIR__ . '/data/mime_types.php';
}
