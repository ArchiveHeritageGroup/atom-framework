<?php

/*
 * Universal Metadata Extraction Service for AtoM
 * 
 * Extracts embedded metadata from various file types:
 * - Images: EXIF, IPTC, XMP
 * - PDFs: Title, Author, Keywords, Creator, Producer
 * - Office: DOCX, XLSX, PPTX (Open XML)
 * - Video: Duration, Codec, Resolution, Framerate
 * - Audio: ID3 tags, Duration, Bitrate, Sample rate
 * - Face Detection: For authority record linking
 * 
 * @package    arMetadataExtractorPlugin
 * @subpackage lib/services
 * @author     The AHG
 * @version    2.0
 */

class ahgUniversalMetadataExtractor
{
    // Supported file types by category
    const TYPE_IMAGE = 'image';
    const TYPE_PDF = 'pdf';
    const TYPE_OFFICE = 'office';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    
    // MIME type mappings
    protected static $mimeCategories = [
        'image/jpeg' => self::TYPE_IMAGE,
        'image/png' => self::TYPE_IMAGE,
        'image/tiff' => self::TYPE_IMAGE,
        'image/webp' => self::TYPE_IMAGE,
        'image/gif' => self::TYPE_IMAGE,
        'image/bmp' => self::TYPE_IMAGE,
        'application/pdf' => self::TYPE_PDF,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::TYPE_OFFICE,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::TYPE_OFFICE,
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => self::TYPE_OFFICE,
        'application/msword' => self::TYPE_OFFICE,
        'application/vnd.ms-excel' => self::TYPE_OFFICE,
        'application/vnd.ms-powerpoint' => self::TYPE_OFFICE,
        'video/mp4' => self::TYPE_VIDEO,
        'video/webm' => self::TYPE_VIDEO,
        'video/ogg' => self::TYPE_VIDEO,
        'video/quicktime' => self::TYPE_VIDEO,
        'video/x-msvideo' => self::TYPE_VIDEO,
        'video/x-matroska' => self::TYPE_VIDEO,
        'audio/mpeg' => self::TYPE_AUDIO,
        'audio/mp3' => self::TYPE_AUDIO,
        'audio/wav' => self::TYPE_AUDIO,
        'audio/ogg' => self::TYPE_AUDIO,
        'audio/flac' => self::TYPE_AUDIO,
        'audio/aac' => self::TYPE_AUDIO,
        'audio/x-m4a' => self::TYPE_AUDIO,
    ];
    
    // Extension mappings (fallback)
    protected static $extensionCategories = [
        'jpg' => self::TYPE_IMAGE,
        'jpeg' => self::TYPE_IMAGE,
        'png' => self::TYPE_IMAGE,
        'tif' => self::TYPE_IMAGE,
        'tiff' => self::TYPE_IMAGE,
        'webp' => self::TYPE_IMAGE,
        'gif' => self::TYPE_IMAGE,
        'bmp' => self::TYPE_IMAGE,
        'pdf' => self::TYPE_PDF,
        'docx' => self::TYPE_OFFICE,
        'xlsx' => self::TYPE_OFFICE,
        'pptx' => self::TYPE_OFFICE,
        'doc' => self::TYPE_OFFICE,
        'xls' => self::TYPE_OFFICE,
        'ppt' => self::TYPE_OFFICE,
        'mp4' => self::TYPE_VIDEO,
        'webm' => self::TYPE_VIDEO,
        'ogv' => self::TYPE_VIDEO,
        'mov' => self::TYPE_VIDEO,
        'avi' => self::TYPE_VIDEO,
        'mkv' => self::TYPE_VIDEO,
        'mp3' => self::TYPE_AUDIO,
        'wav' => self::TYPE_AUDIO,
        'ogg' => self::TYPE_AUDIO,
        'flac' => self::TYPE_AUDIO,
        'aac' => self::TYPE_AUDIO,
        'm4a' => self::TYPE_AUDIO,
    ];
    
    protected $filePath;
    protected $mimeType;
    protected $fileType;
    protected $metadata = [];
    protected $errors = [];
    
    /**
     * Constructor
     * 
     * @param string $filePath Path to the file
     * @param string $mimeType Optional MIME type (auto-detected if not provided)
     */
    public function __construct($filePath, $mimeType = null)
    {
        $this->filePath = $filePath;
        $this->mimeType = $mimeType ?: $this->detectMimeType($filePath);
        $this->fileType = $this->determineFileType();
    }
    
