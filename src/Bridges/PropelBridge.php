<?php

namespace AtomFramework\Bridges;

/**
 * Propel Autoloader Bridge — boot Propel WITHOUT Symfony.
 *
 * Initializes the Propel 1.4 ORM and registers autoloaders for Qubit model
 * classes so that code like QubitInformationObject::getById($id) works in
 * the Heratio (Laravel) context without booting Symfony at all.
 *
 * Also registers:
 *   - sfCoreAutoload: all sf* classes from vendor/symfony/lib/
 *   - Zend autoloader: Zend_Acl_* classes from qbAclPlugin
 *   - Shims: sfException (aliased to \Exception)
 *
 * Usage:
 *   PropelBridge::boot('/usr/share/nginx/archive');
 *
 * After boot(), Propel::getConnection() works and all Qubit* / Base* /
 * *TableMap / sf* / Zend_* classes resolve via registered autoloaders.
 */
class PropelBridge
{
    private static bool $initialized = false;

    /**
     * Boot Propel from the AtoM root directory.
     *
     * Safe to call multiple times — subsequent calls are no-ops.
     */
    public static function boot(string $rootDir): void
    {
        if (self::$initialized) {
            return;
        }

        // If Propel is already loaded (Symfony context), skip
        if (class_exists('Propel', false) && \Propel::isInit()) {
            self::$initialized = true;

            return;
        }

        $propelDir = $rootDir . '/vendor/symfony/lib/plugins/sfPropelPlugin/lib/vendor';

        // 1. Set include path so Propel's internal require() calls resolve.
        //    DO NOT restore the old include path — Propel's Criteria, ColumnMap,
        //    etc. use require() with relative paths that need this permanently.
        set_include_path($propelDir . PATH_SEPARATOR . get_include_path());

        // 2. Shim sfException BEFORE anything else — sfCoreAutoload::register()
        //    uses it, and Qubit model classes catch it.
        if (!class_exists('sfException', false)) {
            class_alias(\Exception::class, 'sfException');
        }

        // 3. Register sfCoreAutoload — provides ALL sf* classes (sfActions,
        //    sfForm, sfComponent, sfView, etc.) without full Symfony boot.
        self::registerSfCoreAutoload($rootDir);

        // 4. Register Zend autoloader — Qubit models implement Zend_Acl_*
        //    interfaces from qbAclPlugin's vendored Zend library.
        self::registerZendAutoloader($rootDir);

        // 5. Require Propel core (it uses require internally for its deps)
        require_once $propelDir . '/propel/Propel.php';

        // 6. Configure Propel datasource from config/config.php
        $dbConfig = self::parseConfig($rootDir);
        if (null === $dbConfig) {
            throw new \RuntimeException('PropelBridge: Unable to parse database config from config/config.php');
        }

        $config = [
            'datasources' => [
                'default' => 'propel',
                'propel' => [
                    'adapter' => 'mysql',
                    'connection' => [
                        'dsn' => $dbConfig['dsn'],
                        'user' => $dbConfig['username'],
                        'password' => $dbConfig['password'],
                        'classname' => 'PropelPDO',
                        'options' => [
                            'ATTR_PERSISTENT' => false,
                        ],
                        'settings' => [
                            'charset' => ['value' => 'utf8mb4'],
                        ],
                    ],
                ],
            ],
        ];

        \Propel::setConfiguration($config);
        \Propel::initialize();

        // 7. Register autoloader for lib/model/ classes
        self::registerModelAutoloader($rootDir);

        // 8. Require QubitQuery (used by all model ::get() methods)
        $queryFile = $rootDir . '/lib/QubitQuery.class.php';
        if (file_exists($queryFile)) {
            require_once $queryFile;
        }

        // 9. Register lib/ autoloader for AtoM utility classes
        //    (QubitFlatfileImport, QubitMetsParser, etc.)
        self::registerLibAutoloader($rootDir);

        // 10. Register plugin lib autoloader for plugin-specific classes
        //     (sfDrupalWidgetFormSchemaFormatter, arWidgetFormInputFileEditable, etc.)
        self::registerPluginLibAutoloader($rootDir);

        // 11. Load OpenSearch backward-compatibility aliases
        //     (maps arElasticSearch* class names to arOpenSearch* equivalents)
        $aliasFile = $rootDir . '/plugins/arOpenSearchPlugin/lib/arOpenSearchAliases.php';
        if (file_exists($aliasFile)) {
            require_once $aliasFile;
        }

        self::$initialized = true;
    }

