<?php
/**
 * AtoM Heratio Web Configuration Wizard
 * Standalone PHP app - runs on PHP built-in server, no framework dependencies
 */

require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/SystemCheck.php';

// Authenticate
if (!Auth::check()) {
    http_response_code(401);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body>';
    echo '<h1>Access Denied</h1>';
    echo '<p>A valid access token is required. Check the terminal output for the token URL.</p>';
    echo '<p>Usage: <code>http://server:port?token=YOUR_TOKEN</code></p>';
    echo '</body></html>';
    exit;
}

$token = Auth::getToken();
$step = max(1, min(7, (int)($_GET['step'] ?? 1)));

// Load the requested step view
$viewFile = __DIR__ . "/views/step{$step}.php";
if (!file_exists($viewFile)) {
    $viewFile = __DIR__ . '/views/step1.php';
    $step = 1;
}

// Get system status for step 1
$systemChecks = ($step === 1) ? SystemCheck::getAll() : [];

// Render layout
require __DIR__ . '/views/layout.php';
