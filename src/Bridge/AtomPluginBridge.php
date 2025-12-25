<?php

declare(strict_types=1);

namespace Atom\Framework\Bridge;

use Atom\Framework\Repositories\PluginRepository;
use Atom\Framework\Services\PluginManagerService;
use Illuminate\Database\Capsule\Manager as Capsule;

class AtomPluginBridge
{
    protected static ?PluginManagerService $service = null;
    protected static ?array $cachedPlugins = null;
    protected static bool $initialized = false;

    public static function initialize(array $dbConfig, string $pluginsPath, string $cachePath): void
    {
        if (self::$initialized) {
            return;
        }

        // Check if Capsule is ready
        if (!self::isCapsuleReady()) {
            self::$initialized = true;
            return;
        }

        // Check if our table exists
        try {
            if (!Capsule::schema()->hasTable('atom_plugin')) {
                self::$initialized = true;
                return;
            }
        } catch (\Exception $e) {
            error_log('AtomPluginBridge: Cannot check table - ' . $e->getMessage());
            self::$initialized = true;
            return;
        }

        try {
            $repository = new PluginRepository();
            self::$service = new PluginManagerService($repository, $pluginsPath, $cachePath);
        } catch (\Exception $e) {
            error_log('AtomPluginBridge: Service init failed - ' . $e->getMessage());
        }

        self::$initialized = true;
    }

    protected static function isCapsuleReady(): bool
    {
        try {
            $connection = Capsule::connection();
            return null !== $connection;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getEnabledPlugins(array $fallbackPlugins = []): array
    {
        if (null !== self::$cachedPlugins) {
            return self::$cachedPlugins;
        }

        if (null === self::$service) {
            return $fallbackPlugins;
        }

        try {
            $enabled = self::$service->getEnabledPlugins();
            self::$cachedPlugins = !empty($enabled) ? $enabled : $fallbackPlugins;
            return self::$cachedPlugins;
        } catch (\Exception $e) {
            error_log('AtomPluginBridge: Failed to load plugins - ' . $e->getMessage());
            return $fallbackPlugins;
        }
    }

    public static function isPluginEnabled(string $name): bool
    {
        if (null === self::$service) {
            return false;
        }

        try {
            return self::$service->isEnabled($name);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getService(): ?PluginManagerService
    {
        return self::$service;
    }

    public static function clearCache(): void
    {
        self::$cachedPlugins = null;
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    public static function isDatabaseAvailable(): bool
    {
        return null !== self::$service;
    }

    public static function seedFromHardcodedList(array $plugins, string $pluginsPath): array
    {
        if (null === self::$service) {
            return ['error' => 'Plugin service not initialized'];
        }

        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($plugins as $pluginName) {
            try {
                $plugin = self::$service->getPlugin($pluginName);
                if (null !== $plugin) {
                    ++$results['skipped'];
                    continue;
                }

                $pluginPath = $pluginsPath . '/' . $pluginName;
                $config = self::$service->validatePluginConfig($pluginPath);

                $pluginData = array_merge($config['config'], [
                    'name' => $pluginName,
                    'plugin_path' => $pluginPath,
                    'is_enabled' => true,
                    'enabled_at' => date('Y-m-d H:i:s'),
                ]);

                self::$service->registerPlugin($pluginData);
                ++$results['created'];
            } catch (\Exception $e) {
                $results['errors'][] = "{$pluginName}: " . $e->getMessage();
            }
        }

        return $results;
    }
}
