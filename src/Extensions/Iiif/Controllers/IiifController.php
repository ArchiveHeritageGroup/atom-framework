<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Iiif\Controllers;

use AtomFramework\Extensions\Iiif\Services\IiifManifestService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * IIIF Controller - Presentation API 3.0
 * 
 * Handles IIIF manifest requests for images, objects, and collections
 * 
 * @package AtomFramework\Extensions\Iiif
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 3.0.0
 */
class IiifController
{
    private IiifManifestService $manifestService;
    
    public function __construct()
    {
        $this->manifestService = new IiifManifestService();
    }
    
    /**
     * Send JSON response with CORS headers
     */
    private function jsonResponse(array $data, int $status = 200, string $format = '3'): void
    {
        http_response_code($status);
        
        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Content type based on format
        if ($format === '2') {
            header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/2/context.json"');
        } else {
            header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Send error response
     */
    private function errorResponse(string $message, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        echo json_encode(['error' => $message]);
        exit;
    }
    
    /**
     * Handle preflight OPTIONS request
     */
    public function handleOptions(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }
    
    /**
     * Image manifest endpoint
     * 
     * GET /iiif-manifest.php?id={identifier}&format={2|3}
     */
    public function imageManifest(string $identifier, string $format = '3'): void
    {
        if (empty($identifier)) {
            $this->errorResponse('Missing identifier parameter', 400);
        }
        
        // Sanitize identifier
        $identifier = preg_replace('/[^a-zA-Z0-9_\-\.\/\[\]]/', '', $identifier);
        
        if ($format === '2') {
            $manifest = $this->manifestService->generateManifest21($identifier);
        } else {
            $manifest = $this->manifestService->generateImageManifest($identifier);
        }
        
        $this->jsonResponse($manifest, 200, $format);
    }
    
    /**
     * Object manifest endpoint
     * 
     * GET /iiif/manifest/{slug}
     */
    public function objectManifest(string $slug): void
    {
        if (empty($slug)) {
            $this->errorResponse('Missing slug parameter', 400);
        }
        
        // Get object ID from slug
        $object = DB::table('slug')
            ->where('slug', $slug)
            ->first();
        
        if (!$object) {
            $this->errorResponse('Object not found', 404);
        }
        
        $manifest = $this->manifestService->generateObjectManifest($object->object_id);
        
        if (!$manifest) {
            $this->errorResponse('No digital objects found for this record', 404);
        }
        
        $this->jsonResponse($manifest);
    }
    
    /**
     * Collection manifest endpoint
     * 
     * GET /iiif/collection/{slug}
     */
    public function collectionManifest(string $slug): void
    {
        if (empty($slug)) {
            $this->errorResponse('Missing collection slug', 400);
        }
        
        // Get collection
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
     * Get object by slug (helper for integrations)
     */
    public function getObjectBySlug(string $slug): ?int
    {
        $object = DB::table('slug')
            ->where('slug', $slug)
            ->first();
        
        return $object ? $object->object_id : null;
    }
    
    /**
     * List available manifests for an object
     * 
     * GET /iiif/list/{slug}
     */
    public function listManifests(string $slug): void
    {
        $objectId = $this->getObjectBySlug($slug);
        
        if (!$objectId) {
            $this->errorResponse('Object not found', 404);
        }
        
        // Get digital objects
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->select('id', 'name', 'path', 'mime_type')
            ->get()
            ->toArray();
        
        // Get 3D models if available
        $models3d = [];
        try {
            $models3d = DB::table('object_3d_model')
                ->where('object_id', $objectId)
                ->where('is_public', 1)
                ->select('id', 'filename', 'format')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            // Table may not exist
        }
        
        $baseUrl = 'https://archives.theahg.co.za';
        
        $manifests = [
            'object_slug' => $slug,
            'object_id' => $objectId,
            'manifests' => []
        ];
        
        // Main object manifest
        if (!empty($digitalObjects)) {
            $manifests['manifests'][] = [
                'type' => 'Manifest',
                'format' => 'IIIF Presentation API 3.0',
                'url' => $baseUrl . '/iiif/manifest/' . $slug
            ];
        }
        
        // Individual image manifests
        foreach ($digitalObjects as $do) {
            $identifier = str_replace('/', '_SL_', trim($do->path, '/') . '/' . $do->name);
            $manifests['manifests'][] = [
                'type' => 'Image',
                'name' => $do->name,
                'info_json' => $baseUrl . '/iiif/2/' . urlencode($identifier) . '/info.json',
                'manifest' => $baseUrl . '/iiif-manifest.php?id=' . urlencode($identifier)
            ];
        }
        
        // 3D model manifests
        foreach ($models3d as $model) {
            $manifests['manifests'][] = [
                'type' => '3D Model',
                'format' => strtoupper($model->format),
                'url' => $baseUrl . '/iiif/3d/' . $model->id . '/manifest.json'
            ];
        }
        
        $this->jsonResponse($manifests);
    }
}