    /**
     * Check whether PropelBridge has been booted.
     */
    public static function isBooted(): bool
    {
        return self::$initialized;
    }

    /**
     * Parse database config from AtoM's config/config.php.
     *
     * @return array{dsn: string, username: string, password: string}|null
     */
    private static function parseConfig(string $rootDir): ?array
    {
        $configFile = $rootDir . '/config/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        $config = require $configFile;
        if (!isset($config['all']['propel']['param'])) {
            return null;
        }

        $params = $config['all']['propel']['param'];

        return [
            'dsn' => $params['dsn'] ?? '',
            'username' => $params['username'] ?? 'root',
            'password' => $params['password'] ?? '',
        ];
    }

    /**
     * Register sfCoreAutoload from Symfony's autoloader.
     *
     * This makes ALL sf* classes available (sfActions, sfForm, sfComponent,
     * sfView, etc.) without booting the full Symfony framework.
     */
    private static function registerSfCoreAutoload(string $rootDir): void
    {
        $autoloadFile = $rootDir . '/vendor/symfony/lib/autoload/sfCoreAutoload.class.php';
        if (!file_exists($autoloadFile)) {
            return;
        }

        // Prevent double-registration
        if (class_exists('sfCoreAutoload', false)) {
            return;
        }

        require_once $autoloadFile;
        \sfCoreAutoload::register();
    }

