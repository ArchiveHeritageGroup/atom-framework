<?php
/**
 * AJAX API endpoint for wizard actions
 */

header('Content-Type: application/json');

require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/SystemCheck.php';
require_once __DIR__ . '/lib/PluginManager.php';
require_once __DIR__ . '/lib/ConfigWriter.php';
require_once __DIR__ . '/lib/ServiceManager.php';

Auth::requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$atomPath = ini_get('atom_heratio.atom_path') ?: '/usr/share/nginx/atom';

try {
    switch ($action) {
        case 'system-check':
            echo json_encode(['status' => 'ok', 'checks' => SystemCheck::getAll()]);
            break;

        case 'get-plugins':
            $pm = new PluginManager($atomPath);
            echo json_encode(['status' => 'ok', 'catalog' => $pm->getCatalog()]);
            break;

        case 'set-plugins':
            $pm = new PluginManager($atomPath);
            $enable = json_decode($_POST['enable'] ?? '[]', true) ?: [];
            $disable = json_decode($_POST['disable'] ?? '[]', true) ?: [];
            $results = $pm->setPlugins($enable, $disable);
            echo json_encode(['status' => 'ok', 'results' => $results]);
            break;

        case 'save-settings':
            $cw = new ConfigWriter();
            $settings = json_decode($_POST['settings'] ?? '{}', true) ?: [];
            $group = $_POST['group'] ?? 'general';
            $count = $cw->setMultiple($settings, $group);
            echo json_encode(['status' => 'ok', 'saved' => $count]);
            break;

        case 'get-settings':
            $cw = new ConfigWriter();
            $group = $_GET['group'] ?? $_POST['group'] ?? 'general';
            $settings = $cw->getGroup($group);
            echo json_encode(['status' => 'ok', 'settings' => $settings]);
            break;

        case 'restart-services':
            $results = ServiceManager::restartWebServices();
            echo json_encode(['status' => 'ok', 'results' => $results]);
            break;

        case 'clear-cache':
            $result = ServiceManager::clearCaches();
            echo json_encode(['status' => $result ? 'ok' : 'error']);
            break;

        case 'apply':
            // Final apply: restart services and clear caches
            ServiceManager::clearCaches();
            $svcResults = ServiceManager::restartWebServices();
            echo json_encode([
                'status' => 'ok',
                'message' => 'Configuration applied successfully',
                'services' => $svcResults,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
