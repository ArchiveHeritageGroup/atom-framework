<?php

declare(strict_types=1);

namespace AtomFramework\Config;

use PDO;

/**
 * Works out which plugins AtoM should enable.
 *
 * Why this is here and not in config/ProjectConfiguration.class.php:
 *
 * Symfony 1 needs the plugin list during setup(), before any plugin code can run,
 * so something in the AtoM tree has to ask. That much is unavoidable. What is
 * avoidable is doing the asking there: the file had grown from AtoM\'s 90 lines to
 * 305, a 261-line divergence carrying database access, theme detection and
 * dependency filtering - and, in passing, it had dropped AtoM\'s AGPL header.
 *
 * A fork that size cannot survive an upstream release. With the logic here, the
 * only change AtoM needs is a few lines that hand over to this class if it is
 * present, and an install without the framework is untouched.
 */
final class PluginLoader
{
    const HERATIO_THEME = 'ahgThemeB5Plugin';

    /**
     * The full plugin list to enable, given AtoM\'s own core list.
     *
     * Returns the caller\'s list unchanged on any failure, so a broken or absent
     * framework degrades to a stock AtoM rather than an unbootable one.
     */
    public static function resolve(array $corePlugins, string $rootDir): array
    {
        try {
            self::loadRoutingClasses($rootDir);

            // Vanilla profile: a .heratio_disabled flag, or an admin-selected theme
            // that is not the AHG one, boots base AtoM with no AHG plugins at all.
            $baseTheme = null;

            if (file_exists($rootDir.'/.heratio_disabled')) {
                $baseTheme = 'arDominionB5Plugin';
            } else {
                $selected = self::getActiveThemeSetting($rootDir);

                if (null !== $selected && '' !== $selected && self::HERATIO_THEME !== $selected) {
                    $baseTheme = $selected;
                }
            }

            if (null !== $baseTheme) {
                return self::baseProfile($rootDir, $baseTheme);
            }

            $plugins = self::loadPluginsFromDatabase($corePlugins, $rootDir);

            return self::filterPluginsByDependencies($plugins, $rootDir);
        } catch (\Throwable $e) {
            return $corePlugins;
        }
    }

    /**
     * Preload the routing classes the plugins name in their route definitions.
     *
     * RouteLoader instantiates these by name when routing.load_configuration
     * fires, which is after setup() and outside any autoloader that knows about
     * them - so without this the first plugin route throws "Class
     * SafeRequestRoute not found" and takes the whole site down, not just that
     * route.
     *
     * Loaded from the framework first, then AtoM's lib, so an installation that
     * has moved them still works. Each is optional: a missing file only matters
     * to a plugin that names it.
     */
    private static function loadRoutingClasses(string $rootDir): void
    {
        // The Qubit parents must exist before the AHG subclasses extend them.
        foreach (['QubitRoute', 'QubitMetadataRoute'] as $class) {
            $path = $rootDir.'/lib/routing/'.$class.'.class.php';

            if (!class_exists($class, false) && file_exists($path)) {
                require_once $path;
            }
        }

        foreach (['AhgMetadataRoute', 'AddActionRoute', 'SafeRequestRoute'] as $class) {
            if (class_exists($class, false)) {
                continue;
            }

            foreach ([
                $rootDir.'/atom-framework/src/Routing/'.$class.'.class.php',
                $rootDir.'/lib/routing/'.$class.'.class.php',
            ] as $path) {
                if (file_exists($path)) {
                    require_once $path;

                    break;
                }
            }
        }
    }

