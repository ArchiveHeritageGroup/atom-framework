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
if (!defined('ATOM_FRAMEWORK_PATH')) { define('ATOM_FRAMEWORK_PATH', __DIR__); }
if (!defined('ATOM_ROOT_PATH')) { define('ATOM_ROOT_PATH', dirname(__DIR__)); }

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

// Global helper functions for term lookups (replaces QubitTerm::getById for read-only access).
if (!function_exists('term_name')) {
    function term_name($id, ?string $culture = null): string
    {
        return \AtomFramework\Helpers\TermHelper::name($id, $culture);
    }
}
if (!function_exists('term_exists')) {
    function term_exists($id): bool
    {
        return \AtomFramework\Helpers\TermHelper::exists($id);
    }
}

// Detect execution context:
// - CLI mode: bin/atom sets ATOM_CLI_MODE
// - Symfony web mode: sfActions loaded later by Symfony dispatcher
// - Standalone web mode: heratio.php — no sfActions, not CLI
$isCliMode = defined('ATOM_CLI_MODE');

if (!$isCliMode && defined('HERATIO_STANDALONE')) {
    // Standalone web (heratio.php) — load standalone template helpers.
    // Symfony's UrlHelper.php etc. do NOT use function_exists() guards,
    // so we must ONLY load these when running through heratio.php.
    require_once __DIR__ . '/src/Helpers/TemplateHelpers.php';

    // Load Blade helpers (__(), atom_url, csp_nonce_attr, etc.)
    // and additional shims (decorate_with, esc_specialchars, etc.)
    // for PHP templates rendered outside BladeRenderer.
    require_once __DIR__ . '/src/Views/blade_helpers.php';
    require_once __DIR__ . '/src/Views/blade_shims.php';

    // Register BladeRenderer alias (no sfActions dependency)
    if (!class_exists('BladeRenderer', false)) {
        class_alias(\AtomFramework\Views\BladeRenderer::class, 'BladeRenderer');
    }
}

// Lazy class alias registration via autoloader — avoids eagerly loading
// sfActions/sfComponent at bootstrap time, which conflicts with Symfony's
// cache compilation that redeclares sfComponent later.
spl_autoload_register(function ($class) {
    static $aliasMap = [
        'AhgActions'    => 'AtomFramework\\Actions\\AhgActions',
        'AhgComponents' => 'AtomFramework\\Actions\\AhgComponents',
        'AhgTask'       => 'AtomFramework\\Actions\\AhgTask',
        'BladeRenderer' => 'AtomFramework\\Views\\BladeRenderer',
    ];

    if (isset($aliasMap[$class])) {
        if (class_exists($aliasMap[$class], true)) {
            class_alias($aliasMap[$class], $class);
        }
    }
});
