<?php
/**
 * AtoM Framework Bootstrap
 */

// Prevent multiple initialization
if (defined('ATOM_FRAMEWORK_LOADED')) {
    return;
}
define('ATOM_FRAMEWORK_LOADED', true);

// Framework paths
define('ATOM_FRAMEWORK_PATH', __DIR__);
define('ATOM_ROOT_PATH', dirname(__DIR__));

// Load Composer autoloader and register namespaces via PSR-4
$loader = require __DIR__ . '/vendor/autoload.php';

// Register both namespaces
$loader->addPsr4('AtomExtensions\\', __DIR__ . '/src/');
$loader->addPsr4('AtomFramework\\', __DIR__ . '/src/');

// Initialize Laravel Database (Capsule)
use Illuminate\Database\Capsule\Manager as Capsule;

$configFile = ATOM_ROOT_PATH . '/config/config.php';

if (file_exists($configFile)) {
    $config = require $configFile;
    
    if (isset($config['all']['propel']['param'])) {
        $dbConfig = $config['all']['propel']['param'];
        
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $dbConfig['host'] ?? 'localhost',
            'database' => $dbConfig['database'] ?? 'atom',
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
