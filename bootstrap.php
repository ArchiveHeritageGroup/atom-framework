<?php
/**
 * AtoM Framework Bootstrap
 */
if (defined('ATOM_FRAMEWORK_LOADED')) {
    return;
}
define('ATOM_FRAMEWORK_LOADED', true);
define('ATOM_FRAMEWORK_PATH', __DIR__);
define('ATOM_ROOT_PATH', dirname(__DIR__));

$loader = require __DIR__ . '/vendor/autoload.php';
$loader->addPsr4('AtomExtensions\\', __DIR__ . '/src/');
$loader->addPsr4('AtomFramework\\', __DIR__ . '/src/');

use Illuminate\Database\Capsule\Manager as Capsule;

$configFile = ATOM_ROOT_PATH . '/config/config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    if (isset($config['all']['propel']['param'])) {
        $dbConfig = $config['all']['propel']['param'];

        // Parse database name from DSN
        $dsn = $dbConfig['dsn'] ?? '';
        $database = 'atom';
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            $database = $matches[1];
        }

        // Parse host from DSN
        $host = 'localhost';
        if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
            $host = $matches[1];
        }

        // Parse port from DSN
        $port = 3306;
        if (preg_match('/port=([^;]+)/', $dsn, $matches)) {
            $port = (int)$matches[1];
        }

        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $dbConfig['username'] ?? 'atom',
            'password' => $dbConfig['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}

// Register shutdown function to log audit after request completes
register_shutdown_function(function() {
    try {
        if (!class_exists('sfContext') || !sfContext::hasInstance()) {
            return;
        }
        
        $context = sfContext::getInstance();
        $request = $context->getRequest();
        $module = $context->getModuleName();
        $action = $context->getActionName();
        
        // Only log POST/PUT/DELETE
        if (!$request->isMethod('POST') && !$request->isMethod('PUT') && !$request->isMethod('DELETE')) {
            return;
        }
        
        // Skip non-auditable modules
        $auditableModules = ['informationobject', 'actor', 'repository', 'term', 'taxonomy',
            'accession', 'deaccession', 'donor', 'rightsholder', 'user', 'aclGroup', 'staticpage',
            'ahgMuseumPlugin', 'sfMuseumPlugin', 'ahgAuditTrail'];
        
        if (!in_array($module, $auditableModules)) {
            return;
        }
        
        // Check if audit enabled
        $enabled = \Illuminate\Database\Capsule\Manager::table('ahg_audit_settings')
            ->where('setting_key', 'audit_enabled')
            ->value('setting_value');
        
        if ($enabled !== '1') {
            return;
        }
        
        $user = $context->getUser();
        $userId = $user->isAuthenticated() ? $user->getAttribute('user_id') : null;
        $username = $user->isAuthenticated() ? ($user->getAttribute('username') ?? 'unknown') : 'anonymous';
        
        \Illuminate\Database\Capsule\Manager::table('ahg_audit_log')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'username' => $username,
            'action' => $action,
            'entity_type' => $module,
            'module' => $module,
            'action_name' => $action,
            'request_method' => $request->getMethod(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {
        // Silent fail
    }
});
