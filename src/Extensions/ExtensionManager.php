<?php

namespace AtomFramework\Extensions;

use AtomFramework\Extensions\Contracts\ExtensionManagerContract;
use AtomFramework\Extensions\Handlers\ExtensionDataHandler;
use AtomFramework\Helpers\PathResolver;
use AtomFramework\Repositories\ExtensionRepository;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;

class ExtensionManager implements ExtensionManagerContract
{
    protected ExtensionRepository $repository;
    protected ExtensionDataHandler $dataHandler;
    protected string $pluginsPath;
    protected array $manifestCache = [];

    public function __construct()
    {
        $this->repository = new ExtensionRepository();
        $this->dataHandler = new ExtensionDataHandler();
        $this->pluginsPath = $this->repository->getSetting('extensions_path', null, PathResolver::getPluginsDir());
    }

    /**
     * Discover all extensions with extension.json in plugins directory and atom-ahg-plugins
     */
    public function discover(bool $includeThemes = false): Collection
    {
        $extensions = collect();
        $found = []; // Track found plugins to avoid duplicates

        $enabledInAtomPlugin = [];
        try {
            $rows = DB::table('atom_plugin')->where('is_enabled', 1)->pluck('name')->toArray();
            $enabledInAtomPlugin = array_flip($rows);
        } catch (\Exception $e) {}

        // Directories to scan: plugins/ and atom-ahg-plugins/
        $dirsToScan = [];

        if (is_dir($this->pluginsPath)) {
            $dirsToScan[] = $this->pluginsPath;
        }

        // Also check atom-ahg-plugins directory (where local plugins live)
        $ahgPluginsPath = $this->getAhgPluginsPath();
        if ($ahgPluginsPath && is_dir($ahgPluginsPath)) {
            $dirsToScan[] = $ahgPluginsPath;
        }

        foreach ($dirsToScan as $basePath) {
            $dirs = glob($basePath . '/*Plugin', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                // Follow symlinks to get real path for manifest
                $realDir = is_link($dir) ? readlink($dir) : $dir;
                if (!$realDir || !is_dir($realDir)) {
                    continue;
                }

                $manifestPath = $realDir . '/extension.json';
                if (file_exists($manifestPath)) {
                    $manifest = $this->loadManifest($manifestPath);
                    if ($manifest) {
                        $machineName = $manifest['machine_name'] ?? $manifest['name'] ?? basename($dir);
                        $manifest['machine_name'] = $machineName;

                        // Skip if already found (avoid duplicates from symlinks)
                        if (isset($found[$machineName])) {
                            continue;
                        }
                        $found[$machineName] = true;

                        if (!$includeThemes && (!empty($manifest["is_theme"]) || ($manifest["category"] ?? "") === "theme")) {
                            continue;
                        }
                        $manifest['path'] = $realDir;
                        $manifest['is_registered'] = $this->repository->exists($machineName)
                            || isset($enabledInAtomPlugin[$machineName]);
                        $extensions->push($manifest);
                    }
                }
            }
        }
        return $extensions;
    }

