<?php

/**
 * IIIF Viewer Framework Router
 * 
 * Routes all IIIF-related requests to the appropriate controller method
 * Place in: /atom-framework/src/Extensions/IiifViewer/public/router.php
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 */

// Bootstrap
require_once dirname(__DIR__, 4) . '/bootstrap.php';

// Get base URL from AtoM settings
function getAtomBaseUrl(): string {
    try {
        $result = \Illuminate\Database\Capsule\Manager::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'siteBaseUrl')
            ->first();
        if ($result && !empty($result->value)) {
            return rtrim($result->value, '/');
        }
    } catch (\Exception $e) {}
    return 'https://psis.theahg.co.za';
}


use AtomFramework\Extensions\IiifViewer\Controllers\IiifController;
use AtomFramework\Extensions\IiifViewer\Controllers\MediaController;

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path if present
$basePath = '/atom-framework/src/Extensions/IiifViewer/public';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Also handle /iiif and /media prefixes
if (strpos($path, '/iiif') === 0) {
    $path = substr($path, 5);
} elseif (strpos($path, '/media') === 0) {
    $path = substr($path, 6);
    $isMediaRoute = true;
}

$isMediaRoute = $isMediaRoute ?? false;

// Initialize controllers
$config = [
    'base_url' => getAtomBaseUrl(),
    'cantaloupe_url' => getAtomBaseUrl() . '/iiif/2',
];

$iiifController = new IiifController($config);
$mediaController = new MediaController($config);

// Handle OPTIONS preflight
if ($requestMethod === 'OPTIONS') {
    $iiifController->handleOptions();
    exit;
}

