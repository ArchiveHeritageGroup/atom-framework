<?php
/**
 * Media streaming routes - Standalone PHP
 */

// Determine base path dynamically
$basePath = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

// Try to load config
$configFile = $basePath . '/config/config.php';
if (file_exists($configFile)) {
    // Parse database config from AtoM's config.php
    $configContent = file_get_contents($configFile);
    // ... config parsing logic
}

// Set paths dynamically
$uploadsDir = $basePath . '/uploads';
$cacheDir = $basePath . '/cache/transcoded';
$exportDir = $basePath . '/cache/exports';

// Ensure directories exist
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
if (!is_dir($exportDir)) {
    @mkdir($exportDir, 0755, true);
}

// Route handling
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathInfo = parse_url($requestUri, PHP_URL_PATH);

// ... rest of routing logic using $basePath, $uploadsDir, etc.
