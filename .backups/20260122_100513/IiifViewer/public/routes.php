<?php

/**
 * IIIF Routes Handler
 * 
 * Entry point for all IIIF-related requests
 * Place this file at: /usr/share/nginx/archive/atom-framework/src/Extensions/IiifViewer/public/routes.php
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/atom/iiif-error.log');

// Bootstrap framework
$frameworkPath = dirname(__DIR__, 4);
$bootstrapFile = $frameworkPath . '/bootstrap.php';

if (file_exists($bootstrapFile)) {
    require_once $bootstrapFile;
}

// Autoload services
require_once __DIR__ . '/../Services/IiifManifestService.php';
require_once __DIR__ . '/../Services/AnnotationService.php';
require_once __DIR__ . '/../Services/OcrService.php';
require_once __DIR__ . '/../Services/ViewerService.php';
require_once __DIR__ . '/../Controllers/IiifController.php';

use AtomFramework\Extensions\IiifViewer\Controllers\IiifController;

// Get configuration
$config = [
    'base_url' => getenv('IIIF_BASE_URL') ?: 'https://archives.theahg.co.za',
    'cantaloupe_url' => getenv('IIIF_CANTALOUPE_URL') ?: 'https://archives.theahg.co.za/iiif/2',
    'framework_path' => '/atom-framework/src/Extensions/IiifViewer',
];

// Initialize controller
$controller = new IiifController($config);

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string for routing
$path = parse_url($requestUri, PHP_URL_PATH);

// Handle CORS preflight
if ($requestMethod === 'OPTIONS') {
    $controller->handleOptions();
}

// Route definitions
$routes = [
    // Manifest endpoints
    'GET' => [
        // Object manifest: /iiif/manifest/{slug}
        '#^/iiif/manifest/([^/]+)$#' => function($matches) use ($controller) {
            $controller->objectManifest($matches[1]);
        },
        
        // Collection manifest: /iiif/collection/{slug}
        '#^/iiif/collection/([^/]+)$#' => function($matches) use ($controller) {
            $controller->collectionManifest($matches[1]);
        },
        
        // Image manifest (legacy): /iiif-manifest.php
        '#^/iiif-manifest\.php$#' => function($matches) use ($controller) {
            $identifier = $_GET['id'] ?? '';
            $format = $_GET['format'] ?? '3';
            $controller->imageManifest($identifier, $format);
        },
        
        // List manifests: /iiif/list/{slug}
        '#^/iiif/list/([^/]+)$#' => function($matches) use ($controller) {
            $controller->listManifests($matches[1]);
        },
        
        // Annotation page: /iiif/annotations/{canvasId}
        '#^/iiif/annotations/canvas/(.+)$#' => function($matches) use ($controller) {
            $controller->getAnnotationPage($matches[1]);
        },
        
        // Object annotations: /iiif/annotations/object/{objectId}
        '#^/iiif/annotations/object/(\d+)$#' => function($matches) use ($controller) {
            $controller->getObjectAnnotations((int)$matches[1]);
        },
        
        // OCR annotations: /iiif/ocr/{digitalObjectId}
        '#^/iiif/ocr/(\d+)$#' => function($matches) use ($controller) {
            $controller->getOcrAnnotations((int)$matches[1]);
        },
        
        // Plain text: /iiif/text/{digitalObjectId}
        '#^/iiif/text/(\d+)$#' => function($matches) use ($controller) {
            $controller->getTextContent((int)$matches[1]);
        },
        
        // Content search: /iiif/search/{objectId}
        '#^/iiif/search/(\d+)$#' => function($matches) use ($controller) {
            $controller->searchContent((int)$matches[1]);
        },
        
        // Viewer HTML: /iiif/viewer/{objectId}
        '#^/iiif/viewer/(\d+)$#' => function($matches) use ($controller) {
            $controller->renderViewer((int)$matches[1]);
        },
        
        // Embed viewer: /iiif/embed/{slug}
        '#^/iiif/embed/([^/]+)$#' => function($matches) use ($controller) {
            $controller->embedViewer($matches[1]);
        },
        
        // 3D model manifest: /iiif/3d/{id}/manifest.json
        '#^/iiif/3d/(\d+)/manifest\.json$#' => function($matches) use ($controller) {
            // Delegate to 3D manifest handler if exists
            $modelId = (int)$matches[1];
            require_once __DIR__ . '/../../ahg3DModelPlugin/services/Model3DService.php';
            $service = new \AtomFramework\Extensions\ahg3DModel\Services\Model3DService();
            $manifest = $service->generateIiifManifest($modelId);
            
            if ($manifest) {
                header('Content-Type: application/ld+json');
                header('Access-Control-Allow-Origin: *');
                echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '3D model not found']);
            }
            exit;
        },
    ],
    
    'POST' => [
        // Create annotation: /iiif/annotations
        '#^/iiif/annotations$#' => function($matches) use ($controller) {
            $controller->createAnnotation();
        },
        
        // Import OCR: /iiif/ocr/import
        '#^/iiif/ocr/import$#' => function($matches) use ($controller) {
            $controller->importOcr();
        },
    ],
    
    'PUT' => [
        // Update annotation: /iiif/annotations/{id}
        '#^/iiif/annotations/(\d+)$#' => function($matches) use ($controller) {
            $controller->updateAnnotation((int)$matches[1]);
        },
    ],
    
    'DELETE' => [
        // Delete annotation: /iiif/annotations/{id}
        '#^/iiif/annotations/(\d+)$#' => function($matches) use ($controller) {
            $controller->deleteAnnotation((int)$matches[1]);
        },
    ],
];

// Find matching route
$matched = false;

if (isset($routes[$requestMethod])) {
    foreach ($routes[$requestMethod] as $pattern => $handler) {
        if (preg_match($pattern, $path, $matches)) {
            $handler($matches);
            $matched = true;
            break;
        }
    }
}

// 404 if no route matched
if (!$matched) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Not Found',
        'path' => $path,
        'method' => $requestMethod,
        'available_endpoints' => [
            'GET /iiif/manifest/{slug}' => 'Object manifest (IIIF 3.0)',
            'GET /iiif/collection/{slug}' => 'Collection manifest',
            'GET /iiif-manifest.php?id={identifier}' => 'Image manifest',
            'GET /iiif/list/{slug}' => 'List available manifests',
            'GET /iiif/annotations/object/{id}' => 'Object annotations',
            'GET /iiif/ocr/{digitalObjectId}' => 'OCR annotation page',
            'GET /iiif/text/{digitalObjectId}' => 'Plain text content',
            'GET /iiif/search/{objectId}?q={query}' => 'Content search',
            'GET /iiif/viewer/{objectId}' => 'Viewer HTML',
            'GET /iiif/embed/{slug}' => 'Embedded viewer',
            'POST /iiif/annotations' => 'Create annotation',
            'PUT /iiif/annotations/{id}' => 'Update annotation',
            'DELETE /iiif/annotations/{id}' => 'Delete annotation',
        ]
    ], JSON_PRETTY_PRINT);
}
