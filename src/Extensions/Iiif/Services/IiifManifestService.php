<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Iiif\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * IIIF Manifest Service - Presentation API 3.0
 * 
 * Generates IIIF 3.0 compliant manifests for images, collections, and 3D models
 * 
 * @package AtomFramework\Extensions\Iiif
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 3.0.0
 */
class IiifManifestService
{
    private Logger $logger;
    private string $baseUrl;
    private string $cantaloupeUrl;
    private string $defaultLanguage = 'en';
    
    // IIIF 3.0 Context
    private const CONTEXT_PRESENTATION = 'http://iiif.io/api/presentation/3/context.json';
    private const CONTEXT_IMAGE = 'http://iiif.io/api/image/3/context.json';
    
    public function __construct()
    {
        $this->logger = new Logger('iiif-manifest');
        $logPath = '/var/log/atom/iiif-manifest.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
        
        $this->baseUrl = $this->getSetting('site_base_url', 'https://archives.theahg.co.za');
        $this->cantaloupeUrl = $this->getSetting('iiif_server_url', 'https://archives.theahg.co.za/iiif/2');
    }
    
    // ========================================================================
    // IIIF 3.0 Manifest Generation - Single Image
    // ========================================================================
    
    /**
     * Generate IIIF 3.0 manifest for a single image identifier
     */
    public function generateImageManifest(string $identifier): array
    {
        $lang = $this->defaultLanguage;
        $title = $this->parseTitle($identifier);
        $manifestId = $this->baseUrl . '/iiif-manifest.php?id=' . urlencode($identifier);
        
        // Get image dimensions
        $dimensions = $this->getImageDimensions($identifier);
        
        // Check for multi-page
        $pageCount = $this->detectPageCount($identifier);
        
        $manifest = [
            '@context' => self::CONTEXT_PRESENTATION,
            'id' => $manifestId,
            'type' => 'Manifest',
            'label' => [$lang => [$title]],
            'metadata' => [
                [
                    'label' => [$lang => ['Source']],
                    'value' => [$lang => ['The Archive and Heritage Group Digital Archives']]
                ],
                [
                    'label' => [$lang => ['Format']],
                    'value' => [$lang => [$this->detectFormat($identifier)]]
                ]
            ],
            'summary' => [$lang => ['Digital image from The Archive and Heritage Group collection.']],
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
            'rights' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            'requiredStatement' => [
                'label' => [$lang => ['Attribution']],
                'value' => [$lang => ['The Archive and Heritage Group']]
            ],
            'provider' => [
                [
                    'id' => $this->baseUrl,
                    'type' => 'Agent',
                    'label' => [$lang => ['The Archive and Heritage Group']],
                    'homepage' => [
                        [
                            'id' => $this->baseUrl,
                            'type' => 'Text',
                            'label' => [$lang => ['Homepage']],
                            'format' => 'text/html'
                        ]
                    ]
                ]
            ],
            'items' => []
        ];
        
        // Generate canvases
        if ($pageCount > 1) {
            for ($i = 0; $i < $pageCount; $i++) {
                $pageIdentifier = $identifier . '[' . $i . ']';
                $pageDimensions = $this->getImageDimensions($pageIdentifier) ?: $dimensions;
                $manifest['items'][] = $this->createCanvas($pageIdentifier, $pageDimensions, $i + 1, $lang);
            }
        } else {
            $manifest['items'][] = $this->createCanvas($identifier, $dimensions, 1, $lang);
        }
        
        return $manifest;
    }
    
    // ========================================================================
    // IIIF 3.0 Manifest Generation - Information Object
    // ========================================================================
    
