<?php
/**
 * AtoM Framework Bootstrap
 *
 * Core framework initialization only.
 * Plugin-specific logic (audit, security, etc.) should be in their respective plugins.
 */
if (defined('ATOM_FRAMEWORK_LOADED')) {
    return;
}
define('ATOM_FRAMEWORK_LOADED', true);
define('ATOM_FRAMEWORK_PATH', __DIR__);
define('ATOM_ROOT_PATH', dirname(__DIR__));

$loader = require __DIR__ . '/vendor/autoload.php';
$loader->addPsr4('AtomFramework\\', __DIR__ . '/src/');
$loader->addPsr4('AtomExtensions\\', __DIR__ . '/src/');

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

// Register global class aliases for non-namespaced plugin action files
if (!class_exists('AhgActions', false)) {
    class_alias(\AtomFramework\Actions\AhgActions::class, 'AhgActions');
}
if (!class_exists('AhgComponents', false)) {
    class_alias(\AtomFramework\Actions\AhgComponents::class, 'AhgComponents');
}
if (!class_exists('AhgTask', false)) {
    class_alias(\AtomFramework\Actions\AhgTask::class, 'AhgTask');
}
if (!class_exists('BladeRenderer', false)) {
    class_alias(\AtomFramework\Views\BladeRenderer::class, 'BladeRenderer');
}