// Route matching
try {
    // ========================================
    // Media Routes
    // ========================================
    
    // GET /media/lookup/{slug}
    if (preg_match('#^/lookup/([^/]+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->lookup($matches[1]);
        exit;
    }

    // GET /media/lookup/{slug}
    if (preg_match('#^/lookup/([^/]+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->lookup($matches[1]);
    }

    // GET /media/metadata/{digitalObjectId}
    if (preg_match('#^/metadata/(\d+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getMetadata((int)$matches[1]);
    }
    
    // POST /media/extract/{digitalObjectId}
    elseif (preg_match('#^/extract/(\d+)$#', $path, $matches) && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->extractMetadata((int)$matches[1]);
    }
    
    // GET /media/waveform/{digitalObjectId}
    elseif (preg_match('#^/waveform/(\d+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getWaveform((int)$matches[1]);
    }
    
    // GET /media/transcription/{digitalObjectId}
    elseif (preg_match('#^/transcription/(\d+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getTranscription((int)$matches[1]);
    }
    
    // POST /media/transcribe/{digitalObjectId}
    elseif (preg_match('#^/transcribe/(\d+)$#', $path, $matches) && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->transcribe((int)$matches[1]);
    }
    
    // GET /media/transcription/{digitalObjectId}/vtt
    elseif (preg_match('#^/transcription/(\d+)/vtt$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getVtt((int)$matches[1]);
    }
    
    // GET /media/transcription/{digitalObjectId}/srt
    elseif (preg_match('#^/transcription/(\d+)/srt$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getSrt((int)$matches[1]);
    }
    
    // GET /media/transcription/{digitalObjectId}/iiif
    elseif (preg_match('#^/transcription/(\d+)/iiif$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getIiifTranscription((int)$matches[1]);
    }
    
    // GET /media/search
    elseif ($path === '/search' && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->searchTranscriptions();
    }
    
    // GET /media/search/{digitalObjectId}/timestamps
    elseif (preg_match('#^/search/(\d+)/timestamps$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->searchWithTimestamps((int)$matches[1]);
    }
    
    // POST /media/queue
    elseif ($path === '/queue' && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->addToQueue();
    }
    
    // GET /media/queue/status/{queueId}
    elseif (preg_match('#^/queue/status/(\d+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getQueueStatus((int)$matches[1]);
    }
    
    // POST /media/batch/extract
    elseif ($path === '/batch/extract' && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->batchExtract();
    }
    
    // POST /media/batch/transcribe
    elseif ($path === '/batch/transcribe' && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->batchTranscribe();
    }
    
    // GET /media/status
    elseif ($path === '/status' && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getStatus();
    }

    // ========================================
    // Snippet Routes
    // ========================================
    
    // GET /media/snippets/{digitalObjectId}
    if (preg_match('#^/snippets/(\d+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getSnippets((int)$matches[1]);
    }
    
    // POST /media/snippets
    elseif ($path === '/snippets' && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->createSnippet();
    }
    
    // PUT /media/snippets/{snippetId}
    elseif (preg_match('#^/snippets/(\d+)$#', $path, $matches) && $requestMethod === 'PUT' && $isMediaRoute) {
        $mediaController->updateSnippet((int)$matches[1]);
    }
    
    // DELETE /media/snippets/{snippetId}
    elseif (preg_match('#^/snippets/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE' && $isMediaRoute) {
        $mediaController->deleteSnippet((int)$matches[1]);
    }
    
    // POST /media/snippets/{snippetId}/export
    elseif (preg_match('#^/snippets/(\d+)/export$#', $path, $matches) && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->exportSnippet((int)$matches[1]);
    }
    
    // GET /media/derivatives/{digitalObjectId}
    elseif (preg_match('#^/derivatives/(\d+)$#', $path, $matches) && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getDerivatives((int)$matches[1]);
    }
    
    // POST /media/process/{digitalObjectId}
    elseif (preg_match('#^/process/(\d+)$#', $path, $matches) && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->processUpload((int)$matches[1]);
    }
    
    // GET /media/settings
    elseif ($path === '/settings' && $requestMethod === 'GET' && $isMediaRoute) {
        $mediaController->getSettings();
    }
    
    // POST /media/settings
    elseif ($path === '/settings' && $requestMethod === 'POST' && $isMediaRoute) {
        $mediaController->saveSettings();
    }

    // ========================================
    // IIIF Manifest Routes
    // ========================================
    
    // GET /manifest/{slug}
    elseif (preg_match('#^/manifest/([^/]+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->objectManifest($matches[1]);
    }
    
    // GET /collection/{slug}
    elseif (preg_match('#^/collection/([^/]+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->collectionManifest($matches[1]);
    }
    
    // GET /list/{slug}
    elseif (preg_match('#^/list/([^/]+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->listManifests($matches[1]);
    }
    
    // ========================================
    // Annotation Routes
    // ========================================
    
    // GET /annotations/{canvasId}
    elseif (preg_match('#^/annotations/([^/]+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->getAnnotationPage($matches[1]);
    }
    
    // GET /annotations/object/{objectId}
    elseif (preg_match('#^/annotations/object/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->getObjectAnnotations((int)$matches[1]);
    }
    
    // POST /annotations
    elseif ($path === '/annotations' && $requestMethod === 'POST') {
        $iiifController->createAnnotation();
    }
    
    // PUT /annotations/{id}
    elseif (preg_match('#^/annotations/(\d+)$#', $path, $matches) && $requestMethod === 'PUT') {
        $iiifController->updateAnnotation((int)$matches[1]);
    }
    
    // DELETE /annotations/{id}
    elseif (preg_match('#^/annotations/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
        $iiifController->deleteAnnotation((int)$matches[1]);
    }
    
    // ========================================
    // OCR Routes
    // ========================================
    
    // GET /ocr/{digitalObjectId}
    elseif (preg_match('#^/ocr/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->getOcrAnnotations((int)$matches[1]);
    }
    
    // GET /text/{digitalObjectId}
    elseif (preg_match('#^/text/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->getTextContent((int)$matches[1]);
    }
    
    // GET /search/{objectId}
    elseif (preg_match('#^/search/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->searchContent((int)$matches[1]);
    }
    
    // POST /ocr/import
    elseif ($path === '/ocr/import' && $requestMethod === 'POST') {
        $iiifController->importOcr();
    }
    
    // ========================================
    // Viewer Routes
    // ========================================
    
    // GET /viewer/{objectId}
    elseif (preg_match('#^/viewer/(\d+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->renderViewer((int)$matches[1]);
    }
    
    // GET /embed/{slug}
    elseif (preg_match('#^/embed/([^/]+)$#', $path, $matches) && $requestMethod === 'GET') {
        $iiifController->embedViewer($matches[1]);
    }
    
    // ========================================
    // Legacy iiif-manifest.php compatibility
    // ========================================
    
    elseif ($path === '/image' || $path === '' || $path === '/') {
        $identifier = $_GET['id'] ?? '';
        $format = $_GET['format'] ?? '3';
        
        if ($identifier) {
            $iiifController->imageManifest($identifier, $format);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Missing identifier parameter']);
        }
    }
    
    // ========================================
    // 404 Not Found
    // ========================================
    
    else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Route not found',
            'path' => $path,
            'method' => $requestMethod,
            'available_routes' => [
                '--- IIIF Routes ---',
                'GET /iiif/manifest/{slug}' => 'Object manifest',
                'GET /iiif/collection/{slug}' => 'Collection manifest',
                'GET /iiif/annotations/{canvasId}' => 'Annotation page',
                'GET /iiif/ocr/{digitalObjectId}' => 'OCR annotation page',
                'GET /iiif/viewer/{objectId}' => 'Render viewer HTML',
                '--- Media Routes ---',
                'GET /media/metadata/{id}' => 'Get media metadata',
                'POST /media/extract/{id}' => 'Extract media metadata',
                'GET /media/waveform/{id}' => 'Get audio waveform',
                'GET /media/transcription/{id}' => 'Get transcription',
                'POST /media/transcribe/{id}' => 'Transcribe audio/video',
                'GET /media/transcription/{id}/vtt' => 'Get VTT subtitles',
                'GET /media/transcription/{id}/srt' => 'Get SRT subtitles',
                'GET /media/transcription/{id}/iiif' => 'Get IIIF annotations',
                'GET /media/search?q=' => 'Search transcriptions',
                'GET /media/status' => 'Service status',
            ]
        ]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
    
    error_log('Router Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
}
