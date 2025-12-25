<?php

/**
 * AHG Framework Bootstrap.
 *
 * Initializes Laravel Query Builder and registers all framework namespaces.
 * This replaces Qubit/Propel with modern Laravel-based services.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

// Prevent double loading
if (defined('AHG_FRAMEWORK_LOADED')) {
    return;
}
define('AHG_FRAMEWORK_LOADED', true);

// Load Composer autoloader
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

use Illuminate\Database\Capsule\Manager as Capsule;

// =============================================================================
// DATABASE INITIALIZATION
// =============================================================================

$capsule = new Capsule();

// Get database config from AtoM's config.php
$dbConfig = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'archive',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
];

$configFile = dirname(__DIR__) . '/config/config.php';
if (file_exists($configFile)) {
    $config = include $configFile;
    if (isset($config['all']['propel']['param'])) {
        $param = $config['all']['propel']['param'];
        
        // Parse DSN
        if (isset($param['dsn'])) {
            if (preg_match('/dbname=(\w+)/', $param['dsn'], $m)) {
                $dbConfig['database'] = $m[1];
            }
            if (preg_match('/host=([\w\.]+)/', $param['dsn'], $m)) {
                $dbConfig['host'] = $m[1];
            }
            if (preg_match('/port=(\d+)/', $param['dsn'], $m)) {
                $dbConfig['port'] = (int)$m[1];
            }
        }
        
        if (isset($param['username'])) {
            $dbConfig['username'] = $param['username'];
        }
        if (isset($param['password'])) {
            $dbConfig['password'] = $param['password'];
        }
        if (isset($param['encoding'])) {
            $dbConfig['charset'] = $param['encoding'];
        }
    }
}
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $dbConfig['host'] ?? '127.0.0.1',
    'database' => $dbConfig['database'] ?? 'archive',
    'username' => $dbConfig['username'] ?? 'root',
    'password' => $dbConfig['password'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
    'engine' => null,
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// =============================================================================
// PSR-4 AUTOLOADER REGISTRATION
// =============================================================================

spl_autoload_register(function ($class) {
    // Handle AtomExtensions namespace
    $prefix = 'AtomExtensions\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) === 0) {
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Handle AtomFramework namespace
    $prefix = 'AtomFramework\\';
    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

if (!function_exists('now')) {
    /**
     * Get current timestamp.
     */
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('ahg_setting')) {
    /**
     * Get AHG setting value.
     */
    function ahg_setting(string $key, $default = null)
    {
        return \AtomExtensions\Services\SettingService::getValue($key) ?? $default;
    }
}

if (!function_exists('ahg_cache')) {
    /**
     * Get cache service instance.
     */
    function ahg_cache(): \AtomExtensions\Services\CacheService
    {
        return \AtomExtensions\Services\CacheService::getInstance();
    }
}

if (!function_exists('ahg_user')) {
    /**
     * Get current user.
     */
    function ahg_user(): ?object
    {
        return \AtomExtensions\Services\AclService::getUser();
    }
}

if (!function_exists('ahg_can')) {
    /**
     * Check if current user can perform action on resource.
     */
    function ahg_can(?object $resource, string $action): bool
    {
        return \AtomExtensions\Services\AclService::check($resource, $action);
    }
}

if (!function_exists('ahg_term')) {
    /**
     * Get term by ID.
     */
    function ahg_term(int $id): ?object
    {
        return \AtomExtensions\Services\TermService::getById($id);
    }
}

// =============================================================================
// CULTURE INITIALIZATION
// =============================================================================

// Set culture from sfContext if available
if (class_exists('sfContext') && sfContext::hasInstance()) {
    $culture = sfContext::getInstance()->getUser()->getCulture();
    \AtomExtensions\Services\SettingService::setCulture($culture);
    \AtomExtensions\Services\TaxonomyService::setCulture($culture);
    \AtomExtensions\Services\TermService::setCulture($culture);
    \AtomExtensions\Services\AclGroupService::setCulture($culture);

    // Set current user for ACL
    $sfUser = sfContext::getInstance()->getUser();
    if ($sfUser->isAuthenticated()) {
        $userId = $sfUser->getUserID();
        if ($userId) {
            $user = $sfUser->user;
            \AtomExtensions\Services\AclService::setUser($user);
        }
    }
}

