<?php

namespace AtomFramework\Helpers;

/**
 * Centralized path resolution for AtoM framework.
 *
 * Resolves paths using priority:
 * 1. sfConfig (Symfony context)
 * 2. Environment variables (ATOM_ROOT, ATOM_PLUGINS_DIR)
 * 3. Constants (ATOM_ROOT_PATH from bootstrap)
 * 4. Relative path detection
 */
class PathResolver
{
    private static ?string $rootDir = null;
    private static ?string $pluginsDir = null;
    private static ?string $frameworkDir = null;

    /**
     * Get the AtoM root directory.
     */
    public static function getRootDir(): string
    {
        if (self::$rootDir !== null) {
            return self::$rootDir;
        }

        // Priority 1: sfConfig (Symfony context)
        if (class_exists('sfConfig') && \sfConfig::get('sf_root_dir')) {
            self::$rootDir = \sfConfig::get('sf_root_dir');
            return self::$rootDir;
        }

        // Priority 2: Environment variable
        $envRoot = getenv('ATOM_ROOT') ?: ($_SERVER['ATOM_ROOT'] ?? null);
        if ($envRoot && is_dir($envRoot)) {
            self::$rootDir = rtrim($envRoot, '/');
            return self::$rootDir;
        }

        // Priority 3: Constant from bootstrap
        if (defined('ATOM_ROOT_PATH')) {
            self::$rootDir = ATOM_ROOT_PATH;
            return self::$rootDir;
        }

        // Priority 4: Detect relative to framework
        $frameworkPath = self::getFrameworkDir();
        self::$rootDir = dirname($frameworkPath);
        return self::$rootDir;
    }

    /**
     * Get the plugins directory.
     */
    public static function getPluginsDir(): string
    {
        if (self::$pluginsDir !== null) {
            return self::$pluginsDir;
        }

        // Priority 1: sfConfig
        if (class_exists('sfConfig') && \sfConfig::get('sf_plugins_dir')) {
            self::$pluginsDir = \sfConfig::get('sf_plugins_dir');
            return self::$pluginsDir;
        }

        // Priority 2: Environment variable
        $envPlugins = getenv('ATOM_PLUGINS_DIR') ?: ($_SERVER['ATOM_PLUGINS_DIR'] ?? null);
        if ($envPlugins && is_dir($envPlugins)) {
            self::$pluginsDir = rtrim($envPlugins, '/');
            return self::$pluginsDir;
        }

        // Priority 3: Default to root/plugins
        self::$pluginsDir = self::getRootDir() . '/plugins';
        return self::$pluginsDir;
    }

    /**
     * Get the framework directory.
     */
    public static function getFrameworkDir(): string
    {
        if (self::$frameworkDir !== null) {
            return self::$frameworkDir;
        }

        // Priority 1: Constant from bootstrap
        if (defined('ATOM_FRAMEWORK_PATH')) {
            self::$frameworkDir = ATOM_FRAMEWORK_PATH;
            return self::$frameworkDir;
        }

        // Priority 2: Environment variable
        $envFramework = getenv('ATOM_FRAMEWORK_PATH') ?: ($_SERVER['ATOM_FRAMEWORK_PATH'] ?? null);
        if ($envFramework && is_dir($envFramework)) {
            self::$frameworkDir = rtrim($envFramework, '/');
            return self::$frameworkDir;
        }

        // Priority 3: Detect relative to this file
        self::$frameworkDir = dirname(__DIR__, 2);
        return self::$frameworkDir;
    }

    /**
     * Get the data directory.
     */
    public static function getDataDir(): string
    {
        $envData = getenv('ATOM_DATA_DIR') ?: ($_SERVER['ATOM_DATA_DIR'] ?? null);
        if ($envData && is_dir($envData)) {
            return rtrim($envData, '/');
        }

        return self::getRootDir() . '/data';
    }

    /**
     * Get the log directory.
     */
    public static function getLogDir(): string
    {
        $envLog = getenv('ATOM_LOG_DIR') ?: ($_SERVER['ATOM_LOG_DIR'] ?? null);
        if ($envLog) {
            return rtrim($envLog, '/');
        }

        // Check if /var/log/atom exists and is writable
        if (is_dir('/var/log/atom') && is_writable('/var/log/atom')) {
            return '/var/log/atom';
        }

        return self::getRootDir() . '/log';
    }

    /**
     * Get the config directory.
     */
    public static function getConfigDir(): string
    {
        return self::getRootDir() . '/config';
    }

    /**
     * Get the config file path.
     */
    public static function getConfigFile(): string
    {
        return self::getConfigDir() . '/config.php';
    }

    /**
     * Get the cache directory.
     */
    public static function getCacheDir(): string
    {
        if (class_exists('sfConfig') && \sfConfig::get('sf_cache_dir')) {
            return \sfConfig::get('sf_cache_dir');
        }

        return self::getRootDir() . '/cache';
    }

    /**
     * Get the uploads directory.
     */
    public static function getUploadsDir(): string
    {
        return self::getRootDir() . '/uploads';
    }

    /**
     * Get the backups directory.
     */
    public static function getBackupsDir(): string
    {
        $envBackups = getenv('ATOM_BACKUPS_DIR') ?: ($_SERVER['ATOM_BACKUPS_DIR'] ?? null);
        if ($envBackups) {
            return rtrim($envBackups, '/');
        }

        // Check if /var/backups/atom exists and is writable
        if (is_dir('/var/backups/atom') && is_writable('/var/backups/atom')) {
            return '/var/backups/atom';
        }

        return self::getDataDir() . '/backups';
    }

    /**
     * Get a plugin's path.
     */
    public static function getPluginPath(string $pluginName): string
    {
        return self::getPluginsDir() . '/' . $pluginName;
    }

    /**
     * Reset cached paths (useful for testing).
     */
    public static function reset(): void
    {
        self::$rootDir = null;
        self::$pluginsDir = null;
        self::$frameworkDir = null;
    }

    /**
     * Set root directory explicitly (for testing or special contexts).
     */
    public static function setRootDir(string $path): void
    {
        self::$rootDir = rtrim($path, '/');
    }

    /**
     * Set plugins directory explicitly.
     */
    public static function setPluginsDir(string $path): void
    {
        self::$pluginsDir = rtrim($path, '/');
    }
}