    /**
     * Get the path to atom-ahg-plugins directory
     */
    public function getAhgPluginsPath(): ?string
    {
        // Try common locations relative to plugins path
        $atomRoot = dirname($this->pluginsPath);
        $possiblePaths = [
            $atomRoot . '/atom-ahg-plugins',
            dirname($atomRoot) . '/atom-ahg-plugins',
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if a plugin exists locally (in plugins/ or atom-ahg-plugins/)
     */
    public function findLocalPluginPath(string $machineName): ?string
    {
        // Check plugins/ directory first (including symlinks)
        $pluginPath = $this->pluginsPath . '/' . $machineName;
        if (is_dir($pluginPath)) {
            return is_link($pluginPath) ? readlink($pluginPath) : $pluginPath;
        }

        // Check atom-ahg-plugins/ directory
        $ahgPluginsPath = $this->getAhgPluginsPath();
        if ($ahgPluginsPath) {
            $ahgPath = $ahgPluginsPath . '/' . $machineName;
            if (is_dir($ahgPath)) {
                return $ahgPath;
            }
        }

        return null;
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }

    public function find(string $machineName): ?array
    {
        $extension = $this->repository->findByMachineName($machineName);
        if (!$extension) {
            return null;
        }

        $result = (array)$extension;
        foreach (['theme_support', 'dependencies', 'optional_dependencies', 'tables_created', 'shared_tables', 'helpers'] as $field) {
            if (!empty($result[$field])) {
                $result[$field] = json_decode($result[$field], true);
            }
        }
        return $result;
    }

    /**
     * Get plugin dependencies from extension.json (excludes composer packages)
     */
    public function getDependencies(string $machineName): array
    {
        $manifest = $this->findManifest($machineName);
        $deps = $manifest['dependencies'] ?? [];

        // Filter out composer packages (contain '/')
        return array_values(array_filter($deps, fn($dep) => strpos($dep, '/') === false));
    }

    /**
     * Get composer dependencies from extension.json
     */
    public function getComposerDependencies(string $machineName): array
    {
        $manifest = $this->findManifest($machineName);
        $deps = $manifest['dependencies'] ?? [];

        // Only return composer packages (contain '/')
        return array_values(array_filter($deps, fn($dep) => strpos($dep, '/') !== false));
    }

    /**
     * Check if composer dependencies are installed
     */
    public function checkComposerDependencies(string $machineName): array
    {
        $deps = $this->getComposerDependencies($machineName);
        $missing = [];

        foreach ($deps as $package) {
            // Check if package is installed in vendor
            $parts = explode('/', $package);
            $vendorPath = dirname($this->pluginsPath) . '/vendor/' . $package;
            $frameworkVendor = dirname($this->pluginsPath) . '/atom-framework/vendor/' . $package;

            if (!is_dir($vendorPath) && !is_dir($frameworkVendor)) {
                $missing[] = $package;
            }
        }

        return $missing;
    }

    /**
     * Get dependents from extension.json
     */
    public function getDependents(string $machineName): array
    {
        $manifest = $this->findManifest($machineName);
        return $manifest['dependents'] ?? [];
    }

    /**
     * Scan ALL plugins to find who depends on the given plugin
     */
    public function findAllDependents(string $machineName): array
    {
        $dependents = [];
        $found = []; // Track processed plugins to avoid duplicates

        // Scan both plugins/ and atom-ahg-plugins/
        $dirsToScan = [];
        if (is_dir($this->pluginsPath)) {
            $dirsToScan[] = $this->pluginsPath;
        }
        $ahgPluginsPath = $this->getAhgPluginsPath();
        if ($ahgPluginsPath && is_dir($ahgPluginsPath)) {
            $dirsToScan[] = $ahgPluginsPath;
        }

        foreach ($dirsToScan as $basePath) {
            $dirs = glob($basePath . '/*Plugin', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $realDir = is_link($dir) ? readlink($dir) : $dir;
                if (!$realDir || !is_dir($realDir)) {
                    continue;
                }

                $manifestPath = $realDir . '/extension.json';
                if (file_exists($manifestPath)) {
                    $manifest = $this->loadManifest($manifestPath);
                    if ($manifest) {
                        $pluginName = $manifest['machine_name'] ?? basename($dir);

                        // Skip if already processed
                        if (isset($found[$pluginName])) {
                            continue;
                        }
                        $found[$pluginName] = true;

                        $deps = $manifest['dependencies'] ?? [];
                        if (in_array($machineName, $deps)) {
                            $dependents[] = $pluginName;
                        }
                    }
                }
            }
        }
        return $dependents;
    }

    /**
     * Install an extension (with dependency chain)
     */
    public function install(string $machineName, bool $installDependencies = true): bool
    {
        if ($this->isInstalled($machineName)) {
            // Allow reinstall if previously uninstalled (pending_removal)
            $extension = $this->repository->findByMachineName($machineName);
            $plugin = DB::table('atom_plugin')->where('name', $machineName)->first();

            $isPendingRemoval = ($extension && $extension->status === 'pending_removal')
                || ($plugin && isset($plugin->status) && $plugin->status === 'pending_removal');

            if (!$isPendingRemoval) {
                throw new \RuntimeException("Extension '{$machineName}' is already installed.");
            }

            // Clean up old records for fresh reinstall
            if ($extension) {
                $this->repository->cancelPendingDeletion($machineName);
                DB::table('atom_extension')->where('machine_name', $machineName)->delete();
            }
            if ($plugin) {
                DB::table('atom_plugin')->where('name', $machineName)->delete();
            }
        }

        $manifest = $this->findManifest($machineName);
        if (!$manifest) {
            throw new \RuntimeException("Extension '{$machineName}' not found or missing extension.json");
        }

        // Check composer dependencies first
        $missingComposer = $this->checkComposerDependencies($machineName);
        if (!empty($missingComposer)) {
            $packageList = implode(' ', $missingComposer);
            throw new \RuntimeException(
                "Missing composer dependencies: " . implode(', ', $missingComposer) . "\n" .
                "Install with: cd atom-framework && composer require {$packageList}"
            );
        }

        // Install plugin dependencies first (excludes composer packages)
        if ($installDependencies) {
            $dependencies = $this->getDependencies($machineName);
            foreach ($dependencies as $dep) {
                if (!$this->isInstalled($dep)) {
                    echo "  Installing dependency: {$dep}\n";
                    $this->install($dep, true);
                }
                if (!$this->isEnabled($dep)) {
                    echo "  Enabling dependency: {$dep}\n";
                    $this->enable($dep, false);
                }
            }
        }

        $this->checkPhpExtensions($manifest);
        $this->checkDependencies($manifest);

        $id = $this->repository->create([
            'machine_name' => $machineName,
            'display_name' => $manifest['name'] ?? $machineName,
            'version' => $manifest['version'] ?? '1.0.0',
            'description' => $manifest['description'] ?? null,
            'author' => $manifest['author'] ?? null,
            'license' => $manifest['license'] ?? 'GPL-3.0',
            'status' => 'installed',
            'theme_support' => json_encode($manifest['theme_support'] ?? []),
            'requires_framework' => $manifest['requires']['atom_framework'] ?? null,
            'requires_atom' => $manifest['requires']['atom'] ?? null,
            'requires_php' => $manifest['requires']['php'] ?? null,
            'dependencies' => json_encode($manifest['dependencies'] ?? []),
            'optional_dependencies' => json_encode($manifest['optional']['extensions'] ?? []),
            'tables_created' => json_encode($manifest['tables'] ?? []),
            'shared_tables' => json_encode($manifest['shared_tables'] ?? []),
            'helpers' => json_encode($manifest['helpers'] ?? []),
            'install_task' => $manifest['install_task'] ?? null,
            'uninstall_task' => $manifest['uninstall_task'] ?? null,
            'config_path' => $this->pluginsPath . '/' . $machineName . '/extension.json',
            'installed_at' => date('Y-m-d H:i:s'),
        ]);

        if (!empty($manifest['install_task'])) {
            $this->runSymfonyTask($manifest['install_task']);
        }

        // A plugin brings its own schema. install_sql stays honoured for anything that
        // names a different file, but database/install.sql is the convention and is
        // used when the manifest is silent - which is almost all of them.
        //
        // This matters more since the framework installer stopped creating every
        // plugin's tables: without the fallback, installing a plugin whose manifest
        // omits install_sql gives you a plugin with no tables and no warning.
        $manifestSql = $manifest["install_sql"] ?? null;
        if (empty($manifestSql) && file_exists($this->pluginsPath.'/'.$machineName.'/database/install.sql')) {
            $manifestSql = 'database/install.sql';
        }

        if (!empty($manifestSql)) {
            $sqlPath = $this->pluginsPath . "/" . $machineName . "/" . $manifestSql;
            if (file_exists($sqlPath)) {
                $this->runSqlFile($sqlPath);
            }
        }

        $this->repository->logAction($machineName, 'installed', $id, null, [
            'version' => $manifest['version'] ?? '1.0.0',
        ]);

        return true;
    }

    /**
     * Uninstall an extension
     */
    public function uninstall(string $machineName, bool $backup = true): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        if (!$extension) {
            throw new \RuntimeException("Extension '{$machineName}' is not installed.");
        }

        // Check for enabled dependents
        $dependents = $this->findAllDependents($machineName);
        $enabledDependents = array_filter($dependents, fn($dep) => $this->isEnabled($dep));
        
        if (!empty($enabledDependents)) {
            throw new \RuntimeException(
                "Cannot uninstall '{$machineName}': These plugins depend on it: " . 
                implode(', ', $enabledDependents) . ". Disable them first."
            );
        }

        if ($backup) {
            $this->dataHandler->backup($machineName, $extension);
        }

        $tables = json_decode($extension->tables_created ?? '[]', true);
        $gracePeriod = (int)$this->repository->getSetting('grace_period_days', null, 30);
        $deleteAfter = new \DateTime("+{$gracePeriod} days");

        foreach ($tables as $table) {
            $recordCount = $this->dataHandler->getTableRecordCount($table);
            $backupPath = $backup ? $this->dataHandler->getBackupPath($machineName) : null;
            $this->repository->queueForDeletion($machineName, $table, $recordCount, $backupPath, $deleteAfter);
        }

        if (!empty($extension->uninstall_task)) {
            $this->runSymfonyTask($extension->uninstall_task);
        }

        $this->repository->update($extension->id, ['status' => 'pending_removal']);

        DB::table('atom_plugin')
            ->where('name', $machineName)
            ->update(['is_enabled' => 0, 'status' => 'pending_removal', 'disabled_at' => date('Y-m-d H:i:s')]);

        $this->updateSymfonyPlugins($machineName, false);
        $this->repository->logAction($machineName, 'uninstalled', $extension->id, null, [
            'backup' => $backup, 'grace_period_days' => $gracePeriod,
        ]);

        return true;
    }

    /**
     * Enable an extension (auto-enables dependencies)
     */
    public function enable(string $machineName, bool $enableDependencies = true): bool
    {
        $extension = $this->repository->findByMachineName($machineName);

        if (!$extension) {
            $plugin = DB::table('atom_plugin')->where('name', $machineName)->first();
            if (!$plugin) {
                // Check if plugin exists locally (has extension.json and symlink)
                $manifest = $this->findManifest($machineName);
                if ($manifest) {
                    // Plugin exists locally - enable it via atom_plugin
                    return $this->enableInAtomPlugin($machineName, $enableDependencies);
                }
                throw new \RuntimeException("Extension '{$machineName}' is not installed.");
            }
            return $this->enableInAtomPlugin($machineName, $enableDependencies);
        }

        if ($extension->status === 'enabled') {
            return true;
        }

        if ($extension->status === 'pending_removal') {
            throw new \RuntimeException("Cannot enable extension pending removal. Use restore first.");
        }

        // Check composer dependencies
        $missingComposer = $this->checkComposerDependencies($machineName);
        if (!empty($missingComposer)) {
            $packageList = implode(' ', $missingComposer);
            throw new \RuntimeException(
                "Missing composer dependencies: " . implode(', ', $missingComposer) . "\n" .
                "Install with: cd atom-framework && composer require {$packageList}"
            );
        }

        // Enable dependencies first
        if ($enableDependencies) {
            $dependencies = $this->getDependencies($machineName);
            foreach ($dependencies as $dep) {
                if (!$this->isEnabled($dep)) {
                    echo "  Enabling dependency: {$dep}\n";
                    $this->enable($dep, true);
                }
            }
        }

        // Verify dependencies are enabled
        $dependencies = $this->getDependencies($machineName);
        foreach ($dependencies as $dep) {
            if (!$this->isEnabled($dep)) {
                throw new \RuntimeException(
                    "Cannot enable '{$machineName}': Required dependency '{$dep}' is not enabled."
                );
            }
        }

        $this->repository->update($extension->id, [
            'status' => 'enabled',
            'enabled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->logAction($machineName, 'enabled', $extension->id);

        $manifest = $this->findManifest($machineName);
        $loadOrder = $manifest['load_order'] ?? 100;
        $category = $manifest['category'] ?? 'ahg';
        
        try {
            DB::table('atom_plugin')->updateOrInsert(
                ['name' => $machineName],
                [
                    'class_name' => $machineName . 'Configuration',
                    'is_enabled' => 1,
                    'is_core' => 0,
                    'version' => $manifest['version'] ?? null,
                    'load_order' => $loadOrder,
                    'category' => $category,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {}

        $this->reconcilePluginSchema($machineName);
        $this->syncMenuEntries($machineName, true);
        $this->updateSymfonyPlugins($machineName, true);
        return true;
    }

    /**
     * Enable plugin in atom_plugin table only
     */
    protected function enableInAtomPlugin(string $machineName, bool $enableDependencies = true): bool
    {
        // Check composer dependencies first
        $missingComposer = $this->checkComposerDependencies($machineName);
        if (!empty($missingComposer)) {
            $packageList = implode(' ', $missingComposer);
            throw new \RuntimeException(
                "Missing composer dependencies: " . implode(', ', $missingComposer) . "\n" .
                "Install with: cd atom-framework && composer require {$packageList}"
            );
        }

        if ($enableDependencies) {
            $dependencies = $this->getDependencies($machineName);
            foreach ($dependencies as $dep) {
                if (!$this->isEnabled($dep)) {
                    echo "  Enabling dependency: {$dep}\n";
                    $this->enable($dep, true);
                }
            }
        }

        $dependencies = $this->getDependencies($machineName);
        foreach ($dependencies as $dep) {
            if (!$this->isEnabled($dep)) {
                throw new \RuntimeException(
                    "Cannot enable '{$machineName}': Required dependency '{$dep}' is not enabled."
                );
            }
        }

        $manifest = $this->findManifest($machineName);
        $loadOrder = $manifest['load_order'] ?? 100;
        $category = $manifest['category'] ?? 'ahg';

        DB::table('atom_plugin')->updateOrInsert(
            ['name' => $machineName],
            [
                'class_name' => $machineName . 'Configuration',
                'version' => $manifest['version'] ?? '1.0.0',
                'description' => $manifest['description'] ?? null,
                'is_enabled' => 1,
                'is_core' => 0,
                'load_order' => $loadOrder,
                'category' => $category,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->reconcilePluginSchema($machineName);
        $this->syncMenuEntries($machineName, true);
        $this->updateSymfonyPlugins($machineName, true);
        return true;
    }

    /**
     * Add or remove the navigation entries a plugin declares in its manifest.
     *
     * AtoM builds its Add/Manage/Import/Admin dropdowns from the `menu` table, so a
     * plugin only has to own a row there to appear in the navigation - no theme
     * override, no injection, and the entry stays editable at /menu/list like any
     * other. The catch is lifecycle: a row inserted at install outlives the plugin,
     * and QubitMenu::checkUserAccess() falls through to the app-wide security
     * default for a module that is no longer loaded, so a stale entry renders and
     * then errors when clicked. Tying the row to enable/disable is what makes the
     * table safe to write to.
     *
     * Declared as:
     *   "menu": [{"name": "feedback", "parent": "manage",
     *             "path": "feedback/browse", "label": "Feedback"}]
     */
    protected function syncMenuEntries(string $machineName, bool $enabled): void
    {
        $manifest = $this->findManifest($machineName);
        $entries = $manifest['menu'] ?? [];

        if (!is_array($entries)) {
            $entries = [];
        }

        try {
            // Driven by what this plugin owns, not by what the manifest currently
            // declares. Deriving removal from the manifest cannot remove an entry
            // that has been taken out of it - the row is simply never looked at
            // again - so dropping or renaming an entry orphaned it permanently.
            $owned = DB::table('atom_plugin_menu')->where('plugin_name', $machineName)->get();

            if (!$enabled) {
                foreach ($owned as $record) {
                    $this->releaseMenuRow($record);
                }

                return;
            }

            $declared = [];
            foreach ($entries as $entry) {
                if (!empty($entry['name']) && !empty($entry['path'])) {
                    $declared[$entry['name'].'|'.$entry['path']] = $entry;
                }
            }

            // Anything owned but no longer declared goes, which covers a manifest
            // that dropped an entry while the plugin stayed enabled.
            foreach ($owned as $record) {
                $key = $record->menu_name.'|'.$record->menu_path;

                if (isset($declared[$key])) {
                    unset($declared[$key]);   // already present and still wanted
                } else {
                    $this->releaseMenuRow($record);
                }
            }

            foreach ($declared as $entry) {
                // A row created before ownership was tracked, or added by hand, is
                // adopted rather than duplicated. Without this the first enable
                // after the upgrade would insert a second copy of every entry.
                $existing = DB::table('menu')
                    ->where('name', $entry['name'])
                    ->where('path', $entry['path'])
                    ->first();

                if ($existing) {
                    $this->recordMenuOwnership($machineName, (int) $existing->id, $entry);

                    continue;
                }

                $this->appendMenuNode($machineName, $entry);
            }
        } catch (\Exception $e) {
            // Navigation is cosmetic - never let it fail an enable or a disable.
            echo "  Warning: could not update menu entries for {$machineName}: {$e->getMessage()}\n";
        }
    }

    /**
     * Remove a menu row this plugin created, and forget that it owned it.
     *
     * The row may already be gone - an administrator can delete any entry from
     * /menu/list - in which case only the ownership record needs clearing.
     */
    protected function releaseMenuRow(object $record): void
    {
        $node = DB::table('menu')->where('id', $record->menu_id)->first();

        if ($node) {
            $this->removeMenuNode($node);
        }

        DB::table('atom_plugin_menu')->where('id', $record->id)->delete();
    }

    protected function recordMenuOwnership(string $machineName, int $menuId, array $entry): void
    {
        DB::table('atom_plugin_menu')->updateOrInsert(
            ['plugin_name' => $machineName, 'menu_id' => $menuId],
            [
                'menu_name' => $entry['name'],
                'menu_path' => $entry['path'],
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Insert a menu row as the last child of its parent, keeping lft/rgt consistent.
     */
    protected function appendMenuNode(string $machineName, array $entry): void
    {
        $parentName = $entry['parent'] ?? 'manage';
        $parent = DB::table('menu')->where('name', $parentName)->first();

        if (!$parent) {
            echo "  Warning: menu parent '{$parentName}' not found, skipping '{$entry['name']}'.\n";

            return;
        }

        $culture = $entry['culture'] ?? 'en';
        $boundary = (int) $parent->rgt;
        $now = date('Y-m-d H:i:s');

        // Open a two-unit gap at the parent's right edge, then fill it.
        DB::table('menu')->where('rgt', '>=', $boundary)->update(['rgt' => DB::raw('rgt + 2')]);
        DB::table('menu')->where('lft', '>', $boundary)->update(['lft' => DB::raw('lft + 2')]);

        $id = DB::table('menu')->insertGetId([
            'parent_id' => $parent->id,
            'name' => $entry['name'],
            'path' => $entry['path'],
            'lft' => $boundary,
            'rgt' => $boundary + 1,
            'created_at' => $now,
            'updated_at' => $now,
            'source_culture' => $culture,
            'serial_number' => 0,
        ]);

        DB::table('menu_i18n')->insert([
            'id' => $id,
            'culture' => $culture,
            'label' => $entry['label'] ?? $entry['name'],
            'description' => $entry['description'] ?? null,
        ]);

        $this->recordMenuOwnership($machineName, (int) $id, $entry);
    }

    /**
     * Delete a menu row and close the gap it leaves in the tree.
     */
    protected function removeMenuNode(object $node): void
    {
        $left = (int) $node->lft;
        $right = (int) $node->rgt;
        $width = $right - $left + 1;

        // menu_i18n cascades on the foreign key, so the label goes with it.
        DB::table('menu')->where('id', $node->id)->delete();

        DB::table('menu')->where('lft', '>', $right)->update(['lft' => DB::raw("lft - {$width}")]);
        DB::table('menu')->where('rgt', '>', $right)->update(['rgt' => DB::raw("rgt - {$width}")]);
    }

    /**
     * Disable an extension (auto-disables dependents)
     */
    public function disable(string $machineName, bool $disableDependents = true): bool
    {
        $extension = $this->repository->findByMachineName($machineName);

        if (!$extension) {
            $plugin = DB::table('atom_plugin')->where('name', $machineName)->first();
            if (!$plugin) {
                throw new \RuntimeException("Extension '{$machineName}' is not installed.");
            }
            return $this->disableInAtomPlugin($machineName, $disableDependents);
        }

        if ($extension->status === 'disabled') {
            return true;
        }

        // Disable dependents first
        if ($disableDependents) {
            $dependents = $this->findAllDependents($machineName);
            foreach ($dependents as $dep) {
                if ($this->isEnabled($dep)) {
                    echo "  Disabling dependent: {$dep}\n";
                    $this->disable($dep, true);
                }
            }
        } else {
            $dependents = $this->findAllDependents($machineName);
            $enabledDependents = array_filter($dependents, fn($dep) => $this->isEnabled($dep));
            
            if (!empty($enabledDependents)) {
                throw new \RuntimeException(
                    "Cannot disable '{$machineName}': These plugins depend on it: " . 
                    implode(', ', $enabledDependents) . ". Use --cascade to disable them too."
                );
            }
        }

        $this->repository->update($extension->id, [
            'status' => 'disabled',
            'disabled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->logAction($machineName, 'disabled', $extension->id);

        try {
            DB::table('atom_plugin')->where('name', $machineName)->update(['is_enabled' => 0]);
        } catch (\Exception $e) {}

        $this->syncMenuEntries($machineName, false);
        $this->updateSymfonyPlugins($machineName, false);
        return true;
    }

    /**
     * Disable plugin in atom_plugin table only
     */
    protected function disableInAtomPlugin(string $machineName, bool $disableDependents = true): bool
    {
        if ($disableDependents) {
            $dependents = $this->findAllDependents($machineName);
            foreach ($dependents as $dep) {
                if ($this->isEnabled($dep)) {
                    echo "  Disabling dependent: {$dep}\n";
                    $this->disable($dep, true);
                }
            }
        } else {
            $dependents = $this->findAllDependents($machineName);
            $enabledDependents = array_filter($dependents, fn($dep) => $this->isEnabled($dep));
            
            if (!empty($enabledDependents)) {
                throw new \RuntimeException(
                    "Cannot disable '{$machineName}': These plugins depend on it: " . implode(', ', $enabledDependents)
                );
            }
        }

        DB::table('atom_plugin')->where('name', $machineName)->update(['is_enabled' => 0, 'disabled_at' => date('Y-m-d H:i:s')]);
        $this->syncMenuEntries($machineName, false);
        $this->updateSymfonyPlugins($machineName, false);
        return true;
    }

    public function restore(string $machineName): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        if (!$extension) {
            throw new \RuntimeException("Extension '{$machineName}' not found.");
        }
        if ($extension->status !== 'pending_removal') {
            throw new \RuntimeException("Extension '{$machineName}' is not pending removal.");
        }

        $this->repository->cancelPendingDeletion($machineName);
        $this->repository->update($extension->id, ['status' => 'disabled']);
        $this->repository->logAction($machineName, 'backup_restored', $extension->id);
        return true;
    }

    public function isInstalled(string $machineName): bool
    {
        if ($this->repository->exists($machineName)) {
            return true;
        }
        $plugin = DB::table('atom_plugin')->where('name', $machineName)->first();
        return $plugin !== null;
    }

    public function isEnabled(string $machineName): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        if ($extension) {
            return $extension->status === 'enabled';
        }
        $plugin = DB::table('atom_plugin')->where('name', $machineName)->first();
        return $plugin && $plugin->is_enabled == 1;
    }

    public function getSetting(string $key, ?int $extensionId = null, $default = null)
    {
        return $this->repository->getSetting($key, $extensionId, $default);
    }

    public function setSetting(string $key, $value, ?int $extensionId = null): bool
    {
        $type = match(true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
        return $this->repository->setSetting($key, $value, $extensionId, $type);
    }

    public function getAuditLog(?string $machineName = null, int $limit = 50): Collection
    {
        return $this->repository->getAuditLog($machineName, $limit);
    }

    public function processPendingDeletions(): array
    {
        $results = ['processed' => 0, 'failed' => 0, 'errors' => []];
        $pending = $this->repository->getPendingDeletions();

        foreach ($pending as $item) {
            try {
                $this->repository->updatePendingStatus($item->id, 'processing');
                $this->dataHandler->dropTable($item->table_name);
                $this->repository->updatePendingStatus($item->id, 'deleted');
                $results['processed']++;
            } catch (\Exception $e) {
                $this->repository->updatePendingStatus($item->id, 'failed', $e->getMessage());
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
            }
        }
        return $results;
    }

    /**
     * Get full dependency tree for display
     */
    public function getDependencyTree(string $machineName): array
    {
        $manifest = $this->findManifest($machineName);
        if (!$manifest) {
            return [];
        }
        return [
            'name' => $machineName,
            'dependencies' => $manifest['dependencies'] ?? [],
            'dependents' => $this->findAllDependents($machineName),
            'optional' => $manifest['optional']['extensions'] ?? [],
        ];
    }

    // ==========================================
    // Protected Methods
    // ==========================================

    protected function loadManifest(string $path): ?array
    {
        if (isset($this->manifestCache[$path])) {
            return $this->manifestCache[$path];
        }
        if (!file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path);
        $manifest = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        $this->manifestCache[$path] = $manifest;
        return $manifest;
    }

    protected function findManifest(string $machineName): ?array
    {
        // First try plugins/ directory (including symlinks)
        $path = $this->pluginsPath . '/' . $machineName . '/extension.json';
        if (file_exists($path)) {
            return $this->loadManifest($path);
        }

        // Also check atom-ahg-plugins/ directory
        $localPath = $this->findLocalPluginPath($machineName);
        if ($localPath) {
            $manifestPath = $localPath . '/extension.json';
            if (file_exists($manifestPath)) {
                return $this->loadManifest($manifestPath);
            }
        }

        return null;
    }

    /**
     * Check required PHP extensions
     */
    protected function checkPhpExtensions(array $manifest): void
    {
        $required = $manifest['requires']['php_extensions'] ?? [];
        if (empty($required)) {
            return;
        }
        
        $missing = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if (!empty($missing)) {
            $extList = implode(', ', $missing);
            $installCmds = [];
            foreach ($missing as $ext) {
                $installCmds[] = "sudo apt-get install php" . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "-{$ext}";
            }
            throw new \RuntimeException(
                "Missing required PHP extensions: {$extList}\n" .
                "Install with:\n  " . implode("\n  ", $installCmds) . "\n" .
                "Then: sudo systemctl restart php" . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "-fpm"
            );
        }
    }

    protected function checkDependencies(array $manifest): void
    {
        // Filter out composer packages (contain '/')
        $dependencies = $manifest['dependencies'] ?? [];
        $pluginDeps = array_filter($dependencies, fn($dep) => strpos($dep, '/') === false);

        foreach ($pluginDeps as $dep) {
            if (!$this->isEnabled($dep)) {
                throw new \RuntimeException("Required dependency '{$dep}' is not installed or enabled.");
            }
        }
        if (!empty($manifest['requires']['php'])) {
            $required = ltrim($manifest['requires']['php'], '>=<');
            if (version_compare(PHP_VERSION, $required, '<')) {
                throw new \RuntimeException("PHP {$manifest['requires']['php']} required, you have " . PHP_VERSION);
            }
        }
    }

    protected function runSymfonyTask(string $task): void
    {
        $atomPath = dirname($this->pluginsPath);
        $command = "cd {$atomPath} && php symfony {$task} 2>&1";
        exec($command, $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \RuntimeException("Task '{$task}' failed: " . implode("\n", $output));
        }
    }

    protected function runSqlFile(string $sqlPath): void
    {
        $sql = file_get_contents($sqlPath);
        if (empty($sql)) {
            return;
        }

        $pdo = $this->schemaConnection();
        if (null === $pdo) {
            return;
        }

        try {
            $pdo->exec($sql);
            $this->reconcileSchema($pdo, $sql);
        } catch (\Exception $e) {
            error_log("runSqlFile ERROR: " . $e->getMessage());
        }
    }

    /**
     * A direct connection for schema work.
     *
     * Not the Capsule connection: this runs from install and enable, which the CLI
     * reaches before the application has booted its database layer.
     */
    protected function schemaConnection(): ?\PDO
    {
        $configPath = dirname($this->pluginsPath) . '/config/config.php';
        if (!file_exists($configPath)) {
            return null;
        }

        $config = require $configPath;
        $params = $config['all']['propel']['param'] ?? [];
        $dsnParts = [];

        foreach (explode(';', preg_replace('/^[a-z]+:/', '', $params['dsn'] ?? '')) as $part) {
            if (false !== strpos($part, '=')) {
                list($key, $value) = explode('=', $part, 2);
                $dsnParts[trim($key)] = trim($value);
            }
        }

        try {
            return new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $dsnParts['host'] ?? 'localhost',
                    $dsnParts['port'] ?? 3306,
                    $dsnParts['dbname'] ?? 'atom'
                ),
                $params['username'] ?? 'root',
                $params['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Reconcile a plugin's tables against its declared schema, without re-running
     * the file.
     *
     * Called on enable, which is the only lifecycle step an already-installed
     * plugin passes through. Executing install.sql again here would re-run its
     * seed INSERTs, so only the ALTER work is done.
     */
    public function reconcilePluginSchema(string $machineName): void
    {
        $manifest = $this->findManifest($machineName);
        $relative = $manifest['install_sql'] ?? null;

        if (empty($relative)) {
            $relative = 'database/install.sql';
        }

        $sqlPath = $this->pluginsPath . '/' . $machineName . '/' . $relative;
        if (!file_exists($sqlPath)) {
            return;
        }

        $sql = file_get_contents($sqlPath);
        $pdo = $this->schemaConnection();

        if (empty($sql) || null === $pdo) {
            return;
        }

        try {
            $this->reconcileSchema($pdo, $sql);
        } catch (\Exception $e) {
            echo "  Warning: schema reconciliation failed for {$machineName}: {$e->getMessage()}\n";
        }
    }

    /**
     * Bring already-existing tables up to the schema the plugin declares.
     *
     * Every table is created with CREATE TABLE IF NOT EXISTS, which is a no-op once
     * the table exists. So a column added to a plugin's install.sql reaches new
     * installs and never reaches any install that already has the table - the
     * declared schema and the live schema drift apart silently and stay that way.
     * The failure surfaces much later as a 500 from a column the code writes and the
     * database has never heard of.
     *
     * Two real cases this was written for: favorites was missing seven of its
     * fourteen declared columns, so adding a favourite was a 500; and
     * feedback_i18n.status_id was declared NOT NULL DEFAULT 1030 but existed as
     * NOT NULL with no default, so submitting feedback was a 500 under
     * STRICT_TRANS_TABLES - on production as well as on a test install.
     *
     * Deliberately additive only. Missing columns are added and a declared default
     * is applied where the live column has none. Nothing is dropped, no type is
     * changed, and no column the database has but the manifest does not is touched -
     * this runs against client databases, so anything it cannot do safely it leaves
     * alone and reports.
     */
    protected function reconcileSchema(\PDO $pdo, string $sql): void
    {
        foreach ($this->parseCreateTables($sql) as $table => $declared) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            } catch (\Exception $e) {
                continue;   // table absent: the CREATE above will have handled it
            }

            $live = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $live[$row['Field']] = $row;
            }

            if (empty($live)) {
                continue;
            }

            foreach ($declared as $column => $definition) {
                try {
                    if (!isset($live[$column])) {
                        // Added nullable regardless of what the manifest says. An
                        // existing table has rows, and NOT NULL without a default
                        // cannot be added to a populated table at all.
                        $safe = $this->nullableDefinition($definition);
                        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$safe}");
                        echo "  Schema: added {$table}.{$column}\n";

                        continue;
                    }

                    // Column present but the declared default never applied.
                    $default = $this->declaredDefault($definition);
                    if (null !== $default && null === $live[$column]['Default']) {
                        $pdo->exec("ALTER TABLE `{$table}` ALTER COLUMN `{$column}` SET DEFAULT {$default}");
                        echo "  Schema: set default on {$table}.{$column}\n";
                    }
                } catch (\Exception $e) {
                    echo "  Schema: could not reconcile {$table}.{$column} - {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Column definitions per table, from the CREATE TABLE statements in a SQL file.
     *
     * Split at depth zero and outside quotes rather than on commas: a type carries
     * parenthesised arguments and a COMMENT can contain a comma, both of which a
     * naive explode() would tear in half.
     */
    protected function parseCreateTables(string $sql): array
    {
        $tables = [];

        // Strip comments first. These files carry -- notes explaining why a column
        // exists, and a note wrapping onto its own line reads as a column named
        // after its first word, producing nonsense ALTERs.
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/^\s*--[^\n]*$/m', '', $sql);
        $sql = preg_replace('/\s--\s[^\n]*$/m', '', $sql);

        if (!preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*(?:ENGINE|;)/is', $sql, $matches, PREG_SET_ORDER)) {
            return $tables;
        }

        foreach ($matches as $match) {
            $columns = [];

            foreach ($this->splitTopLevel($match[2]) as $line) {
                $line = trim($line);

                // Keys, constraints and indexes are not columns.
                if (preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN)\b/i', $line)) {
                    continue;
                }
                if (!preg_match('/^`?([a-zA-Z0-9_]+)`?\s+(.+)$/s', $line, $column)) {
                    continue;
                }

                $columns[$column[1]] = trim($column[2]);
            }

            if ($columns) {
                $tables[$match[1]] = $columns;
            }
        }

        return $tables;
    }

    protected function splitTopLevel(string $body): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($body);

        for ($i = 0; $i < $length; ++$i) {
            $char = $body[$i];

            if (null !== $quote) {
                $current .= $char;
                if ($char === $quote && ($i === 0 || '\\' !== $body[$i - 1])) {
                    $quote = null;
                }

                continue;
            }

            if ("'" === $char || '"' === $char || '`' === $char) {
                $quote = $char;
                $current .= $char;

                continue;
            }

            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif (',' === $char && 0 === $depth) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ('' !== trim($current)) {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * The DEFAULT literal a definition declares, or null if it declares none.
     */
    protected function declaredDefault(string $definition): ?string
    {
        if (!preg_match("/\\bDEFAULT\\s+('(?:[^']|'')*'|[^\\s,]+)/i", $definition, $match)) {
            return null;
        }

        $value = trim($match[1]);

        // CURRENT_TIMESTAMP and friends are expressions, not literals, and are not
        // worth special-casing here - skip rather than emit something invalid.
        return preg_match('/^(NULL|CURRENT_TIMESTAMP)/i', $value) ? null : $value;
    }

    /**
     * A definition safe to add to a table that already holds rows.
     */
    protected function nullableDefinition(string $definition): string
    {
        $definition = preg_replace('/\bAUTO_INCREMENT\b/i', '', $definition);
        $definition = preg_replace('/\bPRIMARY\s+KEY\b/i', '', $definition);

        if (!preg_match('/\bDEFAULT\b/i', $definition)) {
            $definition = preg_replace('/\bNOT\s+NULL\b/i', 'NULL', $definition);
        }

        return trim(preg_replace('/\s+/', ' ', $definition));
    }

    private function updateSymfonyPlugins(string $machineName, bool $add): void
    {
        try {
            $row = DB::table('setting_i18n')->where('id', 1)->where('culture', \AtomExtensions\Helpers\CultureHelper::getCulture())->first();
            if (!$row || empty($row->value)) {
                return;
            }
            $plugins = @unserialize($row->value, ['allowed_classes' => false]); // plugin-name list; no objects (security audit 2026-06-15)
            if (!is_array($plugins)) {
                return;
            }
            $key = array_search($machineName, $plugins);
            $changed = false;

            if ($add && $key === false) {
                $plugins[] = $machineName;
                $changed = true;
            } elseif (!$add && $key !== false) {
                unset($plugins[$key]);
                $plugins = array_values($plugins);
                $changed = true;
            }

            $ordered = self::orderCoreFirst($plugins);

            if ($changed || $ordered !== $plugins) {
                DB::table('setting_i18n')->where('id', 1)->where('culture', \AtomExtensions\Helpers\CultureHelper::getCulture())->update(['value' => serialize($ordered)]);
            }
        } catch (\Exception $e) {}
    }

    /**
     * Move ahgCorePlugin ahead of every other AHG plugin in the load list.
     *
     * sfPluginAdminPlugin initialises plugins in the order this list gives, and
     * ahgCorePluginConfiguration::bootstrapFramework() is what registers the
     * AtomFramework autoloader. Any AHG plugin initialised before it references
     * classes that do not exist yet, which fatals during configuration and
     * returns an empty 200 for every page on the site rather than an error on
     * one of them.
     *
     * Until now the order was whatever sequence the plugins happened to be
     * enabled in, so this worked by accident. Base AtoM plugins keep their
     * relative positions; only the AHG block is touched.
     */
    private static function orderCoreFirst(array $plugins): array
    {
        $position = array_search('ahgCorePlugin', $plugins, true);

        if (false === $position) {
            return $plugins;
        }

        $firstAhg = null;
        foreach ($plugins as $index => $name) {
            if (0 === strpos((string) $name, 'ahg')) {
                $firstAhg = $index;

                break;
            }
        }

        if (null === $firstAhg || $firstAhg >= $position) {
            return $plugins;
        }

        unset($plugins[$position]);
        $plugins = array_values($plugins);
        array_splice($plugins, $firstAhg, 0, 'ahgCorePlugin');

        return $plugins;
    }

    public function updateVersion(string $machineName, string $newVersion): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        if (!$extension) {
            throw new \RuntimeException("Extension '{$machineName}' is not installed.");
        }
        return $this->repository->update($extension->id, [
            'version' => $newVersion,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function logAudit(string $machineName, string $action, ?array $details = null): void
    {
        $extension = $this->repository->findByMachineName($machineName);
        $extensionId = $extension ? $extension->id : null;
        $this->repository->logAction($machineName, $action, $extensionId, null, $details);
    }
}
