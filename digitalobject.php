<?php

/**
 * Digital Object Upload Handler
 * 
 * Standalone entry point for Laravel-based digital object management.
 * This bypasses Symfony routing for direct access.
 */

// Bootstrap AtoM
require_once dirname(__DIR__) . '/config/ProjectConfiguration.class.php';
$configuration = ProjectConfiguration::getApplicationConfiguration('qubit', 'prod', false);
sfContext::createInstance($configuration);

// Bootstrap Laravel components
require_once __DIR__ . '/bootstrap.php';

use AtomExtensions\Controllers\DigitalObjectController;

// Simple router
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse the path
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace('/atom-framework/digitalobject', '', $path);

$controller = new DigitalObjectController();

try {
    // Route: GET /add/{objectId}
    if ($method === 'GET' && preg_match('#^/add/(\d+)$#', $path, $matches)) {
        echo $controller->showAddForm((int) $matches[1]);
        exit;
    }
    
    // Route: POST /upload
    if ($method === 'POST' && $path === '/upload') {
        // Create a simple request wrapper
        $request = new class {
            public function input($key, $default = null) {
                return $_POST[$key] ?? $default;
            }
            public function boolean($key, $default = false) {
                $value = $_POST[$key] ?? $default;
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            public function hasFile($key) {
                return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
            }
            public function file($key) {
                if (!$this->hasFile($key)) return null;
                return $_FILES[$key];
            }
        };
        
        echo $controller->handleUpload($request);
        exit;
    }
    
    // 404
    http_response_code(404);
    echo "Route not found: $method $path";
    
} catch (Exception $e) {
    error_log("DigitalObject error: " . $e->getMessage());
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