    /**
     * Register PEAR-style autoloader for Zend_* classes.
     *
     * QubitObject implements Zend_Acl_Resource_Interface, QubitAclGroup
     * implements Zend_Acl_Role_Interface — both from qbAclPlugin's vendor.
     */
    private static function registerZendAutoloader(string $rootDir): void
    {
        $zendDir = $rootDir . '/plugins/qbAclPlugin/lib/vendor';
        if (!is_dir($zendDir)) {
            return;
        }

        spl_autoload_register(function (string $class) use ($zendDir) {
            if (!str_starts_with($class, 'Zend_')) {
                return;
            }

            // PEAR naming: Zend_Acl_Resource_Interface → Zend/Acl/Resource/Interface.php
            $file = $zendDir . '/' . str_replace('_', '/', $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Register an SPL autoloader for AtoM model classes.
     *
     * Mapping rules:
     *   QubitXxx         → lib/model/QubitXxx.php
     *   BaseXxx          → lib/model/om/BaseXxx.php
     *   XxxTableMap      → lib/model/map/XxxTableMap.php
     *   XxxI18nTableMap  → lib/model/map/XxxI18nTableMap.php
     *   QubitXxxI18n     → lib/model/QubitXxxI18n.php
     */
    private static function registerModelAutoloader(string $rootDir): void
    {
        $modelDir = $rootDir . '/lib/model';

        spl_autoload_register(function (string $class) use ($modelDir) {
            // Skip namespaced classes — those are handled by Composer
            if (str_contains($class, '\\')) {
                return;
            }

            // QubitXxx / QubitXxxI18n → lib/model/QubitXxx.php
            if (str_starts_with($class, 'Qubit')) {
                $file = $modelDir . '/' . $class . '.php';
                if (file_exists($file)) {
                    require_once $file;

                    return;
                }
            }

            // BaseXxx → lib/model/om/BaseXxx.php
            if (str_starts_with($class, 'Base')) {
                $file = $modelDir . '/om/' . $class . '.php';
                if (file_exists($file)) {
                    require_once $file;

                    return;
                }
            }

            // XxxTableMap → lib/model/map/XxxTableMap.php
            if (str_ends_with($class, 'TableMap')) {
                $file = $modelDir . '/map/' . $class . '.php';
                if (file_exists($file)) {
                    require_once $file;

                    return;
                }
            }

            // QubitPeer (if it exists)
            if (str_ends_with($class, 'Peer')) {
                $file = $modelDir . '/' . $class . '.php';
                if (file_exists($file)) {
                    require_once $file;

                    return;
                }
            }
        });
    }

    /**
     * Register autoloader for plugin lib classes.
     *
     * Handles classes from plugin lib directories, e.g.:
     *   sfDrupalWidgetFormSchemaFormatter (sfDrupalPlugin)
     *   arWidgetFormInputFileEditable (arDominionPlugin)
     *
     * Builds a class map on first call for O(1) lookups.
     */
    private static function registerPluginLibAutoloader(string $rootDir): void
    {
        $pluginsDir = $rootDir . '/plugins';
        if (!is_dir($pluginsDir)) {
            return;
        }

        // Build class map lazily on first autoload attempt
        $classMap = null;

        spl_autoload_register(function (string $class) use ($pluginsDir, &$classMap) {
            // Skip namespaced classes
            if (str_contains($class, '\\')) {
                return;
            }

            // Build the class map once
            if (null === $classMap) {
                $classMap = [];
                $pattern = $pluginsDir . '/*/lib/*.class.php';
                foreach (glob($pattern) as $file) {
                    $name = basename($file, '.class.php');
                    $classMap[strtolower($name)] = $file;
                }
                // Also check lib/ subdirectories (one level)
                $pattern2 = $pluginsDir . '/*/lib/*/*.class.php';
                foreach (glob($pattern2) as $file) {
                    $name = basename($file, '.class.php');
                    // Don't overwrite top-level entries
                    if (!isset($classMap[strtolower($name)])) {
                        $classMap[strtolower($name)] = $file;
                    }
                }
                // Also check config/ directories for plugin configuration classes
                // (e.g., arElasticSearchPluginConfiguration)
                $pattern3 = $pluginsDir . '/*/config/*.class.php';
                foreach (glob($pattern3) as $file) {
                    $name = basename($file, '.class.php');
                    if (!isset($classMap[strtolower($name)])) {
                        $classMap[strtolower($name)] = $file;
                    }
                }
                // Also check lib/ subdirectory .php files (non-class.php convention)
                // e.g., arOpenSearchPlugin/lib/Search/QueryWrapper.php
                $pattern4 = $pluginsDir . '/*/lib/*/*.php';
                foreach (glob($pattern4) as $file) {
                    // Skip .class.php files (already handled above)
                    if (str_ends_with($file, '.class.php')) {
                        continue;
                    }
                    $name = basename($file, '.php');
                    if (!isset($classMap[strtolower($name)])) {
                        $classMap[strtolower($name)] = $file;
                    }
                }
            }

            $key = strtolower($class);
            if (isset($classMap[$key])) {
                require_once $classMap[$key];
            }
        });
    }

    /**
     * Register autoloader for AtoM lib/ utility classes.
     *
     * Handles classes like QubitFlatfileImport, QubitMetsParser, etc.
     * that live in lib/ as ClassName.class.php files.
     */
    private static function registerLibAutoloader(string $rootDir): void
    {
        $libDir = $rootDir . '/lib';

        spl_autoload_register(function (string $class) use ($libDir) {
            // Skip namespaced classes
            if (str_contains($class, '\\')) {
                return;
            }

            // Try lib/ClassName.class.php (Symfony convention)
            $file = $libDir . '/' . $class . '.class.php';
            if (file_exists($file)) {
                require_once $file;

                return;
            }

            // Try lib/ClassName.php
            $file = $libDir . '/' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;

                return;
            }
        });
    }
}
