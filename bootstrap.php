<?php
/**
 * AtoM Framework Bootstrap
 * 
 * Initializes Laravel Query Builder and autoloading
 */

// Prevent multiple initialization
if (defined('ATOM_FRAMEWORK_LOADED')) {
    return;
}
define('ATOM_FRAMEWORK_LOADED', true);

// Framework paths
define('ATOM_FRAMEWORK_PATH', __DIR__);
define('ATOM_ROOT_PATH', dirname(__DIR__));

// Load Composer autoloader if exists
$composerAutoloader = ATOM_FRAMEWORK_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoloader)) {
    require_once $composerAutoloader;
}

// PSR-4 Autoloader for framework classes
spl_autoload_register(function ($class) {
    $prefixes = [
        'AtomFramework\\' => ATOM_FRAMEWORK_PATH . '/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }

    return false;
});

// Initialize Laravel Database (Capsule)
use Illuminate\Database\Capsule\Manager as Capsule;

// Default database config
$dbConfig = [
    'host' => 'localhost',
    'database' => 'archive',
    'username' => 'root',
    'password' => '',
];

// Load from AtoM's config.php
$configFile = ATOM_ROOT_PATH . '/config/config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    
    // AtoM config structure: $config['all']['propel']['param']
    if (isset($config['all']['propel']['param'])) {
        $param = $config['all']['propel']['param'];
        
        $dbConfig['username'] = $param['username'] ?? 'root';
        $dbConfig['password'] = $param['password'] ?? '';
        
        // Parse DSN: mysql:dbname=archive;port=3306
        if (isset($param['dsn'])) {
            if (preg_match('/host=([^;]+)/', $param['dsn'], $m)) {
                $dbConfig['host'] = $m[1];
            }
            if (preg_match('/dbname=([^;]+)/', $param['dsn'], $m)) {
                $dbConfig['database'] = $m[1];
            }
        }
    }
}

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $dbConfig['host'],
    'database' => $dbConfig['database'],
    'username' => $dbConfig['username'],
    'password' => $dbConfig['password'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Global helper functions
if (!function_exists('ahg_icon')) {
    function ahg_icon(string $key, array $attributes = []): string {
        return \AtomFramework\Helpers\IconHelper::render($key, $attributes);
    }
}

if (!function_exists('ahg_theme')) {
    function ahg_theme(): string {
        return \AtomFramework\Helpers\ThemeHelper::current();
    }
}
