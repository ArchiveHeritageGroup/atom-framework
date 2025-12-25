<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use AtomExtensions\Controllers\UserDisplayPreferencesController;

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$controller = new UserDisplayPreferencesController();

$action = $_GET['action'] ?? $_POST['action'] ?? 'settings';
$request = array_merge($_GET, $_POST);

try {
    switch ($action) {
        case 'switch':
            $response = $controller->switchMode($request);
            break;

        case 'preferences':
            $response = $controller->save($request);
            break;

        case 'reset':
            $response = $controller->reset($request);
            break;

        case 'show':
            $module = $request['module'] ?? 'search';
            $response = $controller->show($module);
            break;

        case 'index':
        case 'settings':
        default:
            $response = $controller->index();
            break;
    }
} catch (\Exception $e) {
    $response = [
        'success' => false,
        'error' => 'An error occurred',
    ];
    error_log('DisplayMode API Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_THROW_ON_ERROR);