    /**
     * Detect MIME type of file
     */
    protected function detectMimeType($filePath)
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }
        
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mime;
        }
        
        // Fallback to extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
        ];
        
        return $mimeMap[$ext] ?? 'application/octet-stream';
    }
    
    /**
     * Determine file type category
     */
    protected function determineFileType()
    {
        // Try MIME type first
        if (isset(self::$mimeCategories[$this->mimeType])) {
            return self::$mimeCategories[$this->mimeType];
        }
        
        // Fallback to extension
        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        if (isset(self::$extensionCategories[$ext])) {
            return self::$extensionCategories[$ext];
        }
        
        return null;
    }
    
    /**
     * Extract all metadata from file
     * 
     * @return array Extracted metadata
     */
    public function extractAll()
    {
        if (!file_exists($this->filePath)) {
            $this->errors[] = 'File not found: ' . $this->filePath;
            return [];
        }
        
        $this->metadata = [
            'file' => [
                'path' => $this->filePath,
                'name' => basename($this->filePath),
                'size' => filesize($this->filePath),
                'mime_type' => $this->mimeType,
                'type_category' => $this->fileType,
                'extension' => pathinfo($this->filePath, PATHINFO_EXTENSION),
                'modified' => date('Y-m-d H:i:s', filemtime($this->filePath)),
            ]
        ];
        
        switch ($this->fileType) {
            case self::TYPE_IMAGE:
                $this->extractImageMetadata();
                break;
            case self::TYPE_PDF:
                $this->extractPdfMetadata();
                break;
            case self::TYPE_OFFICE:
                $this->extractOfficeMetadata();
                break;
            case self::TYPE_VIDEO:
                $this->extractVideoMetadata();
                break;
            case self::TYPE_AUDIO:
                $this->extractAudioMetadata();
                break;
        }
        
        return $this->metadata;
    }
    
    /**
     * Get errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Get file type category
     */
    public function getFileType()
    {
        return $this->fileType;
    }
    
    // =========================================================================
    // IMAGE METADATA EXTRACTION (EXIF, IPTC, XMP)
    // =========================================================================
    
    /**
     * Extract image metadata (EXIF, IPTC, XMP)
     */
    protected function extractImageMetadata()
    {
        // Get image dimensions
        $imageInfo = @getimagesize($this->filePath);
        if ($imageInfo) {
            $this->metadata['image'] = [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'type' => $imageInfo[2],
                'bits' => $imageInfo['bits'] ?? null,
                'channels' => $imageInfo['channels'] ?? null,
            ];
        }
        
        // Extract EXIF
        $this->metadata['exif'] = $this->extractExif();
        
        // Extract IPTC
        $this->metadata['iptc'] = $this->extractIptc();
        
        // Extract XMP
        $this->metadata['xmp'] = $this->extractXmp();
        
        // Extract GPS coordinates
        if (!empty($this->metadata['exif'])) {
            $this->metadata['gps'] = $this->extractGpsCoordinates($this->metadata['exif']);
        }
        
        // Consolidate key fields
        $this->metadata['consolidated'] = $this->consolidateImageMetadata();
    }
    
    /**
     * Extract EXIF data
     */
    protected function extractExif()
    {
        if (!function_exists('exif_read_data')) {
            $this->errors[] = 'EXIF extension not available';
            return null;
        }
        
        // Check if supported image type
        $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];
        $imageType = @exif_imagetype($this->filePath);
        
        if (!in_array($imageType, $supportedTypes)) {
            return null;
        }
        
        try {
            $exif = @exif_read_data($this->filePath, 'ANY_TAG', true);
            
            if (!$exif || !is_array($exif)) {
                return null;
            }
            
            // Flatten nested sections
            $flat = [];
            foreach ($exif as $section => $data) {
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        // Clean up binary data
                        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                            $value = '[Binary Data]';
                        }
                        $flat[$key] = $value;
                    }
                } else {
                    $flat[$section] = $data;
                }
            }
            
            return $flat;
            
        } catch (Exception $e) {
            $this->errors[] = 'EXIF extraction error: ' . $e->getMessage();
            return null;
        }
    }
    
    /**
     * Extract IPTC data
     */
    protected function extractIptc()
    {
        $info = [];
        
        if (!@getimagesize($this->filePath, $info)) {
            return null;
        }
        
        if (!isset($info['APP13'])) {
            return null;
        }
        
        $iptc = @iptcparse($info['APP13']);
        
        if (!$iptc || !is_array($iptc)) {
            return null;
        }
        
        // IPTC field mappings
        $fieldMap = [
            '2#005' => 'object_name',
            '2#010' => 'urgency',
            '2#015' => 'category',
            '2#020' => 'supplemental_category',
            '2#025' => 'keywords',
            '2#040' => 'special_instructions',
            '2#055' => 'date_created',
            '2#060' => 'time_created',
            '2#062' => 'digital_creation_date',
            '2#063' => 'digital_creation_time',
            '2#065' => 'originating_program',
            '2#070' => 'program_version',
            '2#080' => 'byline',
            '2#085' => 'byline_title',
            '2#090' => 'city',
            '2#092' => 'sub_location',
            '2#095' => 'province_state',
            '2#100' => 'country_code',
            '2#101' => 'country',
            '2#103' => 'original_transmission_reference',
            '2#105' => 'headline',
            '2#110' => 'credit',
            '2#115' => 'source',
            '2#116' => 'copyright',
            '2#118' => 'contact',
            '2#120' => 'caption',
            '2#122' => 'writer',
        ];
        
        $parsed = [];
        foreach ($iptc as $code => $values) {
            $fieldName = $fieldMap[$code] ?? $code;
            
            if (count($values) === 1) {
                $parsed[$fieldName] = $this->cleanString($values[0]);
            } else {
                $parsed[$fieldName] = array_map([$this, 'cleanString'], $values);
            }
        }
        
        return $parsed;
    }
    
    /**
     * Extract XMP data
     */
    protected function extractXmp()
    {
        $content = @file_get_contents($this->filePath);
        
        if (!$content) {
            return null;
        }
        
        // Find XMP packet
        $start = strpos($content, '<x:xmpmeta');
        if ($start === false) {
            $start = strpos($content, '<?xpacket begin');
        }
        
        if ($start === false) {
            return null;
        }
        
        $end = strpos($content, '</x:xmpmeta>', $start);
        if ($end === false) {
            $end = strpos($content, '<?xpacket end', $start);
        }
        
        if ($end === false) {
            return null;
        }
        
        $xmpData = substr($content, $start, $end - $start + 15);
        
        // Parse XMP XML
        return $this->parseXmpXml($xmpData);
    }
    
    /**
     * Parse XMP XML content
     */
    protected function parseXmpXml($xmpData)
    {
        $parsed = [];
        
        // Dublin Core elements
        if (preg_match('/<dc:title[^>]*>.*?<rdf:Alt[^>]*>.*?<rdf:li[^>]*>([^<]+)/s', $xmpData, $matches)) {
            $parsed['title'] = $this->cleanString($matches[1]);
        }
        
        if (preg_match('/<dc:description[^>]*>.*?<rdf:Alt[^>]*>.*?<rdf:li[^>]*>([^<]+)/s', $xmpData, $matches)) {
            $parsed['description'] = $this->cleanString($matches[1]);
        }
        
        // Creators (can be multiple)
        if (preg_match_all('/<dc:creator[^>]*>.*?<rdf:Seq[^>]*>(.*?)<\/rdf:Seq>/s', $xmpData, $matches)) {
            preg_match_all('/<rdf:li[^>]*>([^<]+)<\/rdf:li>/', $matches[1][0] ?? '', $creators);
            if (!empty($creators[1])) {
                $parsed['creator'] = array_map([$this, 'cleanString'], $creators[1]);
            }
        }
        
        // Keywords/Subjects
        if (preg_match_all('/<dc:subject[^>]*>.*?<rdf:Bag[^>]*>(.*?)<\/rdf:Bag>/s', $xmpData, $matches)) {
            preg_match_all('/<rdf:li[^>]*>([^<]+)<\/rdf:li>/', $matches[1][0] ?? '', $keywords);
            if (!empty($keywords[1])) {
                $parsed['keywords'] = array_map([$this, 'cleanString'], $keywords[1]);
            }
        }
        
        // Rights
        if (preg_match('/<dc:rights[^>]*>.*?<rdf:Alt[^>]*>.*?<rdf:li[^>]*>([^<]+)/s', $xmpData, $matches)) {
            $parsed['rights'] = $this->cleanString($matches[1]);
        }
        
        // Photoshop/IPTC Core
        if (preg_match('/<photoshop:AuthorsPosition>([^<]+)/s', $xmpData, $matches)) {
            $parsed['authors_position'] = $this->cleanString($matches[1]);
        }
        
        if (preg_match('/<photoshop:City>([^<]+)/s', $xmpData, $matches)) {
            $parsed['city'] = $this->cleanString($matches[1]);
        }
        
        if (preg_match('/<photoshop:State>([^<]+)/s', $xmpData, $matches)) {
            $parsed['state'] = $this->cleanString($matches[1]);
        }
        
        if (preg_match('/<photoshop:Country>([^<]+)/s', $xmpData, $matches)) {
            $parsed['country'] = $this->cleanString($matches[1]);
        }
        
        // XMP Basic
        if (preg_match('/<xmp:CreateDate>([^<]+)/s', $xmpData, $matches)) {
            $parsed['create_date'] = $this->cleanString($matches[1]);
        }
        
        if (preg_match('/<xmp:ModifyDate>([^<]+)/s', $xmpData, $matches)) {
            $parsed['modify_date'] = $this->cleanString($matches[1]);
        }
        
        if (preg_match('/<xmp:CreatorTool>([^<]+)/s', $xmpData, $matches)) {
            $parsed['creator_tool'] = $this->cleanString($matches[1]);
        }
        
        // EXIF in XMP
        if (preg_match('/<exif:DateTimeOriginal>([^<]+)/s', $xmpData, $matches)) {
            $parsed['date_time_original'] = $this->cleanString($matches[1]);
        }
        
        return empty($parsed) ? null : $parsed;
    }
    
    /**
     * Extract GPS coordinates from EXIF
     */
    protected function extractGpsCoordinates($exif)
    {
        if (!isset($exif['GPSLatitude']) || !isset($exif['GPSLongitude'])) {
            return null;
        }
        
        $lat = $this->gpsToDecimal(
            $exif['GPSLatitude'],
            $exif['GPSLatitudeRef'] ?? 'N'
        );
        
        $lon = $this->gpsToDecimal(
            $exif['GPSLongitude'],
            $exif['GPSLongitudeRef'] ?? 'E'
        );
        
        if ($lat === null || $lon === null) {
            return null;
        }
        
        $gps = [
            'latitude' => $lat,
            'longitude' => $lon,
            'decimal' => sprintf('%.6f, %.6f', $lat, $lon),
        ];
        
        // Altitude if available
        if (isset($exif['GPSAltitude'])) {
            $alt = $this->parseRational($exif['GPSAltitude']);
            if ($alt !== null) {
                $ref = $exif['GPSAltitudeRef'] ?? 0;
                $gps['altitude'] = $ref == 1 ? -$alt : $alt;
            }
        }
        
        return $gps;
    }
    
    /**
     * Convert GPS coordinates to decimal
     */
    protected function gpsToDecimal($coordinate, $ref)
    {
        if (!is_array($coordinate) || count($coordinate) !== 3) {
            return null;
        }
        
        $degrees = $this->parseRational($coordinate[0]);
        $minutes = $this->parseRational($coordinate[1]);
        $seconds = $this->parseRational($coordinate[2]);
        
        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }
        
        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        
        if ($ref === 'S' || $ref === 'W') {
            $decimal = -$decimal;
        }
        
        return $decimal;
    }
    
    /**
     * Parse EXIF rational number
     */
    protected function parseRational($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        if (is_string($value) && strpos($value, '/') !== false) {
            list($num, $den) = explode('/', $value);
            if ($den != 0) {
                return (float) $num / (float) $den;
            }
        }
        
        return null;
    }
    
    /**
     * Consolidate image metadata from all sources
     */
    protected function consolidateImageMetadata()
    {
        $consolidated = [];
        
        $exif = $this->metadata['exif'] ?? [];
        $iptc = $this->metadata['iptc'] ?? [];
        $xmp = $this->metadata['xmp'] ?? [];
        
        // Title (priority: XMP > IPTC > EXIF)
        $consolidated['title'] = $xmp['title'] 
            ?? $iptc['object_name'] 
            ?? $iptc['headline']
            ?? $exif['ImageDescription'] 
            ?? null;
        
        // Description
        $consolidated['description'] = $xmp['description'] 
            ?? $iptc['caption'] 
            ?? null;
        
        // Creator/Artist
        $creators = [];
        if (!empty($xmp['creator'])) {
            $creators = array_merge($creators, (array) $xmp['creator']);
        }
        if (!empty($iptc['byline'])) {
            $creators = array_merge($creators, (array) $iptc['byline']);
        }
        if (!empty($exif['Artist'])) {
            $creators[] = $exif['Artist'];
        }
        $consolidated['creators'] = array_unique(array_filter($creators));
        
        // Keywords
        $keywords = [];
        if (!empty($xmp['keywords'])) {
            $keywords = array_merge($keywords, (array) $xmp['keywords']);
        }
        if (!empty($iptc['keywords'])) {
            $keywords = array_merge($keywords, (array) $iptc['keywords']);
        }
        $consolidated['keywords'] = array_unique(array_filter($keywords));
        
        // Copyright
        $consolidated['copyright'] = $xmp['rights'] 
            ?? $iptc['copyright'] 
            ?? $exif['Copyright'] 
            ?? null;
        
        // Date created
        $consolidated['date_created'] = $exif['DateTimeOriginal'] 
            ?? $xmp['date_time_original']
            ?? $xmp['create_date']
            ?? $iptc['date_created']
            ?? null;
        
        // Location
        $consolidated['location'] = [
            'city' => $xmp['city'] ?? $iptc['city'] ?? null,
            'state' => $xmp['state'] ?? $iptc['province_state'] ?? null,
            'country' => $xmp['country'] ?? $iptc['country'] ?? null,
        ];
        
        // Camera info
        $consolidated['camera'] = [
            'make' => $exif['Make'] ?? null,
            'model' => $exif['Model'] ?? null,
            'software' => $exif['Software'] ?? $xmp['creator_tool'] ?? null,
        ];
        
        // Technical
        $consolidated['technical'] = [
            'exposure_time' => $exif['ExposureTime'] ?? null,
            'f_number' => $exif['FNumber'] ?? null,
            'iso' => $exif['ISOSpeedRatings'] ?? null,
            'focal_length' => $exif['FocalLength'] ?? null,
            'flash' => $exif['Flash'] ?? null,
        ];
        
        return $consolidated;
    }
    
    // =========================================================================
    // PDF METADATA EXTRACTION
    // =========================================================================
    
    /**
     * Extract PDF metadata
     */
    protected function extractPdfMetadata()
    {
        $this->metadata['pdf'] = [];
        
        // Try multiple extraction methods
        $methods = [
            'extractPdfMetadataWithPdfParser',
            'extractPdfMetadataWithSmalot',
            'extractPdfMetadataManual',
        ];
        
        foreach ($methods as $method) {
            $result = $this->$method();
            if ($result) {
                $this->metadata['pdf'] = $result;
                break;
            }
        }
        
        // Get page count and other info
        $this->metadata['pdf']['page_count'] = $this->getPdfPageCount();
    }
    
    /**
     * Extract PDF metadata using TCPDF Parser (if available)
     */
    protected function extractPdfMetadataWithPdfParser()
    {
        if (!class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi')) {
            return null;
        }
        
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pageCount = $pdf->setSourceFile($this->filePath);
            
            return [
                'page_count' => $pageCount,
            ];
        } catch (Exception $e) {
            $this->errors[] = 'PDF Parser error: ' . $e->getMessage();
            return null;
        }
    }
    
    /**
     * Extract PDF metadata using Smalot PDF Parser (if available)
     */
    protected function extractPdfMetadataWithSmalot()
    {
        if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
            return null;
        }
        
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($this->filePath);
            
            $details = $pdf->getDetails();
            $pages = $pdf->getPages();
            
            return [
                'title' => $details['Title'] ?? null,
                'author' => $details['Author'] ?? null,
                'subject' => $details['Subject'] ?? null,
                'keywords' => $details['Keywords'] ?? null,
                'creator' => $details['Creator'] ?? null,
                'producer' => $details['Producer'] ?? null,
                'creation_date' => $details['CreationDate'] ?? null,
                'modification_date' => $details['ModDate'] ?? null,
                'page_count' => count($pages),
                'pdf_version' => $details['PDFVersion'] ?? null,
            ];
        } catch (Exception $e) {
            $this->errors[] = 'Smalot PDF Parser error: ' . $e->getMessage();
            return null;
        }
    }
    
    /**
     * Manual PDF metadata extraction (fallback)
     */
    protected function extractPdfMetadataManual()
    {
        $content = @file_get_contents($this->filePath, false, null, 0, 65536);
        
        if (!$content) {
            return null;
        }
        
        $metadata = [];
        
        // Extract from PDF Info dictionary
        $patterns = [
            'title' => '/\/Title\s*\(([^)]+)\)/',
            'author' => '/\/Author\s*\(([^)]+)\)/',
            'subject' => '/\/Subject\s*\(([^)]+)\)/',
            'keywords' => '/\/Keywords\s*\(([^)]+)\)/',
            'creator' => '/\/Creator\s*\(([^)]+)\)/',
            'producer' => '/\/Producer\s*\(([^)]+)\)/',
            'creation_date' => '/\/CreationDate\s*\(([^)]+)\)/',
            'modification_date' => '/\/ModDate\s*\(([^)]+)\)/',
        ];
        
        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $value = $this->decodePdfString($matches[1]);
                if ($value) {
                    $metadata[$field] = $value;
                }
            }
        }
        
        // Try hex-encoded strings
        $hexPatterns = [
            'title' => '/\/Title\s*<([0-9A-Fa-f]+)>/',
            'author' => '/\/Author\s*<([0-9A-Fa-f]+)>/',
        ];
        
        foreach ($hexPatterns as $field => $pattern) {
            if (!isset($metadata[$field]) && preg_match($pattern, $content, $matches)) {
                $value = $this->decodeHexString($matches[1]);
                if ($value) {
                    $metadata[$field] = $value;
                }
            }
        }
        
        // Check for XMP metadata in PDF
        if (preg_match('/<x:xmpmeta.*?<\/x:xmpmeta>/s', $content, $matches)) {
            $xmp = $this->parseXmpXml($matches[0]);
            if ($xmp) {
                $metadata['xmp'] = $xmp;
                
                // Override with XMP values if available
                if (!empty($xmp['title'])) $metadata['title'] = $xmp['title'];
                if (!empty($xmp['description'])) $metadata['description'] = $xmp['description'];
                if (!empty($xmp['creator'])) $metadata['author'] = is_array($xmp['creator']) ? implode(', ', $xmp['creator']) : $xmp['creator'];
                if (!empty($xmp['keywords'])) $metadata['keywords'] = is_array($xmp['keywords']) ? implode(', ', $xmp['keywords']) : $xmp['keywords'];
            }
        }
        
        return empty($metadata) ? null : $metadata;
    }
    
    /**
     * Get PDF page count
     */
    protected function getPdfPageCount()
    {
        $content = @file_get_contents($this->filePath);
        
        if (!$content) {
            return null;
        }
        
        // Method 1: Count /Page entries
        $count = preg_match_all('/\/Type\s*\/Page[^s]/', $content);
        
        if ($count > 0) {
            return $count;
        }
        
        // Method 2: Look for /Count in Pages object
        if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Decode PDF string
     */
    protected function decodePdfString($str)
    {
        // Handle UTF-16BE BOM
        if (substr($str, 0, 2) === "\xFE\xFF") {
            return mb_convert_encoding(substr($str, 2), 'UTF-8', 'UTF-16BE');
        }
        
        // Handle escape sequences
        $str = preg_replace_callback('/\\\\([0-7]{3})/', function($m) {
            return chr(octdec($m[1]));
        }, $str);
        
        $str = str_replace(['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'], ["\n", "\r", "\t", "\\", "(", ")"], $str);
        
        return $this->cleanString($str);
    }
    
    /**
     * Decode hex string
     */
    protected function decodeHexString($hex)
    {
        $str = pack('H*', $hex);
        
        // Check for UTF-16BE BOM
        if (substr($str, 0, 2) === "\xFE\xFF") {
            return mb_convert_encoding(substr($str, 2), 'UTF-8', 'UTF-16BE');
        }
        
        return $this->cleanString($str);
    }
    
    // =========================================================================
    // OFFICE DOCUMENT METADATA EXTRACTION (DOCX, XLSX, PPTX)
    // =========================================================================
    
    /**
     * Extract Office document metadata
     */
    protected function extractOfficeMetadata()
    {
        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
            $this->metadata['office'] = $this->extractOpenXmlMetadata();
        } elseif (in_array($ext, ['doc', 'xls', 'ppt'])) {
            $this->metadata['office'] = $this->extractOle2Metadata();
        }
    }
    
    /**
     * Extract Open XML (DOCX, XLSX, PPTX) metadata
     */
    protected function extractOpenXmlMetadata()
    {
        $metadata = [];
        
        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            $this->errors[] = 'Failed to open Office document as ZIP';
            return null;
        }
        
        // Core properties (docProps/core.xml)
        $coreXml = $zip->getFromName('docProps/core.xml');
        if ($coreXml) {
            $metadata = array_merge($metadata, $this->parseOoxmlCore($coreXml));
        }
        
        // App properties (docProps/app.xml)
        $appXml = $zip->getFromName('docProps/app.xml');
        if ($appXml) {
            $metadata = array_merge($metadata, $this->parseOoxmlApp($appXml));
        }
        
        // Custom properties (docProps/custom.xml)
        $customXml = $zip->getFromName('docProps/custom.xml');
        if ($customXml) {
            $metadata['custom'] = $this->parseOoxmlCustom($customXml);
        }
        
        $zip->close();
        
        return $metadata;
    }
    
    /**
     * Parse core.xml from Open XML
     */
    protected function parseOoxmlCore($xml)
    {
        $metadata = [];
        
        // Suppress XML errors
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        
        if (!$doc) {
            return $metadata;
        }
        
        // Register namespaces
        $namespaces = $doc->getNamespaces(true);
        
        // Dublin Core namespace
        if (isset($namespaces['dc'])) {
            $dc = $doc->children($namespaces['dc']);
            
            if (isset($dc->title)) $metadata['title'] = (string) $dc->title;
            if (isset($dc->creator)) $metadata['creator'] = (string) $dc->creator;
            if (isset($dc->subject)) $metadata['subject'] = (string) $dc->subject;
            if (isset($dc->description)) $metadata['description'] = (string) $dc->description;
        }
        
        // Core Properties namespace
        if (isset($namespaces['cp'])) {
            $cp = $doc->children($namespaces['cp']);
            
            if (isset($cp->category)) $metadata['category'] = (string) $cp->category;
            if (isset($cp->keywords)) $metadata['keywords'] = (string) $cp->keywords;
            if (isset($cp->lastModifiedBy)) $metadata['last_modified_by'] = (string) $cp->lastModifiedBy;
            if (isset($cp->revision)) $metadata['revision'] = (string) $cp->revision;
        }
        
        // Date properties (dcterms namespace)
        if (isset($namespaces['dcterms'])) {
            $dcterms = $doc->children($namespaces['dcterms']);
            
            if (isset($dcterms->created)) $metadata['created'] = (string) $dcterms->created;
            if (isset($dcterms->modified)) $metadata['modified'] = (string) $dcterms->modified;
        }
        
        return $metadata;
    }
    
    /**
     * Parse app.xml from Open XML
     */
    protected function parseOoxmlApp($xml)
    {
        $metadata = [];
        
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        
        if (!$doc) {
            return $metadata;
        }
        
        $props = [
            'Application' => 'application',
            'AppVersion' => 'app_version',
            'Company' => 'company',
            'Manager' => 'manager',
            'Template' => 'template',
            'TotalTime' => 'total_editing_time',
            'Pages' => 'pages',
            'Words' => 'words',
            'Characters' => 'characters',
            'Lines' => 'lines',
            'Paragraphs' => 'paragraphs',
            'Slides' => 'slides',
            'Notes' => 'notes',
            'HiddenSlides' => 'hidden_slides',
        ];
        
        foreach ($props as $xmlProp => $metaProp) {
            if (isset($doc->$xmlProp)) {
                $value = (string) $doc->$xmlProp;
                if ($value !== '') {
                    $metadata[$metaProp] = $value;
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Parse custom.xml from Open XML
     */
    protected function parseOoxmlCustom($xml)
    {
        $custom = [];
        
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        
        if (!$doc) {
            return $custom;
        }
        
        foreach ($doc->children() as $prop) {
            $name = (string) $prop['name'];
            $value = '';
            
            // Get the value from the first child element
            foreach ($prop->children() as $valueNode) {
                $value = (string) $valueNode;
                break;
            }
            
            if ($name && $value !== '') {
                $custom[$name] = $value;
            }
        }
        
        return $custom;
    }
    
    /**
     * Extract OLE2 (DOC, XLS, PPT) metadata
     */
    protected function extractOle2Metadata()
    {
        // OLE2 parsing is complex - use external tools if available
        
        // Try using COM on Windows
        if (class_exists('COM') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return $this->extractOle2WithCom();
        }
        
        // Try manual OLE2 parsing
        return $this->extractOle2Manual();
    }
    
    /**
     * Extract OLE2 metadata using COM (Windows only)
     */
    protected function extractOle2WithCom()
    {
        // Implementation for Windows COM automation
        return null;
    }
    
    /**
     * Manual OLE2 metadata extraction (basic)
     */
    protected function extractOle2Manual()
    {
        // Basic OLE2 header parsing
        $content = @file_get_contents($this->filePath, false, null, 0, 8192);
        
        if (!$content) {
            return null;
        }
        
        // Check OLE2 signature
        if (substr($content, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return null;
        }
        
        // OLE2 parsing is complex - return basic info
        return [
            'format' => 'OLE2 (Legacy Office)',
            'note' => 'Full metadata extraction requires conversion to Open XML format',
        ];
    }
    
    // =========================================================================
    // VIDEO METADATA EXTRACTION
    // =========================================================================
    
    /**
     * Extract video metadata
     */
    protected function extractVideoMetadata()
    {
        $metadata = [];
        
        // Try FFprobe first (most reliable)
        $ffprobe = $this->extractWithFfprobe();
        if ($ffprobe) {
            $metadata = $ffprobe;
        }
        
        // Try getID3 library
        if (empty($metadata)) {
            $getId3 = $this->extractWithGetId3();
            if ($getId3) {
                $metadata = $getId3;
            }
        }
        
        // Try manual extraction
        if (empty($metadata)) {
            $manual = $this->extractVideoManual();
            if ($manual) {
                $metadata = $manual;
            }
        }
        
        $this->metadata['video'] = $metadata;
    }
    
    /**
     * Extract metadata using FFprobe
     */
    protected function extractWithFfprobe()
    {
        // Check if ffprobe is available
        $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null'));
        
        if (!$ffprobe) {
            // Try common paths
            $paths = ['/usr/bin/ffprobe', '/usr/local/bin/ffprobe', 'ffprobe'];
            foreach ($paths as $path) {
                if (file_exists($path) || ($path === 'ffprobe' && !empty(shell_exec('ffprobe -version 2>/dev/null')))) {
                    $ffprobe = $path;
                    break;
                }
            }
        }
        
        if (!$ffprobe) {
            return null;
        }
        
        $escapedPath = escapeshellarg($this->filePath);
        $cmd = "$ffprobe -v quiet -print_format json -show_format -show_streams $escapedPath 2>/dev/null";
        
        $output = shell_exec($cmd);
        
        if (!$output) {
            return null;
        }
        
        $data = json_decode($output, true);
        
        if (!$data) {
            return null;
        }
        
        $metadata = [];
        
        // Format info
        if (isset($data['format'])) {
            $format = $data['format'];
            
            $metadata['format'] = $format['format_long_name'] ?? $format['format_name'] ?? null;
            $metadata['duration'] = isset($format['duration']) ? (float) $format['duration'] : null;
            $metadata['duration_formatted'] = $metadata['duration'] ? $this->formatDuration($metadata['duration']) : null;
            $metadata['bitrate'] = isset($format['bit_rate']) ? (int) $format['bit_rate'] : null;
            $metadata['size'] = isset($format['size']) ? (int) $format['size'] : null;
            
            // Tags/metadata
            if (isset($format['tags'])) {
                $metadata['title'] = $format['tags']['title'] ?? null;
                $metadata['artist'] = $format['tags']['artist'] ?? null;
                $metadata['album'] = $format['tags']['album'] ?? null;
                $metadata['date'] = $format['tags']['date'] ?? $format['tags']['creation_time'] ?? null;
                $metadata['comment'] = $format['tags']['comment'] ?? null;
                $metadata['encoder'] = $format['tags']['encoder'] ?? null;
            }
        }
        
        // Stream info
        if (isset($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                if ($stream['codec_type'] === 'video' && !isset($metadata['video_codec'])) {
                    $metadata['video_codec'] = $stream['codec_name'] ?? null;
                    $metadata['video_codec_long'] = $stream['codec_long_name'] ?? null;
                    $metadata['width'] = $stream['width'] ?? null;
                    $metadata['height'] = $stream['height'] ?? null;
                    $metadata['resolution'] = ($metadata['width'] && $metadata['height']) 
                        ? $metadata['width'] . 'x' . $metadata['height'] 
                        : null;
                    
                    // Frame rate
                    if (isset($stream['r_frame_rate'])) {
                        $parts = explode('/', $stream['r_frame_rate']);
                        if (count($parts) === 2 && $parts[1] != 0) {
                            $metadata['frame_rate'] = round($parts[0] / $parts[1], 2);
                        }
                    }
                    
                    $metadata['pixel_format'] = $stream['pix_fmt'] ?? null;
                    $metadata['aspect_ratio'] = $stream['display_aspect_ratio'] ?? null;
                }
                
                if ($stream['codec_type'] === 'audio' && !isset($metadata['audio_codec'])) {
                    $metadata['audio_codec'] = $stream['codec_name'] ?? null;
                    $metadata['audio_codec_long'] = $stream['codec_long_name'] ?? null;
                    $metadata['audio_channels'] = $stream['channels'] ?? null;
                    $metadata['audio_sample_rate'] = $stream['sample_rate'] ?? null;
                    $metadata['audio_bitrate'] = $stream['bit_rate'] ?? null;
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Format duration in HH:MM:SS
     */
    protected function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        
        return sprintf('%02d:%02d', $minutes, $secs);
    }
    
    /**
     * Extract metadata using getID3 library
     */
    protected function extractWithGetId3()
    {
        if (!class_exists('getID3')) {
            // Try to load getID3
            $getId3Paths = [
                sfConfig::get('sf_lib_dir') . '/vendor/james-heinrich/getid3/getid3/getid3.php',
                sfConfig::get('sf_root_dir') . '/vendor/james-heinrich/getid3/getid3/getid3.php',
            ];
            
            foreach ($getId3Paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }
        
        if (!class_exists('getID3')) {
            return null;
        }
        
        try {
            $getId3 = new getID3();
            $info = $getId3->analyze($this->filePath);
            
            if (!$info) {
                return null;
            }
            
            $metadata = [];
            
            // Duration
            if (isset($info['playtime_seconds'])) {
                $metadata['duration'] = $info['playtime_seconds'];
                $metadata['duration_formatted'] = $info['playtime_string'] ?? $this->formatDuration($info['playtime_seconds']);
            }
            
            // Video info
            if (isset($info['video'])) {
                $video = $info['video'];
                $metadata['video_codec'] = $video['codec'] ?? null;
                $metadata['width'] = $video['resolution_x'] ?? null;
                $metadata['height'] = $video['resolution_y'] ?? null;
                $metadata['frame_rate'] = $video['frame_rate'] ?? null;
            }
            
            // Audio info
            if (isset($info['audio'])) {
                $audio = $info['audio'];
                $metadata['audio_codec'] = $audio['codec'] ?? null;
                $metadata['audio_channels'] = $audio['channels'] ?? null;
                $metadata['audio_sample_rate'] = $audio['sample_rate'] ?? null;
                $metadata['audio_bitrate'] = $audio['bitrate'] ?? null;
            }
            
            // Tags
            if (isset($info['tags'])) {
                $tags = array_shift($info['tags']) ?: [];
                $metadata['title'] = $tags['title'][0] ?? null;
                $metadata['artist'] = $tags['artist'][0] ?? null;
                $metadata['album'] = $tags['album'][0] ?? null;
            }
            
            return $metadata;
            
        } catch (Exception $e) {
            $this->errors[] = 'getID3 error: ' . $e->getMessage();
            return null;
        }
    }
    
    /**
     * Manual video metadata extraction (basic)
     */
    protected function extractVideoManual()
    {
        // Read first portion of file to detect format
        $header = @file_get_contents($this->filePath, false, null, 0, 4096);
        
        if (!$header) {
            return null;
        }
        
        $metadata = [];
        
        // Detect MP4/MOV
        if (strpos($header, 'ftyp') !== false || strpos($header, 'moov') !== false) {
            $metadata['format'] = 'MP4/MOV container';
        }
        
        // Detect WebM/MKV
        if (substr($header, 0, 4) === "\x1A\x45\xDF\xA3") {
            $metadata['format'] = 'WebM/Matroska container';
        }
        
        // Detect AVI
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'AVI ') {
            $metadata['format'] = 'AVI container';
        }
        
        return empty($metadata) ? null : $metadata;
    }
    
    // =========================================================================
    // AUDIO METADATA EXTRACTION (ID3 tags)
    // =========================================================================
    
    /**
     * Extract audio metadata
     */
    protected function extractAudioMetadata()
    {
        $metadata = [];
        
        // Try FFprobe first
        $ffprobe = $this->extractWithFfprobe();
        if ($ffprobe) {
            $metadata = $ffprobe;
        }
        
        // Try ID3 extraction for MP3
        if (empty($metadata) || strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION)) === 'mp3') {
            $id3 = $this->extractId3Tags();
            if ($id3) {
                $metadata = array_merge($metadata, $id3);
            }
        }
        
        // Try getID3 library
        if (empty($metadata)) {
            $getId3 = $this->extractWithGetId3();
            if ($getId3) {
                $metadata = $getId3;
            }
        }
        
        $this->metadata['audio'] = $metadata;
    }
    
    /**
     * Extract ID3 tags from MP3
     */
    protected function extractId3Tags()
    {
        $handle = @fopen($this->filePath, 'rb');
        
        if (!$handle) {
            return null;
        }
        
        $metadata = [];
        
        // Check for ID3v2 header
        $header = fread($handle, 10);
        
        if (substr($header, 0, 3) === 'ID3') {
            $version = ord($header[3]) . '.' . ord($header[4]);
            $metadata['id3_version'] = 'ID3v2.' . $version;
            
            // Parse ID3v2 size
            $size = (ord($header[6]) << 21) | (ord($header[7]) << 14) | (ord($header[8]) << 7) | ord($header[9]);
            
            // Read ID3v2 frames
            $id3Data = fread($handle, $size);
            $metadata = array_merge($metadata, $this->parseId3v2Frames($id3Data));
        }
        
        // Check for ID3v1 at end of file
        fseek($handle, -128, SEEK_END);
        $id3v1 = fread($handle, 128);
        
        if (substr($id3v1, 0, 3) === 'TAG') {
            if (empty($metadata['title'])) {
                $metadata['title'] = $this->cleanString(trim(substr($id3v1, 3, 30)));
            }
            if (empty($metadata['artist'])) {
                $metadata['artist'] = $this->cleanString(trim(substr($id3v1, 33, 30)));
            }
            if (empty($metadata['album'])) {
                $metadata['album'] = $this->cleanString(trim(substr($id3v1, 63, 30)));
            }
            if (empty($metadata['year'])) {
                $metadata['year'] = $this->cleanString(trim(substr($id3v1, 93, 4)));
            }
            if (empty($metadata['comment'])) {
                $metadata['comment'] = $this->cleanString(trim(substr($id3v1, 97, 28)));
            }
            
            // Check for track number (ID3v1.1)
            if (ord($id3v1[125]) === 0 && ord($id3v1[126]) !== 0) {
                $metadata['track'] = ord($id3v1[126]);
            }
            
            // Genre
            $genreId = ord($id3v1[127]);
            $metadata['genre_id'] = $genreId;
            $metadata['genre'] = $this->getId3Genre($genreId);
        }
        
        fclose($handle);
        
        return empty($metadata) ? null : $metadata;
    }
    
    /**
     * Parse ID3v2 frames
     */
    protected function parseId3v2Frames($data)
    {
        $metadata = [];
        $pos = 0;
        $length = strlen($data);
        
        $frameMap = [
            'TIT2' => 'title',
            'TPE1' => 'artist',
            'TALB' => 'album',
            'TYER' => 'year',
            'TDRC' => 'year',
            'TRCK' => 'track',
            'TCON' => 'genre',
            'COMM' => 'comment',
            'TCOM' => 'composer',
            'TPUB' => 'publisher',
            'TCOP' => 'copyright',
            'TENC' => 'encoded_by',
            'TBPM' => 'bpm',
            'TKEY' => 'key',
            'TLAN' => 'language',
            'TLEN' => 'length',
        ];
        
        while ($pos < $length - 10) {
            $frameId = substr($data, $pos, 4);
            
            // Check for valid frame ID
            if (!preg_match('/^[A-Z0-9]{4}$/', $frameId)) {
                break;
            }
            
            // Frame size (synchsafe integer for ID3v2.4, normal for earlier)
            $frameSize = (ord($data[$pos + 4]) << 24) | (ord($data[$pos + 5]) << 16) | 
                        (ord($data[$pos + 6]) << 8) | ord($data[$pos + 7]);
            
            if ($frameSize <= 0 || $pos + 10 + $frameSize > $length) {
                break;
            }
            
            // Frame data (skip 2 bytes of flags)
            $frameData = substr($data, $pos + 10, $frameSize);
            
            // Handle text frames
            if (isset($frameMap[$frameId])) {
                $encoding = ord($frameData[0]);
                $text = substr($frameData, 1);
                
                // Handle encoding
                if ($encoding === 1 || $encoding === 2) {
                    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16');
                } elseif ($encoding === 3) {
                    // Already UTF-8
                }
                
                $text = trim($text, "\x00");
                
                if ($text !== '') {
                    $metadata[$frameMap[$frameId]] = $text;
                }
            }
            
            $pos += 10 + $frameSize;
        }
        
        return $metadata;
    }
    
    /**
     * Get ID3 genre name from ID
     */
    protected function getId3Genre($id)
    {
        $genres = [
            0 => 'Blues', 1 => 'Classic Rock', 2 => 'Country', 3 => 'Dance',
            4 => 'Disco', 5 => 'Funk', 6 => 'Grunge', 7 => 'Hip-Hop',
            8 => 'Jazz', 9 => 'Metal', 10 => 'New Age', 11 => 'Oldies',
            12 => 'Other', 13 => 'Pop', 14 => 'R&B', 15 => 'Rap',
            16 => 'Reggae', 17 => 'Rock', 18 => 'Techno', 19 => 'Industrial',
            20 => 'Alternative', 21 => 'Ska', 22 => 'Death Metal', 23 => 'Pranks',
            24 => 'Soundtrack', 25 => 'Euro-Techno', 26 => 'Ambient', 27 => 'Trip-Hop',
            28 => 'Vocal', 29 => 'Jazz+Funk', 30 => 'Fusion', 31 => 'Trance',
            32 => 'Classical', 33 => 'Instrumental', 34 => 'Acid', 35 => 'House',
            36 => 'Game', 37 => 'Sound Clip', 38 => 'Gospel', 39 => 'Noise',
            40 => 'AlternRock', 41 => 'Bass', 42 => 'Soul', 43 => 'Punk',
            44 => 'Space', 45 => 'Meditative', 46 => 'Instrumental Pop', 47 => 'Instrumental Rock',
            48 => 'Ethnic', 49 => 'Gothic', 50 => 'Darkwave', 51 => 'Techno-Industrial',
            52 => 'Electronic', 53 => 'Pop-Folk', 54 => 'Eurodance', 55 => 'Dream',
            56 => 'Southern Rock', 57 => 'Comedy', 58 => 'Cult', 59 => 'Gangsta',
            60 => 'Top 40', 61 => 'Christian Rap', 62 => 'Pop/Funk', 63 => 'Jungle',
            64 => 'Native American', 65 => 'Cabaret', 66 => 'New Wave', 67 => 'Psychadelic',
            68 => 'Rave', 69 => 'Showtunes', 70 => 'Trailer', 71 => 'Lo-Fi',
            72 => 'Tribal', 73 => 'Acid Punk', 74 => 'Acid Jazz', 75 => 'Polka',
            76 => 'Retro', 77 => 'Musical', 78 => 'Rock & Roll', 79 => 'Hard Rock',
        ];
        
        return $genres[$id] ?? 'Unknown';
    }
    
    // =========================================================================
    // UTILITY METHODS
    // =========================================================================
    
    /**
     * Clean string for output
     */
    protected function cleanString($str)
    {
        if (!is_string($str)) {
            return $str;
        }
        
        // Remove null bytes
        $str = str_replace("\x00", '', $str);
        
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }
        
        return trim($str);
    }
    
    /**
     * Format metadata for display
     */
    public function formatSummary()
    {
        $lines = [];
        
        if (empty($this->metadata)) {
            return 'No metadata extracted';
        }
        
        // File info
        if (isset($this->metadata['file'])) {
            $f = $this->metadata['file'];
            $lines[] = "=== FILE INFO ===";
            $lines[] = "Name: " . $f['name'];
            $lines[] = "Size: " . AhgCentralHelpers::formatBytes($f['size']);
            $lines[] = "Type: " . $f['mime_type'];
        }
        
        // Type-specific summary
        switch ($this->fileType) {
            case self::TYPE_IMAGE:
                $lines = array_merge($lines, $this->formatImageSummary());
                break;
            case self::TYPE_PDF:
                $lines = array_merge($lines, $this->formatPdfSummary());
                break;
            case self::TYPE_OFFICE:
                $lines = array_merge($lines, $this->formatOfficeSummary());
                break;
            case self::TYPE_VIDEO:
                $lines = array_merge($lines, $this->formatVideoSummary());
                break;
            case self::TYPE_AUDIO:
                $lines = array_merge($lines, $this->formatAudioSummary());
                break;
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Format image metadata summary
     */
    protected function formatImageSummary()
    {
        $lines = [];
        
        if (isset($this->metadata['image'])) {
            $img = $this->metadata['image'];
            $lines[] = "\n=== IMAGE ===";
            $lines[] = "Dimensions: {$img['width']} x {$img['height']} pixels";
        }
        
        if (isset($this->metadata['consolidated'])) {
            $c = $this->metadata['consolidated'];
            
            if (!empty($c['title'])) $lines[] = "Title: {$c['title']}";
            if (!empty($c['description'])) $lines[] = "Description: {$c['description']}";
            if (!empty($c['creators'])) $lines[] = "Creator: " . implode(', ', $c['creators']);
            if (!empty($c['keywords'])) $lines[] = "Keywords: " . implode(', ', $c['keywords']);
            if (!empty($c['copyright'])) $lines[] = "Copyright: {$c['copyright']}";
            if (!empty($c['date_created'])) $lines[] = "Date: {$c['date_created']}";
            
            if (!empty($c['camera']['make']) || !empty($c['camera']['model'])) {
                $camera = trim(($c['camera']['make'] ?? '') . ' ' . ($c['camera']['model'] ?? ''));
                $lines[] = "Camera: $camera";
            }
        }
        
        if (isset($this->metadata['gps'])) {
            $gps = $this->metadata['gps'];
            $lines[] = "\n=== GPS ===";
            $lines[] = "Coordinates: {$gps['decimal']}";
            if (isset($gps['altitude'])) {
                $lines[] = "Altitude: {$gps['altitude']}m";
            }
        }
        
        return $lines;
    }
    
    /**
     * Format PDF metadata summary
     */
    protected function formatPdfSummary()
    {
        $lines = [];
        
        if (isset($this->metadata['pdf'])) {
            $pdf = $this->metadata['pdf'];
            $lines[] = "\n=== PDF ===";
            
            if (!empty($pdf['title'])) $lines[] = "Title: {$pdf['title']}";
            if (!empty($pdf['author'])) $lines[] = "Author: {$pdf['author']}";
            if (!empty($pdf['subject'])) $lines[] = "Subject: {$pdf['subject']}";
            if (!empty($pdf['keywords'])) $lines[] = "Keywords: {$pdf['keywords']}";
            if (!empty($pdf['creator'])) $lines[] = "Creator: {$pdf['creator']}";
            if (!empty($pdf['producer'])) $lines[] = "Producer: {$pdf['producer']}";
            if (!empty($pdf['page_count'])) $lines[] = "Pages: {$pdf['page_count']}";
            if (!empty($pdf['creation_date'])) $lines[] = "Created: {$pdf['creation_date']}";
        }
        
        return $lines;
    }
    
    /**
     * Format Office document metadata summary
     */
    protected function formatOfficeSummary()
    {
        $lines = [];
        
        if (isset($this->metadata['office'])) {
            $office = $this->metadata['office'];
            $lines[] = "\n=== DOCUMENT ===";
            
            if (!empty($office['title'])) $lines[] = "Title: {$office['title']}";
            if (!empty($office['creator'])) $lines[] = "Author: {$office['creator']}";
            if (!empty($office['subject'])) $lines[] = "Subject: {$office['subject']}";
            if (!empty($office['description'])) $lines[] = "Description: {$office['description']}";
            if (!empty($office['keywords'])) $lines[] = "Keywords: {$office['keywords']}";
            if (!empty($office['category'])) $lines[] = "Category: {$office['category']}";
            if (!empty($office['company'])) $lines[] = "Company: {$office['company']}";
            if (!empty($office['application'])) $lines[] = "Application: {$office['application']}";
            if (!empty($office['pages'])) $lines[] = "Pages: {$office['pages']}";
            if (!empty($office['words'])) $lines[] = "Words: {$office['words']}";
            if (!empty($office['slides'])) $lines[] = "Slides: {$office['slides']}";
            if (!empty($office['created'])) $lines[] = "Created: {$office['created']}";
            if (!empty($office['modified'])) $lines[] = "Modified: {$office['modified']}";
            if (!empty($office['last_modified_by'])) $lines[] = "Last Modified By: {$office['last_modified_by']}";
        }
        
        return $lines;
    }
    
    /**
     * Format video metadata summary
     */
    protected function formatVideoSummary()
    {
        $lines = [];
        
        if (isset($this->metadata['video'])) {
            $video = $this->metadata['video'];
            $lines[] = "\n=== VIDEO ===";
            
            if (!empty($video['title'])) $lines[] = "Title: {$video['title']}";
            if (!empty($video['duration_formatted'])) $lines[] = "Duration: {$video['duration_formatted']}";
            if (!empty($video['resolution'])) $lines[] = "Resolution: {$video['resolution']}";
            if (!empty($video['video_codec'])) $lines[] = "Video Codec: {$video['video_codec']}";
            if (!empty($video['audio_codec'])) $lines[] = "Audio Codec: {$video['audio_codec']}";
            if (!empty($video['frame_rate'])) $lines[] = "Frame Rate: {$video['frame_rate']} fps";
            if (!empty($video['bitrate'])) $lines[] = "Bitrate: " . $this->formatBitrate($video['bitrate']);
            if (!empty($video['format'])) $lines[] = "Format: {$video['format']}";
        }
        
        return $lines;
    }
    
    /**
     * Format audio metadata summary
     */
    protected function formatAudioSummary()
    {
        $lines = [];
        
        if (isset($this->metadata['audio'])) {
            $audio = $this->metadata['audio'];
            $lines[] = "\n=== AUDIO ===";
            
            if (!empty($audio['title'])) $lines[] = "Title: {$audio['title']}";
            if (!empty($audio['artist'])) $lines[] = "Artist: {$audio['artist']}";
            if (!empty($audio['album'])) $lines[] = "Album: {$audio['album']}";
            if (!empty($audio['year'])) $lines[] = "Year: {$audio['year']}";
            if (!empty($audio['genre'])) $lines[] = "Genre: {$audio['genre']}";
            if (!empty($audio['track'])) $lines[] = "Track: {$audio['track']}";
            if (!empty($audio['duration_formatted'])) $lines[] = "Duration: {$audio['duration_formatted']}";
            if (!empty($audio['audio_codec'])) $lines[] = "Codec: {$audio['audio_codec']}";
            if (!empty($audio['audio_bitrate'])) $lines[] = "Bitrate: " . $this->formatBitrate($audio['audio_bitrate']);
            if (!empty($audio['audio_sample_rate'])) $lines[] = "Sample Rate: {$audio['audio_sample_rate']} Hz";
            if (!empty($audio['audio_channels'])) $lines[] = "Channels: {$audio['audio_channels']}";
        }
        
        return $lines;
    }
    /**
     * Format bitrate to human readable
     */
    protected function formatBitrate($bps)
    {
        if ($bps >= 1000000) {
            return round($bps / 1000000, 2) . ' Mbps';
        }
        return round($bps / 1000) . ' kbps';
    }
    
    /**
     * Get key metadata for AtoM field mapping
     */
    public function getKeyFields()
    {
        $key = [
            'title' => null,
            'creator' => null,
            'date' => null,
            'description' => null,
            'keywords' => [],
            'copyright' => null,
        ];
        
        switch ($this->fileType) {
            case self::TYPE_IMAGE:
                $c = $this->metadata['consolidated'] ?? [];
                $key['title'] = $c['title'] ?? null;
                $key['creator'] = !empty($c['creators']) ? implode('; ', $c['creators']) : null;
                $key['date'] = $c['date_created'] ?? null;
                $key['description'] = $c['description'] ?? null;
                $key['keywords'] = $c['keywords'] ?? [];
                $key['copyright'] = $c['copyright'] ?? null;
                break;
                
            case self::TYPE_PDF:
                $pdf = $this->metadata['pdf'] ?? [];
                $key['title'] = $pdf['title'] ?? null;
                $key['creator'] = $pdf['author'] ?? null;
                $key['date'] = $pdf['creation_date'] ?? null;
                $key['description'] = $pdf['subject'] ?? null;
                $key['keywords'] = !empty($pdf['keywords']) ? array_map('trim', explode(',', $pdf['keywords'])) : [];
                break;
                
            case self::TYPE_OFFICE:
                $office = $this->metadata['office'] ?? [];
                $key['title'] = $office['title'] ?? null;
                $key['creator'] = $office['creator'] ?? null;
                $key['date'] = $office['created'] ?? null;
                $key['description'] = $office['description'] ?? $office['subject'] ?? null;
                $key['keywords'] = !empty($office['keywords']) ? array_map('trim', explode(',', $office['keywords'])) : [];
                break;
                
            case self::TYPE_VIDEO:
            case self::TYPE_AUDIO:
                $media = $this->metadata[$this->fileType] ?? [];
                $key['title'] = $media['title'] ?? null;
                $key['creator'] = $media['artist'] ?? null;
                $key['date'] = $media['date'] ?? $media['year'] ?? null;
                $key['description'] = $media['comment'] ?? null;
                break;
        }
        
        return $key;
    }
}
