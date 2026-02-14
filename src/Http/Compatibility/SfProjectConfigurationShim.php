<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone sfProjectConfiguration shim.
 *
 * Provides the getActive()->getPlugins() API that AHG plugin actions
 * and templates use to check which plugins are enabled.
 *
 * In Symfony mode, the real sfProjectConfiguration is loaded by the
 * framework. In standalone mode (heratio.php), this shim queries
 * the atom_plugin table instead.
 */
class SfProjectConfigurationShim
{
    private static ?self $active = null;

    /** @var string[] Cached list of enabled plugin names */
    private array $plugins = [];

    /**
     * Get the active project configuration instance.
     */
    public static function getActive(): self
    {
        if (null === self::$active) {
            self::$active = new self();
        }

        return self::$active;
    }

    /**
     * Get the list of enabled plugins.
     *
     * Queries atom_plugin table for enabled OR core plugins.
     * Core plugins (is_core=1) are always loaded regardless of is_enabled flag.
     *
     * @return string[] Plugin names
     */
    public function getPlugins(): array
    {
        if (!empty($this->plugins)) {
            return $this->plugins;
        }

        try {
            $this->plugins = DB::table('atom_plugin')
                ->where(function ($q) {
                    $q->where('is_enabled', 1)->orWhere('is_core', 1);
                })
                ->pluck('name')
                ->toArray();
        } catch (\Throwable $e) {
            $this->plugins = [];
        }

        return $this->plugins;
    }

    /**
     * Set the plugins list.
     *
     * Called by plugin configurations (e.g. theme) to reorder plugins.
     * In standalone mode, updates the in-memory list.
     *
     * @param string[] $plugins Plugin names
     */
    public function setPlugins($plugins): void
    {
        $this->plugins = is_array($plugins) ? array_values($plugins) : [];
    }

    /**
     * Check if a specific plugin is enabled.
     *
     * No type hints — must match ProjectConfiguration::isPluginEnabled($pluginName).
     */
    public function isPluginEnabled($name)
    {
        return in_array($name, $this->getPlugins());
    }

    /**
     * Get plugin sub-paths (model dirs, lib dirs, etc.).
     */
    public function getPluginSubPaths($subPath = '/lib/model'): array
    {
        $paths = [];
        $pluginsDir = \sfConfig::get('sf_plugins_dir', '');
        if (!$pluginsDir || !is_dir($pluginsDir)) {
            return $paths;
        }

        foreach ($this->getPlugins() as $plugin) {
            $dir = $pluginsDir . '/' . $plugin . $subPath;
            if (is_dir($dir)) {
                $paths[$plugin] = $dir;
            }
        }

        return $paths;
    }

    /**
     * Get plugin paths (root directories).
     */
    public function getPluginPaths(): array
    {
        $paths = [];
        $pluginsDir = \sfConfig::get('sf_plugins_dir', '');
        if (!$pluginsDir || !is_dir($pluginsDir)) {
            return $paths;
        }

        foreach ($this->getPlugins() as $plugin) {
            $dir = $pluginsDir . '/' . $plugin;
            if (is_dir($dir)) {
                $paths[] = $dir;
            }
        }

        return $paths;
    }

    /**
     * Get the root directory.
     */
    public function getRootDir(): string
    {
        return \sfConfig::get('sf_root_dir', '');
    }

    /**
     * Get the event dispatcher.
     *
     * sfPluginConfiguration constructor calls $configuration->getEventDispatcher().
     * Returns the SfEventDispatcherAdapter for standalone mode.
     *
     * No return type hint — must match real sfProjectConfiguration signature,
     * and SfEventDispatcherAdapter lives in SfContextAdapter.php (multi-class file).
     */
    public function getEventDispatcher()
    {
        static $dispatcher;
        if (!$dispatcher) {
            // SfEventDispatcherAdapter is defined inside SfContextAdapter.php
            // (multi-class file — PSR-4 autoloading won't find it by class name)
            if (!class_exists('AtomFramework\\Http\\Compatibility\\SfEventDispatcherAdapter', false)) {
                require_once __DIR__ . '/SfContextAdapter.php';
            }
            $dispatcher = new \AtomFramework\Http\Compatibility\SfEventDispatcherAdapter();
        }

        return $dispatcher;
    }

    /**
     * Enable plugins (no-op in standalone — plugins are DB-driven).
     */
    public function enablePlugins($plugins): void
    {
        // No-op
    }
}
