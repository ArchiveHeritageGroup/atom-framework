<?php

namespace AtomFramework\Bridges;

/**
 * Propel Autoloader Bridge — boot Propel WITHOUT Symfony.
 *
 * Initializes the Propel 1.4 ORM and registers autoloaders for Qubit model
 * classes so that code like QubitInformationObject::getById($id) works in
 * the Heratio (Laravel) context without booting Symfony at all.
 *
 * Usage:
 *   PropelBridge::boot('/usr/share/nginx/archive');
 *
 * After boot(), Propel::getConnection() works and all Qubit* / Base* /
 * *TableMap classes resolve via the registered spl_autoload_register.
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

        // 1. Set include path so Propel's internal require() calls resolve
        $oldIncludePath = get_include_path();
        set_include_path($propelDir . PATH_SEPARATOR . $oldIncludePath);

        // 2. Require Propel core (it uses require internally for its deps)
        require_once $propelDir . '/propel/Propel.php';

        // 3. Configure Propel datasource from config/config.php
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

        // 4. Register autoloader for lib/model/ classes
        self::registerModelAutoloader($rootDir);

        // 5. Require QubitQuery (used by all model ::get() methods)
        $queryFile = $rootDir . '/lib/QubitQuery.class.php';
        if (file_exists($queryFile)) {
            require_once $queryFile;
        }

        // Restore include path
        set_include_path($oldIncludePath);

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
}
