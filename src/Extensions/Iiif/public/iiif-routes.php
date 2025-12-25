<?php

declare(strict_types=1);

/**
 * IIIF 3.0 Routes Handler
 * 
 * Entry point for IIIF manifest requests
 * Place this file in the atom-framework public directory
 * 
 * Routes:
 *   GET /iiif/manifest/{slug}     - Object manifest (IIIF 3.0)
 *   GET /iiif/collection/{slug}   - Collection manifest (IIIF 3.0)
 *   GET /iiif/list/{slug}         - List available manifests
 *   GET /iiif-manifest.php?id=x   - Image manifest (legacy compatible)
 * 
 * @package AtomFramework\Extensions\Iiif
 */

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

// Determine framework root
$frameworkRoot = dirname(__DIR__);

// Bootstrap Laravel
$bootstrapPath = $frameworkRoot . '/bootstrap.php';
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
}

// Autoload services
require_once $frameworkRoot . '/src/Extensions/Iiif/Services/IiifManifestService.php';
require_once $frameworkRoot . '/src/Extensions/Iiif/Controllers/IiifController.php';

use AtomFramework\Extensions\Iiif\Controllers\IiifController;

// Initialize controller
$controller = new IiifController();

// Parse request URI
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Route matching
try {
    // Object manifest: /iiif/manifest/{slug}
    if (preg_match('#^/iiif/manifest/([^/?]+)#', $path, $matches)) {
        $controller->objectManifest($matches[1]);
    }
    
    // Collection manifest: /iiif/collection/{slug}
    elseif (preg_match('#^/iiif/collection/([^/?]+)#', $path, $matches)) {
        $controller->collectionManifest($matches[1]);
    }
    
    // List manifests: /iiif/list/{slug}
    elseif (preg_match('#^/iiif/list/([^/?]+)#', $path, $matches)) {
        $controller->listManifests($matches[1]);
    }
    
    // Legacy image manifest: /iiif-manifest.php?id={identifier}
    elseif (strpos($path, 'iiif-manifest') !== false || isset($_GET['id'])) {
        $identifier = $_GET['id'] ?? '';
        $format = $_GET['format'] ?? '3';
        $controller->imageManifest($identifier, $format);
    }
    
    // Not found
    else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Route not found',
            'available_routes' => [
                '/iiif/manifest/{slug}' => 'Object manifest (IIIF 3.0)',
                '/iiif/collection/{slug}' => 'Collection manifest (IIIF 3.0)',
                '/iiif/list/{slug}' => 'List available manifests',
                '/iiif-manifest.php?id={identifier}' => 'Image manifest'
            ]
        ]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
