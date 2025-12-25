<?php

declare(strict_types=1);

/**
 * Digital Object Configuration
 *
 * Configurable settings for digital object handling in AtoM.
 * These values correspond to term IDs in the database.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Usage Type IDs
    |--------------------------------------------------------------------------
    |
    | Term IDs for digital object usage types from the term table.
    | These determine how a digital object is used (master, reference, etc.)
    |
    */
    'usage' => [
        'master' => (int) env('DO_USAGE_MASTER', 169),
        'reference' => (int) env('DO_USAGE_REFERENCE', 170),
        'thumbnail' => (int) env('DO_USAGE_THUMBNAIL', 171),
        'external_uri' => (int) env('DO_USAGE_EXTERNAL_URI', 172),
        'external_file' => (int) env('DO_USAGE_EXTERNAL_FILE', 359),
        'offline' => (int) env('DO_USAGE_OFFLINE', 360),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Type IDs
    |--------------------------------------------------------------------------
    |
    | Term IDs for media types from the term table.
    | Used to categorize digital objects by their content type.
    |
    */
    'media_type' => [
        'image' => (int) env('DO_MEDIA_IMAGE', 137),
        'audio' => (int) env('DO_MEDIA_AUDIO', 138),
        'text' => (int) env('DO_MEDIA_TEXT', 139),
        'video' => (int) env('DO_MEDIA_VIDEO', 140),
        'other' => (int) env('DO_MEDIA_OTHER', 141),
    ],

    /*
    |--------------------------------------------------------------------------
    | Term Type IDs
    |--------------------------------------------------------------------------
    |
    | Common term IDs used throughout the application.
    |
    */
    'term' => [
        'creation_id' => (int) env('DO_TERM_CREATION', 111),
        'publication_id' => (int) env('DO_TERM_PUBLICATION', 113),
        'contribution_id' => (int) env('DO_TERM_CONTRIBUTION', 114),
        'collection_id' => (int) env('DO_TERM_COLLECTION', 117),
        'accumulation_id' => (int) env('DO_TERM_ACCUMULATION', 118),
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Names
    |--------------------------------------------------------------------------
    |
    | Object class names stored in the object table.
    | Used for querying and creating objects by type.
    |
    */
    'class_name' => [
        'digital_object' => env('DO_CLASS_DIGITAL_OBJECT', 'QubitDigitalObject'),
        'information_object' => env('DO_CLASS_INFORMATION_OBJECT', 'QubitInformationObject'),
        'actor' => env('DO_CLASS_ACTOR', 'QubitActor'),
        'event' => env('DO_CLASS_EVENT', 'QubitEvent'),
        'repository' => env('DO_CLASS_REPOSITORY', 'QubitRepository'),
        'term' => env('DO_CLASS_TERM', 'QubitTerm'),
        'accession' => env('DO_CLASS_ACCESSION', 'QubitAccession'),
        'donor' => env('DO_CLASS_DONOR', 'QubitDonor'),
        'physical_object' => env('DO_CLASS_PHYSICAL_OBJECT', 'QubitPhysicalObject'),
        'property' => env('DO_CLASS_PROPERTY', 'QubitProperty'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Dimensions
    |--------------------------------------------------------------------------
    |
    | Maximum dimensions for derivative images.
    |
    */
    'dimensions' => [
        'reference' => [
            'max_width' => (int) env('DO_REFERENCE_MAX_WIDTH', 480),
            'max_height' => (int) env('DO_REFERENCE_MAX_HEIGHT', 480),
        ],
        'thumbnail' => [
            'max_width' => (int) env('DO_THUMBNAIL_MAX_WIDTH', 100),
            'max_height' => (int) env('DO_THUMBNAIL_MAX_HEIGHT', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Settings for file uploads.
    |
    */
    'upload' => [
        'directory' => env('DO_UPLOAD_DIR', '/usr/share/nginx/atom/uploads'),
        'allowed_extensions' => explode(',', env('DO_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,webp,tif,tiff,bmp,pdf,mp4,webm,mp3,ogg,txt,docx,xlsx,pptx')),
        'max_file_size_mb' => (int) env('DO_MAX_FILE_SIZE_MB', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnailable Formats
    |--------------------------------------------------------------------------
    |
    | MIME types that support thumbnail/derivative generation.
    |
    */
    'thumbnailable_formats' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/tiff',
        'image/bmp',
        'application/pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Compatible Formats
    |--------------------------------------------------------------------------
    |
    | MIME types that can be displayed directly in web browsers.
    |
    */
    'web_compatible_formats' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | MIME Type to Extension Mapping
    |--------------------------------------------------------------------------
    |
    | Maps MIME types to file extensions.
    |
    */
    'mime_extensions' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/tiff' => 'tif',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'text/plain' => 'txt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Derivative Quality
    |--------------------------------------------------------------------------
    |
    | Quality settings for derivative image generation.
    |
    */
    'derivative_quality' => [
        'jpeg_quality' => (int) env('DO_JPEG_QUALITY', 85),
        'png_compression' => (int) env('DO_PNG_COMPRESSION', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Extraction
    |--------------------------------------------------------------------------
    |
    | Settings for automatic metadata extraction.
    |
    */
    'metadata' => [
        'extract_on_upload' => env('DO_EXTRACT_METADATA', true),
        'apply_to_title' => env('DO_METADATA_APPLY_TITLE', true),
        'apply_to_description' => env('DO_METADATA_APPLY_DESCRIPTION', true),
        'apply_gps' => env('DO_METADATA_APPLY_GPS', true),
        'apply_creator' => env('DO_METADATA_APPLY_CREATOR', true),
        'apply_date' => env('DO_METADATA_APPLY_DATE', true),
    ],
];