    /**
     * Generate IIIF 3.0 manifest for an AtoM information object
     */
    public function generateObjectManifest(int $objectId): ?array
    {
        $lang = $this->defaultLanguage;
        
        // Get object info
        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function($join) use ($lang) {
                $join->on('io.id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', $lang);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select('io.*', 'ioi.title', 'ioi.scope_and_content', 'slug.slug')
            ->first();
        
        if (!$object) {
            return null;
        }
        
        // Get digital objects
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get()
            ->toArray();
        
        if (empty($digitalObjects)) {
            return null;
        }
        
        $manifestUri = $this->baseUrl . '/iiif/manifest/' . $object->slug;
        
        $manifest = [
            '@context' => self::CONTEXT_PRESENTATION,
            'id' => $manifestUri,
            'type' => 'Manifest',
            'label' => [$lang => [$object->title ?: 'Untitled']],
            'metadata' => $this->buildObjectMetadata($object, $lang),
            'viewingDirection' => 'left-to-right',
            'behavior' => count($digitalObjects) > 1 ? ['paged'] : ['individuals'],
            'rights' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            'requiredStatement' => [
                'label' => [$lang => ['Attribution']],
                'value' => [$lang => ['The Archive and Heritage Group']]
            ],
            'provider' => [
                [
                    'id' => $this->baseUrl,
                    'type' => 'Agent',
                    'label' => [$lang => ['The Archive and Heritage Group']]
                ]
            ],
            'homepage' => [
                [
                    'id' => $this->baseUrl . '/' . $object->slug,
                    'type' => 'Text',
                    'label' => [$lang => ['View in Archive']],
                    'format' => 'text/html'
                ]
            ],
            'items' => []
        ];
        
        if ($object->scope_and_content) {
            $manifest['summary'] = [$lang => [strip_tags($object->scope_and_content)]];
        }
        
        // Add canvases for each digital object
        foreach ($digitalObjects as $index => $digitalObject) {
            $iiifIdentifier = $this->buildIiifIdentifier($digitalObject);
            $dimensions = $this->getImageDimensions($iiifIdentifier);
            
            $manifest['items'][] = $this->createCanvasForDigitalObject(
                $digitalObject,
                $iiifIdentifier,
                $dimensions,
                $index + 1,
                $lang
            );
        }
        
        // Set thumbnail from first canvas
        if (!empty($manifest['items'][0]['thumbnail'])) {
            $manifest['thumbnail'] = $manifest['items'][0]['thumbnail'];
        }
        
        $this->logger->info('Generated object manifest', ['object_id' => $objectId, 'slug' => $object->slug]);
        
        return $manifest;
    }
    
    // ========================================================================
    // IIIF 3.0 Collection Manifest
    // ========================================================================
    
    /**
     * Generate IIIF 3.0 Collection manifest
     */
    public function generateCollectionManifest(int $collectionId): ?array
    {
        $lang = $this->defaultLanguage;
        
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
            '@context' => self::CONTEXT_PRESENTATION,
            'id' => $collectionUri,
            'type' => 'Collection',
            'label' => [$lang => [$collection->title ?: 'Untitled Collection']],
            'viewingDirection' => 'left-to-right',
            'behavior' => [$collection->view_type === 'continuous' ? 'continuous' : 'individuals'],
            'requiredStatement' => [
                'label' => [$lang => ['Attribution']],
                'value' => [$lang => ['The Archive and Heritage Group']]
            ],
            'provider' => [
                [
                    'id' => $this->baseUrl,
                    'type' => 'Agent',
                    'label' => [$lang => ['The Archive and Heritage Group']]
                ]
            ],
            'items' => []
        ];
        
        if ($collection->description) {
            $manifest['summary'] = [$lang => [$collection->description]];
        }
        
        if ($collection->thumbnail_url) {
            $manifest['thumbnail'] = [
                [
                    'id' => $collection->thumbnail_url,
                    'type' => 'Image',
                    'format' => 'image/jpeg'
                ]
            ];
        }
        
        // Get items
        $items = DB::table('iiif_collection_item as ci')
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
            ->get();
        
        foreach ($items as $item) {
            $iiifIdentifier = $this->buildIiifIdentifierFromPath($item->digital_object_path, $item->digital_object_name);
            $imageUri = $this->cantaloupeUrl . '/' . urlencode($iiifIdentifier);
            
            $manifest['items'][] = [
                'id' => $this->baseUrl . '/iiif/manifest/' . $item->slug,
                'type' => 'Manifest',
                'label' => [$lang => [$item->object_title ?: $item->digital_object_name]],
                'thumbnail' => [
                    [
                        'id' => $imageUri . '/full/200,/0/default.jpg',
                        'type' => 'Image',
                        'format' => 'image/jpeg'
                    ]
                ]
            ];
        }
        
        $this->logger->info('Generated collection manifest', ['collection_id' => $collectionId]);
        
        return $manifest;
    }
    
