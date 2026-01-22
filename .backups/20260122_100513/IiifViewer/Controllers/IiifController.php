<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Controllers;

use AtomFramework\Extensions\IiifViewer\Services\IiifManifestService;
use AtomFramework\Extensions\IiifViewer\Services\AnnotationService;
use AtomFramework\Extensions\IiifViewer\Services\OcrService;
use AtomFramework\Extensions\IiifViewer\Services\ViewerService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * IIIF Controller
 * 
 * Handles all IIIF-related HTTP requests:
 * - Manifests (object, collection, image)
 * - Annotations (CRUD, pages)
 * - OCR (text, search)
 * - Viewer rendering
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class IiifController
{
    private IiifManifestService $manifestService;
    private AnnotationService $annotationService;
    private OcrService $ocrService;
    private ViewerService $viewerService;
    
    private static function getAtomBaseUrl(): string
    {
        try {
            $result = \Illuminate\Database\Capsule\Manager::table("setting")
                ->join("setting_i18n", "setting.id", "=", "setting_i18n.id")
                ->where("setting.name", "siteBaseUrl")
                ->first();
            if ($result && !empty($result->value)) {
                return rtrim($result->value, "/");
            }
        } catch (\Exception $e) {}
        return "https://psis.theahg.co.za";
    }

    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'base_url' => self::getAtomBaseUrl(),
            'cantaloupe_url' => self::getAtomBaseUrl() . '/iiif/2',
        ], $config);
        
        $this->manifestService = new IiifManifestService($this->config);
        $this->annotationService = new AnnotationService($this->config['base_url']);
        $this->ocrService = new OcrService($this->config['base_url']);
        $this->viewerService = new ViewerService($this->config);
    }
    
    // ========================================================================
    // Response Helpers
    // ========================================================================
    
    private function jsonResponse($data, int $status = 200, string $context = 'presentation'): void
    {
        http_response_code($status);
        
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($context === 'annotation') {
            header('Content-Type: application/ld+json;profile="http://www.w3.org/ns/anno.jsonld"');
        } else {
            header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private function errorResponse(string $message, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        echo json_encode(['error' => $message, 'status' => $status]);
        exit;
    }
    
    private function htmlResponse(string $html): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }
    
    public function handleOptions(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }
    
    // ========================================================================
    // Manifest Endpoints
    // ========================================================================
    
    /**
     * GET /iiif/manifest/{slug}
     * Object manifest
     */
    public function objectManifest(string $slug): void
    {
        $objectId = $this->getObjectIdBySlug($slug);
        
        if (!$objectId) {
            $this->errorResponse('Object not found', 404);
        }
        
        $manifest = $this->manifestService->generateObjectManifest($objectId);
        
        if (!$manifest) {
            $this->errorResponse('No digital objects found', 404);
        }
        
        $this->jsonResponse($manifest);
    }
    
    /**
     * GET /iiif/collection/{slug}
     * Collection manifest
     */
    public function collectionManifest(string $slug): void
    {
        $collection = DB::table('iiif_collection')
            ->where('slug', $slug)
            ->where('is_public', 1)
            ->first();
        
        if (!$collection) {
            $this->errorResponse('Collection not found', 404);
        }
        
        $manifest = $this->manifestService->generateCollectionManifest($collection->id);
        
        if (!$manifest) {
            $this->errorResponse('Failed to generate collection manifest', 500);
        }
        
        $this->jsonResponse($manifest);
    }
    
    /**
     * GET /iiif-manifest.php?id={identifier}&format={2|3}
     * Image manifest (legacy compatible)
     */
    public function imageManifest(string $identifier, string $format = '3'): void
    {
        if (empty($identifier)) {
            $this->errorResponse('Missing identifier', 400);
        }
        
        $identifier = preg_replace('/[^a-zA-Z0-9_\-\.\/\[\]]/', '', $identifier);
        
        $manifest = $this->manifestService->generateImageManifest($identifier, ['format' => $format]);
        
        $this->jsonResponse($manifest, 200, $format === '2' ? 'presentation2' : 'presentation');
    }
    
    // ========================================================================
    // Annotation Endpoints
    // ========================================================================
    
    /**
     * GET /iiif/annotations/{canvasId}
     * Get annotation page for a canvas
     */
    public function getAnnotationPage(string $canvasId): void
    {
        $canvasId = urldecode($canvasId);
        
        // Extract object ID from canvas ID
        preg_match('/canvas\/(\d+)/', $canvasId, $matches);
        $objectId = (int)($matches[1] ?? 0);
        
        $page = $this->annotationService->generateAnnotationPage($canvasId, $objectId);
        
        $this->jsonResponse($page, 200, 'annotation');
    }
    
    /**
     * GET /iiif/annotations/object/{objectId}
     * Get all annotations for an object
     */
    public function getObjectAnnotations(int $objectId): void
    {
        $annotations = $this->annotationService->getAnnotationsForObject($objectId);
        
        $formatted = array_map(function($anno) {
            return $this->annotationService->toAnnotoriousFormat((object)$anno);
        }, $annotations);
        
        $this->jsonResponse([
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'type' => 'AnnotationCollection',
            'total' => count($formatted),
            'items' => $formatted
        ], 200, 'annotation');
    }
    
    /**
     * POST /iiif/annotations
     * Create a new annotation
     */
    public function createAnnotation(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['target'])) {
            $this->errorResponse('Invalid annotation data', 400);
        }
        
        // Get object ID from request or parse from target
        $objectId = $input['object_id'] ?? null;
        if (!$objectId) {
            preg_match('/manifest\/([^\/]+)/', $input['target']['source'] ?? $input['target'], $matches);
            if ($matches[1]) {
                $objectId = $this->getObjectIdBySlug($matches[1]);
            }
        }
        
        if (!$objectId) {
            $this->errorResponse('Could not determine object ID', 400);
        }
        
        $data = $this->annotationService->parseAnnotoriousAnnotation($input, $objectId);
        
        // Get user ID if authenticated
        $data['created_by'] = $this->getCurrentUserId();
        
        $annotationId = $this->annotationService->createAnnotation($data);
        
        $annotation = $this->annotationService->getAnnotation($annotationId);
        $formatted = $this->annotationService->toAnnotoriousFormat($annotation);
        
        $this->jsonResponse($formatted, 201, 'annotation');
    }
    
    /**
     * PUT /iiif/annotations/{id}
     * Update an annotation
     */
    public function updateAnnotation(int $annotationId): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->errorResponse('Invalid annotation data', 400);
        }
        
        $existing = $this->annotationService->getAnnotation($annotationId);
        
        if (!$existing) {
            $this->errorResponse('Annotation not found', 404);
        }
        
        $data = $this->annotationService->parseAnnotoriousAnnotation($input, $existing->object_id);
        
        $this->annotationService->updateAnnotation($annotationId, $data);
        
        $annotation = $this->annotationService->getAnnotation($annotationId);
        $formatted = $this->annotationService->toAnnotoriousFormat($annotation);
        
        $this->jsonResponse($formatted, 200, 'annotation');
    }
    
    /**
     * DELETE /iiif/annotations/{id}
     * Delete an annotation
     */
    public function deleteAnnotation(int $annotationId): void
    {
        $existing = $this->annotationService->getAnnotation($annotationId);
        
        if (!$existing) {
            $this->errorResponse('Annotation not found', 404);
        }
        
        $this->annotationService->deleteAnnotation($annotationId);
        
        $this->jsonResponse(['deleted' => true], 200);
    }
    
    // ========================================================================
    // OCR Endpoints
    // ========================================================================
    
    /**
     * GET /iiif/ocr/{digitalObjectId}
     * Get OCR annotation page
     */
    public function getOcrAnnotations(int $digitalObjectId): void
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->first();
        
        if (!$do) {
            $this->errorResponse('Digital object not found', 404);
        }
        
        $canvasId = $this->config['base_url'] . '/iiif/canvas/' . $digitalObjectId;
        
        // Get image dimensions
        $iiifId = $this->manifestService->buildIiifIdentifier($do->path, $do->name);
        $dimensions = $this->manifestService->getImageDimensions($iiifId);
        
        $page = $this->ocrService->generateOcrAnnotationPage($digitalObjectId, $canvasId, $dimensions);
        
        $this->jsonResponse($page, 200, 'annotation');
    }
    
    /**
     * GET /iiif/text/{digitalObjectId}
     * Get plain text content
     */
    public function getTextContent(int $digitalObjectId): void
    {
        $ocr = $this->ocrService->getOcrForDigitalObject($digitalObjectId);
        
        if (!$ocr) {
            $this->errorResponse('No OCR text found', 404);
        }
        
        header('Content-Type: text/plain; charset=UTF-8');
        echo $ocr->full_text;
        exit;
    }
    
    /**
     * GET /iiif/search/{objectId}?q={query}
     * Content search
     */
    public function searchContent(int $objectId): void
    {
        $query = $_GET['q'] ?? '';
        
        if (empty($query)) {
            $this->errorResponse('Missing search query', 400);
        }
        
        // Build canvas map
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get();
        
        $canvasMap = [];
        $pageNum = 1;
        foreach ($digitalObjects as $do) {
            $canvasMap[$pageNum] = $this->config['base_url'] . '/iiif/canvas/' . $do->id;
            $pageNum++;
        }
        
        $response = $this->ocrService->generateSearchResponse($query, $objectId, $canvasMap);
        
        $this->jsonResponse($response);
    }
    
    /**
     * POST /iiif/ocr/import
     * Import OCR data (ALTO or hOCR)
     */
    public function importOcr(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $digitalObjectId = $input['digital_object_id'] ?? null;
        $objectId = $input['object_id'] ?? null;
        $format = $input['format'] ?? 'alto';
        $content = $input['content'] ?? null;
        
        if (!$digitalObjectId || !$objectId || !$content) {
            $this->errorResponse('Missing required fields', 400);
        }
        
        if ($format === 'alto') {
            $ocrId = $this->ocrService->importAlto($digitalObjectId, $objectId, $content);
        } elseif ($format === 'hocr') {
            $ocrId = $this->ocrService->importHocr($digitalObjectId, $objectId, $content);
        } else {
            $ocrId = $this->ocrService->storeOcr($digitalObjectId, $objectId, $content, 'plain');
        }
        
        $this->jsonResponse(['ocr_id' => $ocrId, 'format' => $format], 201);
    }
    
    // ========================================================================
    // Viewer Endpoints
    // ========================================================================
    
    /**
     * GET /iiif/viewer/{objectId}
     * Render viewer HTML
     */
    public function renderViewer(int $objectId): void
    {
        $options = [
            'viewer' => $_GET['viewer'] ?? null,
            'height' => $_GET['height'] ?? null,
        ];
        
        $html = $this->viewerService->renderViewer($objectId, array_filter($options));
        
        $this->htmlResponse($html);
    }
    
    /**
     * GET /iiif/embed/{slug}
     * Embedded viewer (for iframe)
     */
    public function embedViewer(string $slug): void
    {
        $objectId = $this->getObjectIdBySlug($slug);
        
        if (!$objectId) {
            $this->errorResponse('Object not found', 404);
        }
        
        $manifestUrl = $this->config['base_url'] . '/iiif/manifest/' . $slug;
        
        $html = $this->renderEmbedPage($objectId, $manifestUrl);
        
        $this->htmlResponse($html);
    }
    
    /**
     * GET /iiif/list/{slug}
     * List available manifests for an object
     */
    public function listManifests(string $slug): void
    {
        $objectId = $this->getObjectIdBySlug($slug);
        
        if (!$objectId) {
            $this->errorResponse('Object not found', 404);
        }
        
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->select('id', 'name', 'path', 'mime_type')
            ->get()
            ->toArray();
        
        $models3d = [];
        try {
            $models3d = DB::table('object_3d_model')
                ->where('object_id', $objectId)
                ->where('is_public', 1)
                ->select('id', 'filename', 'format')
                ->get()
                ->toArray();
        } catch (\Exception $e) {}
        
        $baseUrl = $this->config['base_url'];
        
        $manifests = [
            'object_slug' => $slug,
            'object_id' => $objectId,
            'iiif_version' => '3.0',
            'manifests' => []
        ];
        
        if (!empty($digitalObjects)) {
            $manifests['manifests'][] = [
                'type' => 'Manifest',
                'format' => 'IIIF Presentation API 3.0',
                'url' => $baseUrl . '/iiif/manifest/' . $slug
            ];
        }
        
        foreach ($digitalObjects as $do) {
            $identifier = str_replace('/', '_SL_', trim($do->path, '/') . '/' . $do->name);
            $manifests['manifests'][] = [
                'type' => 'Image',
                'name' => $do->name,
                'info_json' => $baseUrl . '/iiif/2/' . urlencode($identifier) . '/info.json',
                'manifest' => $baseUrl . '/iiif-manifest.php?id=' . urlencode($identifier)
            ];
        }
        
        foreach ($models3d as $model) {
            $manifests['manifests'][] = [
                'type' => '3D Model',
                'format' => strtoupper($model->format),
                'url' => $baseUrl . '/iiif/3d/' . $model->id . '/manifest.json'
            ];
        }
        
        $this->jsonResponse($manifests);
    }
    
    // ========================================================================
    // Helper Methods
    // ========================================================================
    
    private function getObjectIdBySlug(string $slug): ?int
    {
        $object = DB::table('slug')
            ->where('slug', $slug)
            ->first();
        
        return $object ? $object->object_id : null;
    }
    
    private function getCurrentUserId(): ?int
    {
        // Integrate with Symfony user session if available
        if (class_exists('sfContext')) {
            try {
                $context = \sfContext::getInstance();
                $user = $context->getUser();
                if ($user && $user->isAuthenticated()) {
                    return $user->getUserId();
                }
            } catch (\Exception $e) {}
        }
        
        return null;
    }
    
    private function renderEmbedPage(int $objectId, string $manifestUrl): string
    {
        $baseUrl = $this->config['base_url'];
        $frameworkPath = $this->config['framework_path'] ?? '/atom-framework/src/Extensions/IiifViewer';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IIIF Viewer</title>
    <link rel="stylesheet" href="{$frameworkPath}/public/css/iiif-viewer.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        .iiif-viewer-container { height: 100%; }
        .viewer-area { height: calc(100% - 80px); }
    </style>
</head>
<body>
    <div id="embed-viewer"></div>
    <script src="https://cdn.jsdelivr.net/npm/openseadragon@3.1.0/build/openseadragon/openseadragon.min.js"></script>
    <script type="module">
        import { IiifViewerManager } from '{$frameworkPath}/public/js/iiif-viewer-manager.js';
        
        const viewer = new IiifViewerManager('embed-viewer', {
            objectId: {$objectId},
            manifestUrl: '{$manifestUrl}',
            baseUrl: '{$baseUrl}',
            frameworkPath: '{$frameworkPath}',
            defaultViewer: 'openseadragon',
            embedded: true
        });
        
        viewer.init();
    </script>
</body>
</html>
HTML;
    }
}
