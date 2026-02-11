<?php

namespace AtomFramework\Http\Compatibility;

/**
 * Standalone sfConfig implementation for when Symfony is not loaded.
 *
 * Provides the same static API as Symfony's sfConfig class, backed by
 * a simple array store. Only activated when the real sfConfig class
 * doesn't exist (i.e., booting via heratio.php without Symfony).
 */
class SfConfigShim
{
    private static array $config = [];

    public static function get(string $name, $default = null)
    {
        return self::$config[$name] ?? $default;
    }

    public static function set(string $name, $value): void
    {
        self::$config[$name] = $value;
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$config);
    }

    public static function add(array $parameters = []): void
    {
        self::$config = array_merge(self::$config, $parameters);
    }

    public static function getAll(): array
    {
        return self::$config;
    }

    public static function clear(): void
    {
        self::$config = [];
    }

    /**
     * Register this shim as the global sfConfig class.
     *
     * Only call this when Symfony's sfConfig is NOT loaded.
     */
    public static function register(): void
    {
        if (!class_exists('sfConfig', false)) {
            class_alias(self::class, 'sfConfig');
        }
    }

    /**
     * Pre-populate with essential paths and defaults.
     */
    public static function bootstrap(string $rootDir): void
    {
        self::add([
            'sf_root_dir' => $rootDir,
            'sf_web_dir' => $rootDir,
            'sf_upload_dir' => $rootDir . '/uploads',
            'sf_cache_dir' => $rootDir . '/cache',
            'sf_log_dir' => $rootDir . '/log',
            'sf_data_dir' => $rootDir . '/data',
            'sf_config_dir' => $rootDir . '/config',
            'sf_plugins_dir' => $rootDir . '/plugins',
            'sf_lib_dir' => $rootDir . '/lib',
            'sf_app' => 'qubit',
            'sf_environment' => 'prod',
            'sf_debug' => false,
            'sf_logging_enabled' => false,
        ]);
    }
}
