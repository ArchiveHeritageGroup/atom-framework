<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * Auto-discovers and registers modules from enabled plugins.
 * Uses PDO directly to avoid autoloader conflicts (like PluginManagerService).
 * 
 * No settings.yml required - core modules defined here.
 */
class ModuleDiscoveryService
{
    private static ?array $cachedModules = null;

    /**
     * Core modules required by AtoM - always enabled.
     * These would normally be in settings.yml enabled_modules.
     */
    private static array $coreModules = [
        'default',
        'aclGroup',
        'extensions',
        'settings',
    ];

    /**
     * Discover all modules from enabled plugins.
     */
    public static function discoverModules(): array
    {
        if (false && null !== self::$cachedModules) {
            return self::$cachedModules;
        }

        $modules = self::$coreModules;
        $pluginsPath = \sfConfig::get('sf_plugins_dir');

        // Get enabled plugins from database
        $enabledPlugins = self::getEnabledPlugins();

        foreach ($enabledPlugins as $pluginName) {
            $modulesDir = $pluginsPath . '/' . $pluginName . '/modules';
            if (!is_dir($modulesDir)) {
                continue;
            }

            $discovered = self::scanModulesDirectory($modulesDir);
            $modules = array_merge($modules, $discovered);
        }

        // Remove duplicates and re-index
        self::$cachedModules = array_values(array_unique($modules));

        return self::$cachedModules;
    }

    /**
     * Get enabled plugins from database using PDO (no Laravel dependency).
     */
    private static function getEnabledPlugins(): array
    {
        try {
            // Use Propel connection if available (web context)
            if (class_exists('Propel') && method_exists('Propel', 'getConnection')) {
                $conn = \Propel::getConnection();
                $stmt = $conn->prepare('SELECT name FROM atom_plugin WHERE is_enabled = 1 ORDER BY load_order ASC');
                $stmt->execute();
                $plugins = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                return !empty($plugins) ? $plugins : self::getFallbackPlugins();
            }

            // Direct PDO for CLI or if Propel not ready
            $configPath = \sfConfig::get('sf_root_dir') . '/config/config.php';
            if (!file_exists($configPath)) {
                return self::getFallbackPlugins();
            }

            $config = require $configPath;
            if (!isset($config['all']['propel']['param'])) {
                return self::getFallbackPlugins();
            }

            $params = $config['all']['propel']['param'];
            $dsn = $params['dsn'] ?? '';
            $dsnParts = [];

            if (!empty($dsn)) {
                $dsnWithoutDriver = preg_replace('/^[a-z]+:/', '', $dsn);
                foreach (explode(';', $dsnWithoutDriver) as $part) {
                    $part = trim($part);
                    if (strpos($part, '=') !== false) {
                        list($key, $value) = explode('=', $part, 2);
                        $dsnParts[trim($key)] = trim($value);
                    }
                }
            }

            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $dsnParts['host'] ?? 'localhost',
                    $dsnParts['port'] ?? 3306,
                    $dsnParts['dbname'] ?? 'atom'
                ),
                $params['username'] ?? 'root',
                $params['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare('SELECT name FROM atom_plugin WHERE is_enabled = 1 ORDER BY load_order ASC');
            $stmt->execute();
            $plugins = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return !empty($plugins) ? $plugins : self::getFallbackPlugins();

        } catch (\Exception $e) {
            error_log('ModuleDiscovery: DB error - ' . $e->getMessage());
            return self::getFallbackPlugins();
        }
    }

    /**
     * Fallback plugin list if database unavailable.
     */
    private static function getFallbackPlugins(): array
    {
        return [
            'sfPropelPlugin',
            'qbAclPlugin',
            'sfWebBrowserPlugin',
            'arElasticSearchPlugin',
            'arDominionB5Plugin',
            'arAHGThemeB5Plugin',
        ];
    }

    /**
     * Scan a modules directory for module folders.
     */
    private static function scanModulesDirectory(string $modulesDir): array
    {
        $modules = [];
        $iterator = new \DirectoryIterator($modulesDir);

        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $moduleName = $item->getFilename();
            $actionsDir = $modulesDir . '/' . $moduleName . '/actions';

            // Only register if it has an actions directory
            if (is_dir($actionsDir)) {
                $modules[] = $moduleName;
            }
        }

        return $modules;
    }

    /**
     * Register discovered modules with Symfony.
     * Replaces settings.yml enabled_modules entirely.
     */
    public static function registerModules(): void
    {
        $modules = self::discoverModules();

        // Set modules directly - no merge with settings.yml needed
        // Merge with existing modules, don't replace
        $existing = \sfConfig::get('sf_enabled_modules', []);
        \sfConfig::set('sf_enabled_modules', array_values(array_unique(array_merge($existing, $modules))));
    }

    /**
     * Clear the cached modules.
     */
    public static function clearCache(): void
    {
        self::$cachedModules = null;
    }

    /**
     * Check if a module is available.
     */
    public static function isModuleAvailable(string $moduleName): bool
    {
        $modules = self::discoverModules();
        return in_array($moduleName, $modules, true);
    }

    /**
     * Get core modules list.
     */
    public static function getCoreModules(): array
    {
        return self::$coreModules;
    }
}
