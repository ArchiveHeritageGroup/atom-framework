<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * IIIF Manifest Service - Presentation API 3.0
 * 
 * Generates IIIF 3.0 compliant manifests for all media types:
 * - Images (single and multi-page)
 * - PDFs (converted to image sequences)
 * - 3D Models (IIIF 3D extension)
 * - Audio/Video (AV extension)
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 3.0.0
 */
class IiifManifestService
{
    private Logger $logger;
    private string $baseUrl;
    private string $cantaloupeUrl;
    private string $defaultLanguage = 'en';
    private array $config;
    
    // IIIF Contexts
    public const CONTEXT_PRESENTATION_3 = 'http://iiif.io/api/presentation/3/context.json';
    public const CONTEXT_PRESENTATION_2 = 'http://iiif.io/api/presentation/2/context.json';
    public const CONTEXT_IMAGE_3 = 'http://iiif.io/api/image/3/context.json';
    public const CONTEXT_ANNOTATION = 'http://www.w3.org/ns/anno.jsonld';
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->logger = new Logger('iiif-manifest');
        $logPath = $this->config['log_path'] ?? '/var/log/atom/iiif-manifest.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
        
        $this->baseUrl = $this->config['base_url'];
        $this->cantaloupeUrl = $this->config['cantaloupe_url'];
        $this->defaultLanguage = $this->config['default_language'];
    }
    
    /**
     * Get base URL from AtoM settings
     */
    private static function getAtomBaseUrl(): string
    {
        try {
            $result = DB::table("setting")
                ->join("setting_i18n", "setting.id", "=", "setting_i18n.id")
                ->where("setting.name", "siteBaseUrl")
                ->first();
            if ($result && !empty($result->value)) {
                return rtrim($result->value, "/");
            }
        } catch (\Exception $e) {
            error_log("getAtomBaseUrl exception: " . $e->getMessage());
        }
        return "https://psis.theahg.co.za";
    }

    private function getDefaultConfig(): array
    {
        return [
            'base_url' => self::getAtomBaseUrl(),
            'cantaloupe_url' => self::getAtomBaseUrl() . '/iiif/2',
            'default_language' => 'en',
            'log_path' => '/var/log/atom/iiif-manifest.log',
            'attribution' => 'The Archive and Heritage Group',
            'license' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            'logo_url' => null,
            'enable_annotations' => true,
            'enable_ocr' => true,
            'enable_3d' => true,
            'pdf_dpi' => 150,
        ];
    }
    
    // ========================================================================
    // Main Manifest Generation
    // ========================================================================
    
    /**
     * Generate manifest for an AtoM information object
     * Automatically detects content type and generates appropriate manifest
     */
    public function generateObjectManifest(int $objectId, array $options = []): ?array
    {
        $object = $this->getObjectInfo($objectId);
        if (!$object) {
            return null;
        }
        
        $digitalObjects = $this->getDigitalObjects($objectId);
        if (empty($digitalObjects)) {
            return null;
        }
        
        // Check for 3D models
        $models3d = $this->get3DModels($objectId);
        
        // Determine manifest type
        $has3D = !empty($models3d);
        $hasPdf = $this->hasPdfContent($digitalObjects);
        $hasAV = $this->hasAVContent($digitalObjects);
        
        $lang = $options['language'] ?? $this->defaultLanguage;
        $manifestUri = $this->baseUrl . '/iiif/manifest/' . $object->slug;
        
        $manifest = [
            '@context' => [
                self::CONTEXT_PRESENTATION_3,
                self::CONTEXT_ANNOTATION
            ],
            'id' => $manifestUri,
            'type' => 'Manifest',
            'label' => $this->langMap($object->title ?: 'Untitled', $lang),
            'metadata' => $this->buildMetadata($object, $lang),
            'summary' => $object->scope_and_content 
                ? $this->langMap(strip_tags($object->scope_and_content), $lang)
                : null,
            'viewingDirection' => 'left-to-right',
            'behavior' => $this->determineBehavior($digitalObjects, $has3D),
            'rights' => $this->config['license'],
            'requiredStatement' => [
                'label' => $this->langMap('Attribution', $lang),
                'value' => $this->langMap($this->config['attribution'], $lang)
            ],
            'provider' => $this->buildProvider($lang),
            'homepage' => [
                [
                    'id' => $this->baseUrl . '/' . $object->slug,
                    'type' => 'Text',
                    'label' => $this->langMap('View in Archive', $lang),
                    'format' => 'text/html'
                ]
            ],
            'items' => [],
            'structures' => [],
            'annotations' => []
        ];
        
        // Add canvases for each digital object
        $canvasIndex = 1;
        foreach ($digitalObjects as $digitalObject) {
            $canvases = $this->createCanvasesForDigitalObject($digitalObject, $canvasIndex, $lang, $object);
            foreach ($canvases as $canvas) {
                $manifest['items'][] = $canvas;
                $canvasIndex++;
            }
        }
        
        // Add 3D model canvases
        foreach ($models3d as $model) {
            $manifest['items'][] = $this->create3DCanvas($model, $canvasIndex, $lang);
            $canvasIndex++;
        }
        
        // Add annotation pages if enabled
        if ($this->config['enable_annotations']) {
            $manifest['annotations'] = $this->getAnnotationPages($objectId, $manifest['items']);
        }
        
        // Add OCR content if available
        if ($this->config['enable_ocr']) {
            $this->addOcrAnnotations($manifest, $objectId);
        }
        
        // Set thumbnail
        if (!empty($manifest['items'])) {
            $manifest['thumbnail'] = $manifest['items'][0]['thumbnail'] ?? null;
        }
        
        // Clean null values
        $manifest = $this->removeNullValues($manifest);
        
        // Add structures (table of contents)
        $manifest['structures'] = $this->buildStructures($manifest['items'], $object, $lang);
        
        $this->logger->info('Generated object manifest', [
            'object_id' => $objectId,
            'slug' => $object->slug,
            'canvas_count' => count($manifest['items'])
        ]);
        
        return $manifest;
    }
    
    /**
     * Generate manifest for a single image identifier
     */
    public function generateImageManifest(string $identifier, array $options = []): array
    {
        $lang = $options['language'] ?? $this->defaultLanguage;
        $format = $options['format'] ?? '3';
        
        if ($format === '2') {
            return $this->generateManifest21($identifier, $options);
        }
        
        $title = $this->parseTitle($identifier);
        $dimensions = $this->getImageDimensions($identifier);
        $pageCount = $this->detectPageCount($identifier);
        
        $manifestId = $this->baseUrl . '/iiif-manifest.php?id=' . urlencode($identifier);
        
        $manifest = [
            '@context' => self::CONTEXT_PRESENTATION_3,
            'id' => $manifestId,
            'type' => 'Manifest',
            'label' => $this->langMap($title, $lang),
            'metadata' => [
                [
                    'label' => $this->langMap('Source', $lang),
                    'value' => $this->langMap('The Archive and Heritage Group Digital Archives', $lang)
                ],
                [
                    'label' => $this->langMap('Format', $lang),
                    'value' => $this->langMap($this->detectFormat($identifier), $lang)
                ]
            ],
            'thumbnail' => [
                [
                    'id' => $this->cantaloupeUrl . '/' . urlencode($identifier) . '/full/200,/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'width' => 200,
                    'height' => $this->calculateThumbnailHeight($dimensions, 200)
                ]
            ],
            'viewingDirection' => 'left-to-right',
            'behavior' => $pageCount > 1 ? ['paged'] : ['individuals'],
            'rights' => $this->config['license'],
            'requiredStatement' => [
                'label' => $this->langMap('Attribution', $lang),
                'value' => $this->langMap($this->config['attribution'], $lang)
            ],
            'provider' => $this->buildProvider($lang),
            'items' => []
        ];
        
        // Generate canvases
        if ($pageCount > 1) {
            for ($i = 0; $i < $pageCount; $i++) {
                $pageIdentifier = $identifier . '[' . $i . ']';
                $pageDimensions = $this->getImageDimensions($pageIdentifier) ?: $dimensions;
                $manifest['items'][] = $this->createImageCanvas($pageIdentifier, $pageDimensions, $i + 1, $lang);
            }
        } else {
            $manifest['items'][] = $this->createImageCanvas($identifier, $dimensions, 1, $lang);
        }
        
        return $manifest;
    }
    
    /**
     * Generate collection manifest
     */
    public function generateCollectionManifest(int $collectionId, array $options = []): ?array
    {
        $lang = $options['language'] ?? $this->defaultLanguage;
        
        $collection = DB::table('iiif_collection as c')
            ->leftJoin('iiif_collection_i18n as i18n', function($join) use ($lang) {
                $join->on('c.id', '=', 'i18n.collection_id')
                     ->where('i18n.culture', '=', $lang);
            })
            ->where('c.id', $collectionId)
            ->select('c.*', 'i18n.title', 'i18n.description')
            ->first();
        
        if (!$collection) {
            return null;
        }
        
        $collectionUri = $this->baseUrl . '/iiif/collection/' . $collection->slug;
        
        $manifest = [
            '@context' => self::CONTEXT_PRESENTATION_3,
            'id' => $collectionUri,
            'type' => 'Collection',
            'label' => $this->langMap($collection->title ?: 'Untitled Collection', $lang),
            'viewingDirection' => 'left-to-right',
            'behavior' => [$collection->view_type === 'continuous' ? 'continuous' : 'individuals'],
            'requiredStatement' => [
                'label' => $this->langMap('Attribution', $lang),
                'value' => $this->langMap($this->config['attribution'], $lang)
            ],
            'provider' => $this->buildProvider($lang),
            'items' => []
        ];
        
        if ($collection->description) {
            $manifest['summary'] = $this->langMap($collection->description, $lang);
        }
        
        if ($collection->thumbnail_url) {
            $manifest['thumbnail'] = [[
                'id' => $collection->thumbnail_url,
                'type' => 'Image',
                'format' => 'image/jpeg'
            ]];
        }
        
        // Get items
        $items = $this->getCollectionItems($collectionId, $lang);
        
        foreach ($items as $item) {
            $iiifIdentifier = $this->buildIiifIdentifier($item->digital_object_path, $item->digital_object_name);
            $imageUri = $this->cantaloupeUrl . '/' . urlencode($iiifIdentifier);
            
            $manifest['items'][] = [
                'id' => $this->baseUrl . '/iiif/manifest/' . $item->slug,
                'type' => 'Manifest',
                'label' => $this->langMap($item->object_title ?: $item->digital_object_name, $lang),
                'thumbnail' => [[
                    'id' => $imageUri . '/full/200,/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg'
                ]]
            ];
        }
        
        return $manifest;
    }
    
    // ========================================================================
    // Canvas Creation Methods
    // ========================================================================
    
    /**
     * Create canvases for a digital object (handles PDFs, multi-page, etc.)
     */
    private function createCanvasesForDigitalObject(object $do, int $startIndex, string $lang, object $object): array
    {
        $canvases = [];
        $mimeType = $do->mime_type ?? '';
        
        // PDF - create canvas per page
        if (stripos($mimeType, 'pdf') !== false) {
            $pdfCanvases = $this->createPdfCanvases($do, $startIndex, $lang);
            return $pdfCanvases;
        }
        
        // Image
        if (stripos($mimeType, 'image') !== false) {
            $iiifId = $this->buildIiifIdentifier($do->path, $do->name);
            $dimensions = $this->getImageDimensions($iiifId);
            
            // Check for multi-page (TIFF)
            $pageCount = $this->detectPageCount($iiifId);
            
            if ($pageCount > 1) {
                for ($i = 0; $i < $pageCount; $i++) {
                    $pageId = $iiifId . '[' . $i . ']';
                    $pageDimensions = $this->getImageDimensions($pageId) ?: $dimensions;
                    $canvases[] = $this->createImageCanvas($pageId, $pageDimensions, $startIndex + $i, $lang, $do->id);
                }
            } else {
                $canvases[] = $this->createImageCanvas($iiifId, $dimensions, $startIndex, $lang, $do->id);
            }
            
            return $canvases;
        }
        
        // Audio/Video
        if (stripos($mimeType, 'audio') !== false || stripos($mimeType, 'video') !== false) {
            $canvases[] = $this->createAVCanvas($do, $startIndex, $lang);
            return $canvases;
        }
        
        return $canvases;
    }
    
    /**
     * Create a standard image canvas
     */
    private function createImageCanvas(string $identifier, array $dimensions, int $index, string $lang, ?int $doId = null): array
    {
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        
        $canvasId = $this->baseUrl . '/iiif/canvas/' . ($doId ?: urlencode($identifier)) . '/' . $index;
        $imageUri = $this->cantaloupeUrl . '/' . urlencode($identifier);
        
        return [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => $this->langMap('Page ' . $index, $lang),
            'width' => $width,
            'height' => $height,
            'thumbnail' => [[
                'id' => $imageUri . '/full/200,/0/default.jpg',
                'type' => 'Image',
                'format' => 'image/jpeg',
                'width' => 200,
                'height' => $this->calculateThumbnailHeight($dimensions, 200)
            ]],
            'items' => [
                [
                    'id' => $canvasId . '/annotation-page/main',
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $canvasId . '/annotation/image',
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $imageUri . '/full/max/0/default.jpg',
                                'type' => 'Image',
                                'format' => 'image/jpeg',
                                'width' => $width,
                                'height' => $height,
                                'service' => [[
                                    'id' => $imageUri,
                                    'type' => 'ImageService3',
                                    'profile' => 'level2'
                                ]]
                            ],
                            'target' => $canvasId
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Create canvases for PDF pages
     */
    private function createPdfCanvases(object $do, int $startIndex, string $lang): array
    {
        $canvases = [];

        // Get PDF page count and path (may be redacted)
        $pdfInfo = $this->getPdfPathWithRedaction($do);
        $pdfPath = $pdfInfo['path'];
        $pdfIdentifier = $pdfInfo['identifier'];
        $isRedacted = $pdfInfo['is_redacted'];

        $pageCount = $this->getPdfPageCount($pdfPath);

        if ($pageCount === 0) {
            $pageCount = 1; // Fallback
        }
        
        for ($i = 0; $i < $pageCount; $i++) {
            $pageIdentifier = $pdfIdentifier . '[' . $i . ']';
            $dimensions = $this->getImageDimensions($pageIdentifier);
            
            // If Cantaloupe can't serve this page, use fallback dimensions
            if ($dimensions['width'] === 1000 && $dimensions['height'] === 1000) {
                $dimensions = ['width' => 612, 'height' => 792]; // Letter size at 72 DPI
            }
            
            $canvasId = $this->baseUrl . '/iiif/canvas/pdf-' . $do->id . '/' . ($i + 1);
            $imageUri = $this->cantaloupeUrl . '/' . urlencode($pageIdentifier);
            
            $canvases[] = [
                'id' => $canvasId,
                'type' => 'Canvas',
                'label' => $this->langMap('Page ' . ($startIndex + $i), $lang),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'thumbnail' => [[
                    'id' => $imageUri . '/full/200,/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg'
                ]],
                'items' => [
                    [
                        'id' => $canvasId . '/annotation-page/main',
                        'type' => 'AnnotationPage',
                        'items' => [
                            [
                                'id' => $canvasId . '/annotation/image',
                                'type' => 'Annotation',
                                'motivation' => 'painting',
                                'body' => [
                                    'id' => $imageUri . '/full/max/0/default.jpg',
                                    'type' => 'Image',
                                    'format' => 'image/jpeg',
                                    'service' => [[
                                        'id' => $imageUri,
                                        'type' => 'ImageService3',
                                        'profile' => 'level2'
                                    ]]
                                ],
                                'target' => $canvasId
                            ]
                        ]
                    ]
                ],
                'rendering' => [[
                    'id' => $isRedacted
                        ? $this->baseUrl . '/privacyAdmin/downloadPdf?id=' . $do->object_id
                        : $this->baseUrl . '/uploads/' . trim($do->path, '/') . '/' . $do->name,
                    'type' => 'Text',
                    'label' => $this->langMap($isRedacted ? 'Download Redacted PDF' : 'Download PDF', $lang),
                    'format' => 'application/pdf'
                ]]
            ];
        }

        return $canvases;
    }

    /**
     * Get PDF path with PII redaction check
     *
     * Returns the redacted PDF path if PII redaction is active for this object,
     * otherwise returns the original path.
     *
     * @param object $do Digital object
     * @return array ['path' => string, 'identifier' => string, 'is_redacted' => bool]
     */
    private function getPdfPathWithRedaction(object $do): array
    {
        $originalPath = $this->getDigitalObjectPath($do);
        $originalIdentifier = $this->buildIiifIdentifier($do->path, $do->name);
        $objectId = $do->object_id ?? null;

        // Check if PII redaction is needed for this object
        if (!$objectId || !$this->hasPiiRedaction($objectId)) {
            return [
                'path' => $originalPath,
                'identifier' => $originalIdentifier,
                'is_redacted' => false
            ];
        }

        // Load redaction service and get redacted PDF
        try {
            $pluginPath = class_exists('sfConfig')
                ? \sfConfig::get('sf_plugins_dir') . '/ahgPrivacyPlugin/lib/Service/PdfRedactionService.php'
                : '/usr/share/nginx/archive/plugins/ahgPrivacyPlugin/lib/Service/PdfRedactionService.php';

            if (!file_exists($pluginPath)) {
                return [
                    'path' => $originalPath,
                    'identifier' => $originalIdentifier,
                    'is_redacted' => false
                ];
            }

            require_once $pluginPath;
            $service = new \ahgPrivacyPlugin\Service\PdfRedactionService();
            $result = $service->getRedactedPdf($objectId, $originalPath);

            if ($result['success'] && file_exists($result['path'])) {
                // Build identifier for redacted PDF
                // The redacted PDF is in cache, need to make it accessible to Cantaloupe
                $redactedPath = $result['path'];

                // Create symlink in uploads for Cantaloupe access
                $symlinkDir = class_exists('sfConfig')
                    ? \sfConfig::get('sf_upload_dir') . '/pii_redacted'
                    : '/usr/share/nginx/archive/uploads/pii_redacted';

                if (!is_dir($symlinkDir)) {
                    @mkdir($symlinkDir, 0755, true);
                }

                $symlinkName = basename($redactedPath);
                $symlinkPath = $symlinkDir . '/' . $symlinkName;

                // Create/update symlink
                if (!file_exists($symlinkPath) || !is_link($symlinkPath)) {
                    @unlink($symlinkPath);
                    @symlink($redactedPath, $symlinkPath);
                }

                // Build IIIF identifier for the symlinked redacted PDF
                $redactedIdentifier = $this->buildIiifIdentifier('/pii_redacted', $symlinkName);

                return [
                    'path' => $redactedPath,
                    'identifier' => $redactedIdentifier,
                    'is_redacted' => true
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('PII redaction failed for manifest', [
                'object_id' => $objectId,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to original
        return [
            'path' => $originalPath,
            'identifier' => $originalIdentifier,
            'is_redacted' => false
        ];
    }

    /**
     * Check if an object has PII entities marked for redaction
     *
     * @param int $objectId
     * @return bool
     */
    private function hasPiiRedaction(int $objectId): bool
    {
        try {
            $count = DB::table('ahg_ner_entity')
                ->where('object_id', $objectId)
                ->where('status', 'redacted')
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Create canvas for 3D model (from digital_object)
     */
    private function create3DCanvas(object $model, int $index, string $lang): array
    {
        $canvasId = $this->baseUrl . '/iiif/canvas/3d-' . $model->id;
        
        // Build URL from digital object path (standard AtoM upload location)
        if (!empty($model->is_digital_object)) {
            $modelUrl = $this->baseUrl . '/uploads/' . trim($model->path ?? '', '/') . '/' . $model->filename;
        } else {
            // Fallback for legacy separate 3D upload
            $modelUrl = $this->baseUrl . '/uploads/3d/' . $model->object_id . '/' . $model->filename;
        }
        
        // Determine model format
        $format = $model->format ?? pathinfo($model->filename, PATHINFO_EXTENSION);
        $mimeTypes = [
            'glb' => 'model/gltf-binary',
            'gltf' => 'model/gltf+json',
            'obj' => 'model/obj',
            'stl' => 'model/stl',
            'fbx' => 'model/fbx',
            'ply' => 'model/ply',
            'usdz' => 'model/vnd.usdz+zip'
        ];
        
        $mimeType = $mimeTypes[strtolower($format)] ?? $model->mime_type ?? 'model/gltf-binary';
        
        $canvas = [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => $this->langMap($model->title ?: '3D Model ' . $index, $lang),
            'width' => 1000,
            'height' => 1000,
            'items' => [
                [
                    'id' => $canvasId . '/annotation-page/main',
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $canvasId . '/annotation/model',
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $modelUrl,
                                'type' => 'Model',
                                'format' => $mimeType,
                                'label' => $this->langMap($model->title ?: 'Model', $lang)
                            ],
                            'target' => $canvasId
                        ]
                    ]
                ]
            ],
            'behavior' => ['3d-model']
        ];
        
        // Add poster image as thumbnail
        if ($model->poster_image) {
            $canvas['thumbnail'] = [[
                'id' => $this->baseUrl . $model->poster_image,
                'type' => 'Image',
                'format' => 'image/jpeg'
            ]];
        }
        
        return $canvas;
    }
    
    /**
     * Create canvas for audio/video
     */
    private function createAVCanvas(object $do, int $index, string $lang): array
    {
        $canvasId = $this->baseUrl . '/iiif/canvas/av-' . $do->id;
        $mediaUrl = $this->baseUrl . '/uploads/' . trim($do->path, '/') . '/' . $do->name;
        
        $isAudio = stripos($do->mime_type, 'audio') !== false;
        $type = $isAudio ? 'Sound' : 'Video';
        
        // Get duration if available
        $duration = $this->getMediaDuration($do);
        
        $canvas = [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => $this->langMap($do->name, $lang),
            'duration' => $duration ?: 0,
            'items' => [
                [
                    'id' => $canvasId . '/annotation-page/main',
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $canvasId . '/annotation/media',
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $mediaUrl,
                                'type' => $type,
                                'format' => $do->mime_type,
                                'duration' => $duration ?: 0
                            ],
                            'target' => $canvasId
                        ]
                    ]
                ]
            ]
        ];
        
        if (!$isAudio) {
            $canvas['width'] = 1920;
            $canvas['height'] = 1080;
        }
        
        return $canvas;
    }
    
    // ========================================================================
    // Legacy 2.1 Support
    // ========================================================================
    
    /**
     * Generate IIIF 2.1 manifest for backward compatibility
     */
    public function generateManifest21(string $identifier, array $options = []): array
    {
        $title = $this->parseTitle($identifier);
        $dimensions = $this->getImageDimensions($identifier);
        $pageCount = $this->detectPageCount($identifier);
        
        $manifestId = $this->baseUrl . '/iiif-manifest.php?id=' . urlencode($identifier) . '&format=2';
        
        $manifest = [
            '@context' => self::CONTEXT_PRESENTATION_2,
            '@id' => $manifestId,
            '@type' => 'sc:Manifest',
            'label' => $title,
            'description' => 'Digital image from The Archive and Heritage Group collection.',
            'attribution' => $this->config['attribution'],
            'license' => $this->config['license'],
            'metadata' => [
                ['label' => 'Source', 'value' => 'The Archive and Heritage Group Digital Archives'],
                ['label' => 'Format', 'value' => $this->detectFormat($identifier)]
            ],
            'thumbnail' => [
                '@id' => $this->cantaloupeUrl . '/' . urlencode($identifier) . '/full/200,/0/default.jpg',
                'service' => [
                    '@context' => 'http://iiif.io/api/image/2/context.json',
                    '@id' => $this->cantaloupeUrl . '/' . urlencode($identifier),
                    'profile' => 'http://iiif.io/api/image/2/level2.json'
                ]
            ],
            'viewingDirection' => 'left-to-right',
            'viewingHint' => $pageCount > 1 ? 'paged' : 'individuals',
            'sequences' => [
                [
                    '@id' => $this->baseUrl . '/iiif/sequence/' . urlencode($identifier) . '/normal',
                    '@type' => 'sc:Sequence',
                    'label' => 'Normal Sequence',
                    'canvases' => []
                ]
            ]
        ];
        
        // Generate canvases
        if ($pageCount > 1) {
            for ($i = 0; $i < $pageCount; $i++) {
                $pageIdentifier = $identifier . '[' . $i . ']';
                $pageDimensions = $this->getImageDimensions($pageIdentifier) ?: $dimensions;
                $manifest['sequences'][0]['canvases'][] = $this->createCanvas21($pageIdentifier, $pageDimensions, $i + 1);
            }
        } else {
            $manifest['sequences'][0]['canvases'][] = $this->createCanvas21($identifier, $dimensions, 1);
        }
        
        return $manifest;
    }
    
    private function createCanvas21(string $identifier, array $dimensions, int $pageNum): array
    {
        $canvasId = $this->baseUrl . '/iiif/canvas/' . urlencode($identifier);
        $imageUri = $this->cantaloupeUrl . '/' . urlencode($identifier);
        
        return [
            '@id' => $canvasId,
            '@type' => 'sc:Canvas',
            'label' => 'Page ' . $pageNum,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'images' => [
                [
                    '@id' => $canvasId . '/annotation/1',
                    '@type' => 'oa:Annotation',
                    'motivation' => 'sc:painting',
                    'on' => $canvasId,
                    'resource' => [
                        '@id' => $imageUri . '/full/full/0/default.jpg',
                        '@type' => 'dctypes:Image',
                        'format' => 'image/jpeg',
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'service' => [
                            '@context' => 'http://iiif.io/api/image/2/context.json',
                            '@id' => $imageUri,
                            'profile' => 'http://iiif.io/api/image/2/level2.json'
                        ]
                    ]
                ]
            ],
            'thumbnail' => [
                '@id' => $imageUri . '/full/200,/0/default.jpg'
            ]
        ];
    }
    
    // ========================================================================
    // Helper Methods
    // ========================================================================
    
    private function getObjectInfo(int $objectId): ?object
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function($join) {
                $join->on('io.id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', $this->defaultLanguage);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select('io.*', 'ioi.title', 'ioi.scope_and_content', 'slug.slug')
            ->first();
    }
    
    private function getDigitalObjects(int $objectId): array
    {
        return DB::table('digital_object')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }
    
    /**
     * Get 3D models from standard digital_object table
     * Detects 3D files by MIME type or extension
     */
    private function get3DModels(int $objectId): array
    {
        // 3D model MIME types
        $mimeTypes = [
            'model/gltf-binary',
            'model/gltf+json', 
            'model/obj',
            'model/stl',
            'model/vnd.usdz+zip',
            'application/octet-stream', // Often used for .glb files
        ];
        
        // 3D file extensions
        $extensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'usdz'];
        
        $models = [];
        
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->get();
        
        foreach ($digitalObjects as $do) {
            $ext = strtolower(pathinfo($do->name, PATHINFO_EXTENSION));
            $mime = strtolower($do->mime_type ?? '');
            
            // Check if it's a 3D model
            if (in_array($ext, $extensions) || in_array($mime, $mimeTypes)) {
                // Convert digital object to 3D model format for compatibility
                $models[] = (object)[
                    'id' => $do->id,
                    'object_id' => $do->object_id,
                    'filename' => $do->name,
                    'path' => $do->path,
                    'format' => $ext,
                    'mime_type' => $do->mime_type,
                    'title' => pathinfo($do->name, PATHINFO_FILENAME),
                    'description' => null,
                    'auto_rotate' => true,
                    'ar_enabled' => true,
                    'camera_orbit' => '0deg 75deg 105%',
                    'background_color' => '#f5f5f5',
                    'poster_image' => null,
                    'is_digital_object' => true, // Flag to indicate source
                ];
            }
        }
        
        return $models;
    }
    
    private function getCollectionItems(int $collectionId, string $lang): array
    {
        return DB::table('iiif_collection_item as ci')
            ->leftJoin('information_object_i18n as ioi', function($join) use ($lang) {
                $join->on('ci.object_id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', $lang);
            })
            ->leftJoin('digital_object as do', 'ci.object_id', '=', 'do.object_id')
            ->leftJoin('slug', 'ci.object_id', '=', 'slug.object_id')
            ->where('ci.collection_id', $collectionId)
            ->whereNotNull('do.id')
            ->orderBy('ci.display_order')
            ->select(
                'ci.*',
                'ioi.title as object_title',
                'slug.slug',
                'do.path as digital_object_path',
                'do.name as digital_object_name'
            )
            ->get()
            ->toArray();
    }
    
    public function getImageDimensions(string $identifier): array
    {
        $infoUrl = $this->cantaloupeUrl . '/' . urlencode($identifier) . '/info.json';
        
        $context = stream_context_create([
            'http' => ['timeout' => 5],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        
        $response = @file_get_contents($infoUrl, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['width']) && isset($data['height'])) {
                return [
                    'width' => (int)$data['width'],
                    'height' => (int)$data['height']
                ];
            }
        }
        
        return ['width' => 1000, 'height' => 1000];
    }
    
    private function detectPageCount(string $identifier): int
    {
        $page1Url = $this->cantaloupeUrl . '/' . urlencode($identifier . '[1]') . '/info.json';
        
        $context = stream_context_create([
            'http' => ['timeout' => 3],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        
        $response = @file_get_contents($page1Url, false, $context);
        
        if (!$response) {
            return 1;
        }
        
        $count = 2;
        $max = 100;
        
        while ($count <= $max) {
            $testUrl = $this->cantaloupeUrl . '/' . urlencode($identifier . '[' . $count . ']') . '/info.json';
            $testResponse = @file_get_contents($testUrl, false, $context);
            
            if (!$testResponse) {
                break;
            }
            
            $count++;
        }
        
        return $count;
    }
    
    private function getPdfPageCount(string $pdfPath): int
    {
        if (!file_exists($pdfPath)) {
            return 0;
        }
        
        // Try pdfinfo command
        $output = shell_exec("pdfinfo " . escapeshellarg($pdfPath) . " 2>/dev/null | grep Pages:");
        if ($output && preg_match('/Pages:\s+(\d+)/', $output, $matches)) {
            return (int)$matches[1];
        }
        
        // Fallback: try reading PDF
        $content = file_get_contents($pdfPath, false, null, 0, 10000);
        if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
            return (int)$matches[1];
        }
        
        return 1;
    }
    
    private function getDigitalObjectPath(object $do): string
    {
        // The $do->path already includes /uploads/ prefix, so use sf_web_dir
        $webDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_web_dir')
            : '/usr/share/nginx/archive';
        return $webDir . $do->path . $do->name;
    }
    
    private function getMediaDuration(object $do): ?float
    {
        $path = $this->getDigitalObjectPath($do);
        
        if (!file_exists($path)) {
            return null;
        }
        
        // Try ffprobe
        $output = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path) . " 2>/dev/null");
        
        if ($output) {
            return (float)trim($output);
        }
        
        return null;
    }
    
    private function hasPdfContent(array $digitalObjects): bool
    {
        foreach ($digitalObjects as $do) {
            if (stripos($do->mime_type ?? '', 'pdf') !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function hasAVContent(array $digitalObjects): bool
    {
        foreach ($digitalObjects as $do) {
            $mime = $do->mime_type ?? '';
            if (stripos($mime, 'audio') !== false || stripos($mime, 'video') !== false) {
                return true;
            }
        }
        return false;
    }
    
    public function buildIiifIdentifier(?string $path, ?string $name): string
    {
        $path = trim($path ?? '', '/');
        return str_replace('/', '_SL_', $path . '/' . $name);
    }
    
    private function parseTitle(string $identifier): string
    {
        $title = str_replace('_SL_', '/', $identifier);
        $parts = explode('/', $title);
        $filename = end($parts);
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['_', '-'], ' ', $title);
        return ucwords($title) ?: 'Untitled Image';
    }
    
    private function detectFormat(string $identifier): string
    {
        $ext = strtolower(pathinfo($identifier, PATHINFO_EXTENSION));
        
        $formats = [
            'tif' => 'TIFF Image',
            'tiff' => 'TIFF Image',
            'jp2' => 'JPEG 2000',
            'jpg' => 'JPEG Image',
            'jpeg' => 'JPEG Image',
            'png' => 'PNG Image',
            'pdf' => 'PDF Document',
            'glb' => '3D Model (glTF Binary)',
            'gltf' => '3D Model (glTF)',
        ];
        
        return $formats[$ext] ?? 'Digital Object';
    }
    
    private function calculateThumbnailHeight(array $dimensions, int $targetWidth): int
    {
        if ($dimensions['width'] <= 0) return $targetWidth;
        return (int) round($dimensions['height'] * ($targetWidth / $dimensions['width']));
    }
    
    private function langMap($value, string $lang): array
    {
        if (is_array($value)) {
            return $value;
        }
        return [$lang => [(string)$value]];
    }
    
    private function buildProvider(string $lang): array
    {
        $provider = [
            'id' => $this->baseUrl,
            'type' => 'Agent',
            'label' => $this->langMap($this->config['attribution'], $lang),
            'homepage' => [[
                'id' => $this->baseUrl,
                'type' => 'Text',
                'label' => $this->langMap('Homepage', $lang),
                'format' => 'text/html'
            ]]
        ];
        
        if ($this->config['logo_url']) {
            $provider['logo'] = [[
                'id' => $this->config['logo_url'],
                'type' => 'Image',
                'format' => 'image/png'
            ]];
        }
        
        return [$provider];
    }
    
    private function buildMetadata(object $object, string $lang): array
    {
        $metadata = [];
        
        // Repository
        if ($object->repository_id) {
            $repo = DB::table('repository')
                ->leftJoin('actor_i18n', function($join) use ($lang) {
                    $join->on('repository.id', '=', 'actor_i18n.id')
                         ->where('actor_i18n.culture', '=', $lang);
                })
                ->where('repository.id', $object->repository_id)
                ->select('actor_i18n.authorized_form_of_name')
                ->first();
            
            if ($repo && $repo->authorized_form_of_name) {
                $metadata[] = [
                    'label' => $this->langMap('Repository', $lang),
                    'value' => $this->langMap($repo->authorized_form_of_name, $lang)
                ];
            }
        }
        
        // Identifier
        if (!empty($object->identifier)) {
            $metadata[] = [
                'label' => $this->langMap('Reference Code', $lang),
                'value' => $this->langMap($object->identifier, $lang)
            ];
        }
        
        // Level of description
        if ($object->level_of_description_id) {
            $level = DB::table('term_i18n')
                ->where('id', $object->level_of_description_id)
                ->where('culture', $lang)
                ->value('name');
            
            if ($level) {
                $metadata[] = [
                    'label' => $this->langMap('Level of Description', $lang),
                    'value' => $this->langMap($level, $lang)
                ];
            }
        }
        
        return $metadata;
    }
    
    private function determineBehavior(array $digitalObjects, bool $has3D): array
    {
        $behavior = [];
        
        if (count($digitalObjects) > 1) {
            $behavior[] = 'paged';
        } else {
            $behavior[] = 'individuals';
        }
        
        if ($has3D) {
            $behavior[] = '3d-model';
        }
        
        return $behavior;
    }
    
    private function buildStructures(array $items, object $object, string $lang): array
    {
        if (count($items) <= 1) {
            return [];
        }
        
        $range = [
            'id' => $this->baseUrl . '/iiif/range/' . $object->slug . '/all',
            'type' => 'Range',
            'label' => $this->langMap('Table of Contents', $lang),
            'items' => []
        ];
        
        foreach ($items as $index => $item) {
            $range['items'][] = [
                'id' => $item['id'],
                'type' => 'Canvas'
            ];
        }
        
        return [$range];
    }
    
    private function getAnnotationPages(int $objectId, array $canvases): array
    {
        // Will be populated by AnnotationService
        return [];
    }
    
    private function addOcrAnnotations(array &$manifest, int $objectId): void
    {
        // Will be populated by OcrService
    }
    
    private function removeNullValues(array $array): array
    {
        return array_filter($array, function($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== null;
        });
    }
}
