<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bootstrap.php';

use AtomExtensions\Controllers\AdminDisplaySettingsController;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Check admin authorization
if (class_exists('sfContext') && sfContext::hasInstance()) {
    $user = sfContext::getInstance()->getUser();
    if (!$user || !$user->isAuthenticated() || !$user->hasCredential('administrator')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

$controller = new AdminDisplaySettingsController();

$action = $_GET['action'] ?? $_POST['action'] ?? $_POST['form_action'] ?? 'index';
$request = array_merge($_GET, $_POST);

try {
    switch ($action) {
        case 'update':
            $response = $controller->update($request);
            break;

        case 'reset':
            $response = $controller->reset($request);
            break;

        case 'show':
            $module = $request['module'] ?? '';
            $response = $controller->show($module);
            break;

        case 'audit':
            $response = $controller->auditLog($request);
            break;

        case 'index':
        default:
            $response = $controller->index();
            break;
    }
} catch (\Exception $e) {
    $response = [
        'success' => false,
        'error' => 'An error occurred',
    ];
    error_log('Admin DisplaySettings API Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_THROW_ON_ERROR);
