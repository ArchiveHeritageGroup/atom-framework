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

/**
 * Parse database connection config from AtoM's config.php.
 *
 * Returns an array suitable for Illuminate Capsule::addConnection(),
 * or null if the config file is missing/invalid.
 */
function atomParseDbConfig(string $rootPath): ?array
{
    $configFile = $rootPath . '/config/config.php';
    if (!file_exists($configFile)) {
        return null;
    }

    $config = require $configFile;
    if (!isset($config['all']['propel']['param'])) {
        return null;
    }

    $dbConfig = $config['all']['propel']['param'];
    $dsn = $dbConfig['dsn'] ?? '';

    $database = 'atom';
    if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
        $database = $matches[1];
    }

    $host = 'localhost';
    if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
        $host = $matches[1];
    }

    $port = 3306;
    if (preg_match('/port=([^;]+)/', $dsn, $matches)) {
        $port = (int) $matches[1];
    }

    return [
        'driver' => 'mysql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $dbConfig['username'] ?? 'atom',
        'password' => $dbConfig['password'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ];
}

$dbConnection = atomParseDbConfig(ATOM_ROOT_PATH);
if (null !== $dbConnection) {
    $capsule = new Capsule();
    $capsule->addConnection($dbConnection);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
}

// Detect standalone CLI mode (bin/atom sets ATOM_CLI_MODE).
// In CLI mode: skip Symfony-dependent aliases, load standalone helpers.
// In web mode: register aliases (Symfony classes available), skip helpers (Symfony provides them).
$isCliMode = defined('ATOM_CLI_MODE');

if (!$isCliMode) {
    // Web context — Symfony is (or will be) loaded. Register class aliases.
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
} else {
    // CLI context — load standalone template helpers since Symfony won't provide them.
    // Symfony's AssetHelper.php etc. do NOT use function_exists() guards,
    // so we must ONLY load these when Symfony is NOT present.
    require_once __DIR__ . '/src/Helpers/TemplateHelpers.php';
}
