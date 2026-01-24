<?php

namespace AtomFramework\Extensions;

use AtomFramework\Extensions\Contracts\ExtensionManagerContract;
use AtomFramework\Extensions\Handlers\ExtensionDataHandler;
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
        $this->pluginsPath = $this->repository->getSetting('extensions_path', null, defined('ATOM_ROOT') ? ATOM_ROOT . '/plugins' : '/var/www/atom/plugins');
    }

    /**
     * Discover all extensions with extension.json in plugins directory
     */
    public function discover(bool $includeThemes = false): Collection
    {
        $extensions = collect();
        if (!is_dir($this->pluginsPath)) {
            return $extensions;
        }

        $enabledInAtomPlugin = [];
        try {
            $rows = DB::table('atom_plugin')->where('is_enabled', 1)->pluck('name')->toArray();
            $enabledInAtomPlugin = array_flip($rows);
        } catch (\Exception $e) {}

        $dirs = glob($this->pluginsPath . '/*Plugin', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $manifestPath = $dir . '/extension.json';
            if (file_exists($manifestPath)) {
                $manifest = $this->loadManifest($manifestPath);
                if ($manifest) {
                    if (!$includeThemes && (!empty($manifest["is_theme"]) || ($manifest["category"] ?? "") === "theme")) {
                        continue;
                    }
                    $manifest['path'] = $dir;
                    $machineName = $manifest['machine_name'] ?? basename($dir);
                    $manifest['is_registered'] = $this->repository->exists($machineName)
                        || isset($enabledInAtomPlugin[$machineName]);
                    $extensions->push($manifest);
                }
            }
        }
        return $extensions;
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
     * Get dependencies from extension.json
     */
    public function getDependencies(string $machineName): array
    {
        $manifest = $this->findManifest($machineName);
        return $manifest['dependencies'] ?? [];
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
        $dirs = glob($this->pluginsPath . '/*Plugin', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $manifestPath = $dir . '/extension.json';
            if (file_exists($manifestPath)) {
                $manifest = $this->loadManifest($manifestPath);
                if ($manifest) {
                    $deps = $manifest['dependencies'] ?? [];
                    if (in_array($machineName, $deps)) {
                        $dependents[] = $manifest['machine_name'] ?? basename($dir);
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
            throw new \RuntimeException("Extension '{$machineName}' is already installed.");
        }

        $manifest = $this->findManifest($machineName);
        if (!$manifest) {
            throw new \RuntimeException("Extension '{$machineName}' not found or missing extension.json");
        }

        // Install dependencies first
        if ($installDependencies) {
            $dependencies = $manifest['dependencies'] ?? [];
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

        if (!empty($manifest["install_sql"])) {
            $sqlPath = $this->pluginsPath . "/" . $machineName . "/" . $manifest["install_sql"];
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

        $this->updateSymfonyPlugins($machineName, true);
        return true;
    }

    /**
     * Enable plugin in atom_plugin table only
     */
    protected function enableInAtomPlugin(string $machineName, bool $enableDependencies = true): bool
    {
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

        $this->updateSymfonyPlugins($machineName, true);
        return true;
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
        $path = $this->pluginsPath . '/' . $machineName . '/extension.json';
        return $this->loadManifest($path);
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
        $dependencies = $manifest['dependencies'] ?? [];
        foreach ($dependencies as $dep) {
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
        $configPath = dirname($this->pluginsPath) . '/config/config.php';
        if (!file_exists($configPath)) {
            return;
        }
        $config = require $configPath;
        $params = $config['all']['propel']['param'] ?? [];
        $dsn = $params['dsn'] ?? '';
        $dsnParts = [];
        $dsnWithoutDriver = preg_replace('/^[a-z]+:/', '', $dsn);
        foreach (explode(';', $dsnWithoutDriver) as $part) {
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $dsnParts[trim($key)] = trim($value);
            }
        }
        try {
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
            $pdo->exec($sql);
        } catch (\Exception $e) {
            error_log("runSqlFile ERROR: " . $e->getMessage());
        }
    }

    private function updateSymfonyPlugins(string $machineName, bool $add): void
    {
        try {
            $row = DB::table('setting_i18n')->where('id', 1)->where('culture', 'en')->first();
            if (!$row || empty($row->value)) {
                return;
            }
            $plugins = @unserialize($row->value);
            if (!is_array($plugins)) {
                return;
            }
            $key = array_search($machineName, $plugins);
            if ($add && $key === false) {
                $plugins[] = $machineName;
                DB::table('setting_i18n')->where('id', 1)->where('culture', 'en')->update(['value' => serialize($plugins)]);
            } elseif (!$add && $key !== false) {
                unset($plugins[$key]);
                $plugins = array_values($plugins);
                DB::table('setting_i18n')->where('id', 1)->where('culture', 'en')->update(['value' => serialize($plugins)]);
            }
        } catch (\Exception $e) {}
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