    /**
     * The stock AtoM plugin set plus a chosen theme, filtered to what is installed:
     * some ship in symfony\'s vendored plugin directory rather than project plugins/.
     */
    private static function baseProfile(string $rootDir, string $baseTheme): array
    {
        $plugins = [
            'qbAclPlugin', 'qtAccessionPlugin', 'sfDrupalPlugin', 'sfHistoryPlugin',
            'arOpenSearchPlugin', 'arElasticSearchPlugin',
            'sfPropelPlugin', 'sfThumbnailPlugin', 'sfTranslatePlugin', 'sfWebBrowserPlugin',
            $baseTheme,
            'sfIsadPlugin', 'sfIsaarPlugin', 'sfEadPlugin', 'sfDcPlugin', 'sfModsPlugin',
            'sfRadPlugin', 'sfSkosPlugin', 'sfEacPlugin', 'sfIsdfPlugin', 'sfIsdiahPlugin',
        ];

        return array_values(array_filter($plugins, static function ($p) use ($rootDir) {
            return is_dir($rootDir.'/plugins/'.$p)
                || is_dir($rootDir.'/vendor/symfony/lib/plugins/'.$p);
        }));
    }

    public static function getActiveThemeSetting(string $rootDir): ?string
    {
        try {
            $pdo = self::getAhgPdo($rootDir);
            if (null === $pdo) {
                return null;
            }
            $stmt = $pdo->prepare(
                "SELECT setting_value FROM ahg_settings"
                ." WHERE setting_key = 'ahg_active_theme' AND setting_group = 'general' LIMIT 1"
            );
            $stmt->execute();
            $value = $stmt->fetchColumn();

            return false === $value ? null : (string) $value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getAhgPdo(string $rootDir): ?PDO
    {
        $configPath = $rootDir.'/config/config.php';
        if (!file_exists($configPath)) {
            return null;
        }
        $config = require $configPath;
        if (!isset($config['all']['propel']['param'])) {
            return null;
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

        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $dsnParts['host'] ?? 'localhost',
                $dsnParts['port'] ?? 3306,
                $dsnParts['dbname'] ?? 'atom'
            ),
            $params['username'] ?? 'root',
            $params['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public static function filterPluginsByDependencies(array $plugins, string $rootDir): array
    {
        // The caller's root, not sfConfig: setup() runs before sf_root_dir is
        // dependable, and a wrong root here silently disables every plugin.
        $dir = $rootDir.'/plugins';

        // Iterate to a fixed point: dropping B because its dependency is missing must
        // also drop A, which depends on B.
        do {
            $enabled = array_flip($plugins);
            $keep = [];

            foreach ($plugins as $plugin) {
                $missing = [];
                $file = $dir.'/'.$plugin.'/extension.json';

                if (is_file($file)) {
                    $config = json_decode((string) file_get_contents($file), true);

                    foreach ((array) ($config['dependencies'] ?? []) as $dependency) {
                        if (!isset($enabled[$dependency]) || !is_dir($dir.'/'.$dependency)) {
                            $missing[] = $dependency;
                        }
                    }
                }

                if (!empty($missing)) {
                    error_log(sprintf(
                        'AHG: plugin "%s" not loaded - missing dependency: %s',
                        $plugin,
                        implode(', ', $missing)
                    ));

                    continue;
                }

                $keep[] = $plugin;
            }

            $changed = count($keep) !== count($plugins);
            $plugins = $keep;
        } while ($changed);

        return $plugins;
    }

    public static function loadPluginsFromDatabase(array $corePlugins, string $rootDir): array
    {
        try {
            $pdo = self::getAhgPdo($rootDir);
            if (null === $pdo) {
                return $corePlugins;
            }

            $stmt = $pdo->prepare('SELECT name FROM atom_plugin WHERE is_enabled = 1 ORDER BY load_order ASC');
            $stmt->execute();
            $dbPlugins = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($dbPlugins)) {
                // Merge core plugins with database plugins, core first
                return array_values(array_unique(array_merge($corePlugins, $dbPlugins)));
            }

            return $corePlugins;

        } catch (\Exception $e) {
            error_log('ProjectConfiguration: Failed to load plugins from database - ' . $e->getMessage());
            return $corePlugins;
        }
    }
}
