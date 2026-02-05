<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use AtomFramework\Contracts\PluginManagerContract;
use AtomFramework\Exceptions\PluginDependencyException;
use AtomFramework\Exceptions\PluginNotFoundException;
use AtomFramework\Exceptions\PluginStateException;
use AtomFramework\Repositories\PluginRepository;

class PluginManagerService implements PluginManagerContract
{
    protected PluginRepository $repository;
    protected string $pluginsPath;
    protected string $cachePath;

    protected const CORE_PLUGINS = [
        'sfPropelPlugin',
        'arOpenSearchPlugin',
        'qbAclPlugin',
    ];

    protected const CATEGORIES = [
        'core' => 'Core System',
        'theme' => 'Themes',
        'metadata' => 'Metadata Standards',
        'integration' => 'Integrations',
        'workflow' => 'Workflow',
        'security' => 'Security',
        'general' => 'General',
    ];

    public function __construct(PluginRepository $repository, string $pluginsPath, string $cachePath)
    {
        $this->repository = $repository;
        $this->pluginsPath = rtrim($pluginsPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
    }

    public function getAllPlugins(array $filters = []): array
    {
        $plugins = $this->repository->findAll($filters);

        return array_map(function ($plugin) {
            $plugin = (array) $plugin;
            $plugin['settings'] = $plugin['settings'] ? json_decode($plugin['settings'], true) : [];
            $plugin['dependencies'] = $this->getDependencies($plugin['name']);
            $plugin['dependents'] = $this->getDependents($plugin['name']);
            return $plugin;
        }, $plugins);
    }

    public function getEnabledPlugins(): array
    {
        return $this->repository->findEnabled();
    }

    public function getPlugin(string $name): ?array
    {
        $plugin = $this->repository->findByName($name);
        if (null === $plugin) {
            return null;
        }
        $plugin = (array) $plugin;
        $plugin['settings'] = $plugin['settings'] ? json_decode($plugin['settings'], true) : [];
        $plugin['dependencies'] = $this->getDependencies($name);
        $plugin['dependents'] = $this->getDependents($name);
        return $plugin;
    }

    public function registerPlugin(array $pluginData): int
    {
        if ($this->repository->exists($pluginData['name'])) {
            throw new PluginStateException("Plugin '{$pluginData['name']}' already exists");
        }

        $pluginId = $this->repository->create($pluginData);

        if (isset($pluginData['dependencies'])) {
            foreach ($pluginData['dependencies'] as $dep) {
                $this->repository->addDependency($pluginId, $dep);
            }
        }

        if (isset($pluginData['hooks'])) {
            $this->repository->registerHooks($pluginId, $pluginData['hooks']);
        }

        return $pluginId;
    }

    public function enablePlugin(string $name, ?int $userId = null, ?string $reason = null): bool
    {
        $plugin = $this->repository->findByName($name);

        if (null === $plugin) {
            throw new PluginNotFoundException("Plugin '{$name}' not found");
        }

        if ($plugin->is_enabled) {
            return true;
        }

        if ($plugin->is_locked) {
            throw new PluginStateException("Plugin '{$name}' is locked");
        }

        $canEnable = $this->canEnable($name);
        if (!$canEnable['can_enable']) {
            throw new PluginDependencyException(
                "Cannot enable '{$name}': missing dependencies - " . implode(', ', $canEnable['missing_dependencies'])
            );
        }

        $this->repository->enable($name);

        $this->repository->addAuditLog([
            'plugin_id' => $plugin->id,
            'user_id' => $userId,
            'action' => 'enable',
            'previous_state' => ['is_enabled' => false],
            'new_state' => ['is_enabled' => true],
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $this->clearCaches();
        return true;
    }

    public function disablePlugin(string $name, ?int $userId = null, ?string $reason = null, bool $force = false): bool
    {
        $plugin = $this->repository->findByName($name);

        if (null === $plugin) {
            throw new PluginNotFoundException("Plugin '{$name}' not found");
        }

        if (!$plugin->is_enabled) {
            return true;
        }

        if ($plugin->is_core) {
            throw new PluginStateException("Plugin '{$name}' is a core plugin and cannot be disabled");
        }

        if ($plugin->is_locked) {
            throw new PluginStateException("Plugin '{$name}' is locked");
        }

        $canDisable = $this->canDisable($name);
        if (!$canDisable['can_disable'] && !$force) {
            throw new PluginStateException(
                "Cannot disable '{$name}': required by - " . implode(', ', $canDisable['blocking_plugins'])
            );
        }

        $this->repository->disable($name);

        $this->repository->addAuditLog([
            'plugin_id' => $plugin->id,
            'user_id' => $userId,
            'action' => $force ? 'force_disable' : 'disable',
            'previous_state' => ['is_enabled' => true],
            'new_state' => ['is_enabled' => false],
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $this->clearCaches();
        return true;
    }

    public function canEnable(string $name): array
    {
        $plugin = $this->repository->findByName($name);
        if (null === $plugin) {
            return ['can_enable' => false, 'missing_dependencies' => ['Plugin not found']];
        }

        $dependencies = $this->repository->getDependencies($plugin->id);
        $missing = [];

        foreach ($dependencies as $dep) {
            $dep = (array) $dep;
            if ($dep['is_optional']) {
                continue;
            }
            if (!$this->repository->isEnabled($dep['requires_plugin'])) {
                $missing[] = $dep['requires_plugin'];
            }
        }

        return ['can_enable' => empty($missing), 'missing_dependencies' => $missing];
    }

    public function canDisable(string $name): array
    {
        $dependents = $this->repository->getDependents($name);
        $blocking = array_map(fn($d) => $d->name, $dependents);
        return ['can_disable' => empty($blocking), 'blocking_plugins' => $blocking];
    }

    public function getDependencies(string $name): array
    {
        $plugin = $this->repository->findByName($name);
        if (null === $plugin) {
            return [];
        }
        $deps = $this->repository->getDependencies($plugin->id);
        return array_map(fn($d) => (array) $d, $deps);
    }

    public function getDependents(string $name): array
    {
        $dependents = $this->repository->getDependents($name);
        return array_map(fn($d) => (array) $d, $dependents);
    }

    public function resolveDependencyTree(string $name): array
    {
        $resolved = [];
        $this->resolveRecursive($name, $resolved, []);
        return $resolved;
    }

    protected function resolveRecursive(string $name, array &$resolved, array $seen): void
    {
        if (in_array($name, $seen, true)) {
            throw new PluginDependencyException("Circular dependency detected: {$name}");
        }
        if (in_array($name, $resolved, true)) {
            return;
        }

        $seen[] = $name;
        $plugin = $this->repository->findByName($name);
        if (null === $plugin) {
            return;
        }

        $dependencies = $this->repository->getDependencies($plugin->id);
        foreach ($dependencies as $dep) {
            $dep = (array) $dep;
            if (!$dep['is_optional']) {
                $this->resolveRecursive($dep['requires_plugin'], $resolved, $seen);
            }
        }
        $resolved[] = $name;
    }

    public function updateSettings(string $name, array $settings): bool
    {
        $plugin = $this->repository->findByName($name);
        if (null === $plugin) {
            throw new PluginNotFoundException("Plugin '{$name}' not found");
        }

        $currentSettings = $plugin->settings ? json_decode($plugin->settings, true) : [];
        $newSettings = array_merge($currentSettings, $settings);
        return $this->repository->update($name, ['settings' => $newSettings]);
    }

    public function syncPluginsFromFilesystem(string $pluginsPath): array
    {
        $results = ['added' => 0, 'updated' => 0, 'removed' => 0];
        $foundPlugins = [];

        $directories = glob($pluginsPath . '/*Plugin', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $pluginName = basename($dir);
            $foundPlugins[] = $pluginName;

            $config = $this->validatePluginConfig($dir);

            $pluginData = array_merge($config['config'], [
                'name' => $pluginName,
                'plugin_path' => $dir,
            ]);

            if ($this->repository->exists($pluginName)) {
                $this->repository->update($pluginName, $pluginData);
                ++$results['updated'];
            } else {
                $this->repository->create($pluginData);
                ++$results['added'];
            }
        }

        $dbPlugins = $this->repository->getAllPluginNames();
        $removed = array_diff($dbPlugins, $foundPlugins);

        foreach ($removed as $name) {
            $plugin = $this->repository->findByName($name);
            if ($plugin && !$plugin->is_core) {
                $this->repository->delete($name);
                ++$results['removed'];
            }
        }

        return $results;
    }

    public function clearCaches(): bool
    {
        $cleared = true;

        $cacheDirs = [
            $this->cachePath . '/config',
            $this->cachePath . '/routing',
        ];

        foreach ($cacheDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*.php');
                foreach ($files as $file) {
                    if (!unlink($file)) {
                        $cleared = false;
                    }
                }
            }
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        return $cleared;
    }

    public function getAuditLog(?string $pluginName = null, int $limit = 50): array
    {
        $pluginId = null;
        if (null !== $pluginName) {
            $plugin = $this->repository->findByName($pluginName);
            $pluginId = $plugin ? $plugin->id : null;
        }

        $logs = $this->repository->getAuditLog($pluginId, $limit);

        return array_map(function ($log) {
            $log = (array) $log;
            $log['previous_state'] = $log['previous_state'] ? json_decode($log['previous_state'], true) : null;
            $log['new_state'] = $log['new_state'] ? json_decode($log['new_state'], true) : null;
            return $log;
        }, $logs);
    }

    public function isEnabled(string $name): bool
    {
        return $this->repository->isEnabled($name);
    }

    public function validatePluginConfig(string $pluginPath): array
    {
        $result = ['valid' => false, 'errors' => [], 'config' => []];
        $configFile = $pluginPath . '/config/plugin.yml';

        if (!file_exists($configFile)) {
            $result['config'] = $this->inferConfigFromPlugin($pluginPath);
            $result['valid'] = true;
            return $result;
        }

        $yaml = file_get_contents($configFile);
        if (class_exists('sfYaml')) {
            $config = \sfYaml::load($yaml);
        } else {
            $config = [];
        }

        if (!is_array($config)) {
            $result['errors'][] = 'Invalid YAML format';
            return $result;
        }

        $result['config'] = $config;
        $result['valid'] = true;
        return $result;
    }

    protected function inferConfigFromPlugin(string $pluginPath): array
    {
        $name = basename($pluginPath);

        $category = 'general';
        if (str_starts_with($name, 'ar')) {
            $category = 'integration';
        } elseif (str_starts_with($name, 'sf')) {
            $category = 'core';
        } elseif (str_contains(strtolower($name), 'theme')) {
            $category = 'theme';
        }

        $isCore = in_array($name, self::CORE_PLUGINS, true);

        return [
            'class_name' => $name,
            'category' => $category,
            'is_core' => $isCore,
            'is_locked' => $isCore,
        ];
    }

    public function getCategories(): array
    {
        return self::CATEGORIES;
    }
}