    // ========================================================================
    // Legacy 2.1 Support
    // ========================================================================
    
    /**
     * Generate IIIF 2.1 manifest (legacy support)
     */
    public function generateManifest21(string $identifier): array
    {
        $title = $this->parseTitle($identifier);
        $dimensions = $this->getImageDimensions($identifier);
        $pageCount = $this->detectPageCount($identifier);
        
        $manifestId = $this->baseUrl . '/iiif-manifest.php?id=' . urlencode($identifier) . '&format=2';
        
        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => $manifestId,
            '@type' => 'sc:Manifest',
            'label' => $title,
            'description' => 'Digital image from The Archive and Heritage Group collection.',
            'attribution' => 'The Archive and Heritage Group',
            'license' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
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
    
    // ========================================================================
    // Canvas Creation
    // ========================================================================
    
    /**
     * Create a IIIF 3.0 Canvas
     */
    private function createCanvas(string $identifier, array $dimensions, int $pageNum, string $lang): array
    {
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        
        $canvasId = $this->baseUrl . '/iiif/canvas/' . urlencode($identifier);
        $imageUri = $this->cantaloupeUrl . '/' . urlencode($identifier);
        
        return [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => [$lang => ['Page ' . $pageNum]],
            'width' => $width,
            'height' => $height,
            'items' => [
                [
                    'id' => $canvasId . '/annotation-page/1',
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $canvasId . '/annotation/1',
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $imageUri . '/full/max/0/default.jpg',
                                'type' => 'Image',
                                'format' => 'image/jpeg',
                                'width' => $width,
                                'height' => $height,
                                'service' => [
                                    [
                                        'id' => $imageUri,
                                        'type' => 'ImageService3',
                                        'profile' => 'level2'
                                    ]
                                ]
                            ],
                            'target' => $canvasId
                        ]
                    ]
                ]
            ],
            'thumbnail' => [
                [
                    'id' => $imageUri . '/full/200,/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'width' => 200,
                    'height' => $this->calculateThumbnailHeight($dimensions, 200)
                ]
            ]
        ];
    }
    
    /**
     * Create a IIIF 3.0 Canvas for a digital object
     */
    private function createCanvasForDigitalObject(
        object $digitalObject,
        string $iiifIdentifier,
        array $dimensions,
        int $pageNum,
        string $lang
    ): array {
        $imageUri = $this->cantaloupeUrl . '/' . urlencode($iiifIdentifier);
        $canvasUri = $this->baseUrl . '/iiif/canvas/' . $digitalObject->id;
        
        return [
            'id' => $canvasUri,
            'type' => 'Canvas',
            'label' => [$lang => ['Page ' . $pageNum]],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'thumbnail' => [
                [
                    'id' => $imageUri . '/full/200,/0/default.jpg',
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                    'width' => 200,
                    'height' => $this->calculateThumbnailHeight($dimensions, 200)
                ]
            ],
            'items' => [
                [
                    'id' => $canvasUri . '/annotation-page/1',
                    'type' => 'AnnotationPage',
                    'items' => [
                        [
                            'id' => $canvasUri . '/annotation/1',
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $imageUri . '/full/max/0/default.jpg',
                                'type' => 'Image',
                                'format' => 'image/jpeg',
                                'width' => $dimensions['width'],
                                'height' => $dimensions['height'],
                                'service' => [
                                    [
                                        'id' => $imageUri,
                                        'type' => 'ImageService3',
                                        'profile' => 'level2'
                                    ]
                                ]
                            ],
                            'target' => $canvasUri
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Create a IIIF 2.1 Canvas (legacy)
     */
    private function createCanvas21(string $identifier, array $dimensions, int $pageNum): array
    {
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        
        $canvasId = $this->baseUrl . '/iiif/canvas/' . urlencode($identifier);
        $imageUri = $this->cantaloupeUrl . '/' . urlencode($identifier);
        
        return [
            '@id' => $canvasId,
            '@type' => 'sc:Canvas',
            'label' => 'Page ' . $pageNum,
            'width' => $width,
            'height' => $height,
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
                        'width' => $width,
                        'height' => $height,
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
    
    /**
     * Build IIIF identifier from digital object
     */
    private function buildIiifIdentifier(object $digitalObject): string
    {
        $path = trim($digitalObject->path ?? '', '/');
        return str_replace('/', '_SL_', $path . '/' . $digitalObject->name);
    }
    
    /**
     * Build IIIF identifier from path and name
     */
    private function buildIiifIdentifierFromPath(string $path, string $name): string
    {
        $path = trim($path, '/');
        return str_replace('/', '_SL_', $path . '/' . $name);
    }
    
    /**
     * Get image dimensions from Cantaloupe
     */
    private function getImageDimensions(string $identifier): array
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
    
    /**
     * Detect page count for multi-page documents
     */
    private function detectPageCount(string $identifier): int
    {
        // Try page 1
        $page1Url = $this->cantaloupeUrl . '/' . urlencode($identifier . '[1]') . '/info.json';
        
        $context = stream_context_create([
            'http' => ['timeout' => 3],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        
        $response = @file_get_contents($page1Url, false, $context);
        
        if (!$response) {
            return 1;
        }
        
        // Count pages
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
    
    /**
     * Parse title from identifier
     */
    private function parseTitle(string $identifier): string
    {
        $title = str_replace('_SL_', '/', $identifier);
        $parts = explode('/', $title);
        $filename = end($parts);
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['_', '-'], ' ', $title);
        return ucwords($title) ?: 'Untitled Image';
    }
    
    /**
     * Detect format from identifier
     */
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
        ];
        
        return $formats[$ext] ?? 'Digital Image';
    }
    
    /**
     * Calculate thumbnail height maintaining aspect ratio
     */
    private function calculateThumbnailHeight(array $dimensions, int $targetWidth): int
    {
        if ($dimensions['width'] <= 0) return $targetWidth;
        return (int) round($dimensions['height'] * ($targetWidth / $dimensions['width']));
    }
    
    /**
     * Build metadata for object manifest
     */
    private function buildObjectMetadata(object $object, string $lang): array
    {
        $metadata = [];
        
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
                    'label' => [$lang => ['Repository']],
                    'value' => [$lang => [$repo->authorized_form_of_name]]
                ];
            }
        }
        
        if (!empty($object->identifier)) {
            $metadata[] = [
                'label' => [$lang => ['Reference Code']],
                'value' => [$lang => [$object->identifier]]
            ];
        }
        
        if ($object->level_of_description_id) {
            $level = DB::table('term_i18n')
                ->where('id', $object->level_of_description_id)
                ->where('culture', $lang)
                ->value('name');
            
            if ($level) {
                $metadata[] = [
                    'label' => [$lang => ['Level']],
                    'value' => [$lang => [$level]]
                ];
            }
        }
        
        return $metadata;
    }
    
    /**
     * Get setting from database
     */
    private function getSetting(string $key, $default = null)
    {
        try {
            $setting = DB::table('setting')
                ->leftJoin('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
                ->where('setting.name', $key)
                ->value('setting_i18n.value');
            
            return $setting ?: $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