// Framework ready

// Qubit compatibility helpers
require_once __DIR__ . '/src/Helpers/QubitHelper.php';
// require_once __DIR__ . '/src/Compat/Qubit.php'; // Conflicts with core - uses core Qubit instead

// Qubit compatibility classes
// require_once __DIR__ . '/src/Compat/QubitSetting.php'; // Conflicts with core
// require_once __DIR__ . '/src/Compat/QubitCache.php'; // Conflicts with core
// require_once __DIR__ . '/src/Compat/QubitHtmlPurifier.php'; // Conflicts with core
// require_once __DIR__ . '/src/Compat/QubitSlug.php'; // Conflicts with core
// require_once __DIR__ . '/src/Compat/QubitOai.php'; // Conflicts with core
// require_once __DIR__ . '/src/Compat/QubitTerm.php'; // Conflicts with core
// require_once __DIR__ . '/src/Compat/QubitTaxonomy.php'; // Conflicts with core

// =============================================================================
// 3D AUTO CONFIG EVENT LISTENER
// =============================================================================

// Listen for digital object view/access events and auto-create 3D configs
if (class_exists('sfContext') && sfContext::hasInstance()) {
    try {
        $dispatcher = sfContext::getInstance()->getEventDispatcher();
        
        // Listen for access_log.view events (fired when viewing objects)
        $dispatcher->connect('access_log.view', function(sfEvent $event) {
            try {
                $object = $event->getParameters()['object'] ?? null;
                if ($object && method_exists($object, 'getDigitalObjectsRelatedByobjectId')) {
                    foreach ($object->getDigitalObjectsRelatedByobjectId() as $do) {
                        $filename = $do->getName();
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, ['glb', 'gltf', 'obj', 'fbx', 'stl', 'ply', 'usdz'])) {
                            // Check if config exists
                            $existing = \Illuminate\Database\Capsule\Manager::table('object_3d_model')
                                ->where('object_id', $object->id)
                                ->first();
                            
                            if (!$existing) {
                                // Auto-create config
                                $formatMap = ['glb' => 'glb', 'gltf' => 'gltf', 'obj' => 'obj', 'fbx' => 'fbx', 'stl' => 'stl', 'ply' => 'ply', 'usdz' => 'usdz'];
                                $filePath = '/uploads/r/' . $do->path . '/' . $filename;
                                
                                \Illuminate\Database\Capsule\Manager::table('object_3d_model')->insert([
                                    'object_id' => $object->id,
                                    'filename' => $filename,
                                    'original_filename' => $filename,
                                    'file_path' => $filePath,
                                    'file_size' => $do->byteSize ?? 0,
                                    'mime_type' => $do->mimeType ?? 'model/gltf-binary',
                                    'format' => $formatMap[$ext] ?? 'glb',
                                    'auto_rotate' => 1,
                                    'rotation_speed' => 1.00,
                                    'camera_orbit' => '0deg 75deg 105%',
                                    'field_of_view' => '30deg',
                                    'exposure' => 1.00,
                                    'shadow_intensity' => 1.00,
                                    'shadow_softness' => 1.00,
                                    'background_color' => '#f5f5f5',
                                    'ar_enabled' => 1,
                                    'ar_scale' => 'auto',
                                    'ar_placement' => 'floor',
                                    'is_primary' => 1,
                                    'is_public' => 1,
                                    'display_order' => 0,
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                ]);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Silently fail - don't break the view
                error_log('3D Auto Config: ' . $e->getMessage());
            }
        });
    } catch (Exception $e) {
        // Dispatcher not available
    }
}
