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
        $this->pluginsPath = $this->repository->getSetting('extensions_path', null, defined('ATOM_ROOT') ? ATOM_ROOT . '/plugins' : '/usr/share/nginx/atom/plugins');
    }

    /**
     * Discover all extensions with extension.json in plugins directory
     */
    public function discover(): Collection
    {
        $extensions = collect();
        
        if (!is_dir($this->pluginsPath)) {
            return $extensions;
        }

        $dirs = glob($this->pluginsPath . '/*Plugin', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $manifestPath = $dir . '/extension.json';
            
            if (file_exists($manifestPath)) {
                $manifest = $this->loadManifest($manifestPath);
                if ($manifest) {
                    $manifest['path'] = $dir;
                    $manifest['is_registered'] = $this->repository->exists($manifest['machine_name'] ?? basename($dir));
                    if (empty($manifest["is_theme"]) && ($manifest["category"] ?? "") !== "theme") { $extensions->push($manifest); }
                }
            }
        }

        return $extensions;
    }

    /**
     * Get all registered extensions from database
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Get extensions by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }

    /**
     * Find extension by machine name
     */
    public function find(string $machineName): ?array
    {
        $extension = $this->repository->findByMachineName($machineName);
        
        if (!$extension) {
            return null;
        }

        $result = (array)$extension;
        
        // Decode JSON fields
        foreach (['theme_support', 'dependencies', 'optional_dependencies', 'tables_created', 'shared_tables', 'helpers'] as $field) {
            if (!empty($result[$field])) {
                $result[$field] = json_decode($result[$field], true);
            }
        }

        return $result;
    }

    /**
     * Install an extension
     */
    public function install(string $machineName): bool
    {
        // Check if already installed
        if ($this->isInstalled($machineName)) {
            throw new \RuntimeException("Extension '{$machineName}' is already installed.");
        }

        // Find manifest
        $manifest = $this->findManifest($machineName);
        if (!$manifest) {
            throw new \RuntimeException("Extension '{$machineName}' not found or missing extension.json");
        }

        // Check dependencies
        $this->checkDependencies($manifest);

        // Create database record
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

        // Run install task if defined
        if (!empty($manifest['install_task'])) {
            $this->runSymfonyTask($manifest['install_task']);
        }

        // Log action
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

        // Backup if requested
        if ($backup) {
            $this->dataHandler->backup($machineName, $extension);
        }

        // Queue tables for deletion
        $tables = json_decode($extension->tables_created ?? '[]', true);
        $gracePeriod = (int)$this->repository->getSetting('grace_period_days', null, 30);
        $deleteAfter = new \DateTime("+{$gracePeriod} days");

        foreach ($tables as $table) {
            $recordCount = $this->dataHandler->getTableRecordCount($table);
            $backupPath = $backup ? $this->dataHandler->getBackupPath($machineName) : null;
            
            $this->repository->queueForDeletion(
                $machineName,
                $table,
                $recordCount,
                $backupPath,
                $deleteAfter
            );
        }

        // Run uninstall task if defined
        if (!empty($extension->uninstall_task)) {
            $this->runSymfonyTask($extension->uninstall_task);
        }

        // Update status
        $this->repository->update($extension->id, [
            'status' => 'pending_removal',
        ]);

        // Log action
        $this->repository->logAction($machineName, 'uninstalled', $extension->id, null, [
            'backup' => $backup,
            'grace_period_days' => $gracePeriod,
        ]);

        return true;
    }

    /**
     * Enable an extension
     */
    public function enable(string $machineName): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        
        if (!$extension) {
            throw new \RuntimeException("Extension '{$machineName}' is not installed.");
        }

        if ($extension->status === 'enabled') {
            return true;
        }

        if ($extension->status === 'pending_removal') {
            throw new \RuntimeException("Cannot enable extension pending removal. Use restore first.");
        }

        $this->repository->update($extension->id, [
            'status' => 'enabled',
            'enabled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->logAction($machineName, 'enabled', $extension->id);

	// Register in atom_plugin for Symfony plugin loading
        try {
            DB::table('atom_plugin')->updateOrInsert(
                ['name' => $machineName],
                [
                    'class_name' => $machineName . 'Configuration',
                    'is_enabled' => 1,
                    'is_core' => 0,
                    'load_order' => 100,
                    'category' => 'ahg',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            // Silently continue if atom_plugin table doesn't exist
        }

        $this->updateSymfonyPlugins($machineName, true);

        return true;
    }

    /**
     * Disable an extension
     */
    public function disable(string $machineName): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        
        if (!$extension) {
            throw new \RuntimeException("Extension '{$machineName}' is not installed.");
        }

        if ($extension->status === 'disabled') {
            return true;
        }

        $this->repository->update($extension->id, [
            'status' => 'disabled',
            'disabled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->logAction($machineName, 'disabled', $extension->id);

        // Update atom_plugin table
        try {
            DB::table('atom_plugin')
                ->where('name', $machineName)
                ->update(['is_enabled' => 0]);
        } catch (\Exception $e) {
            // Silently continue
        }

        // Update Symfony setting_i18n plugins array
        $this->updateSymfonyPlugins($machineName, false);

        return true;
    }

    /**
     * Restore a pending deletion
     */
    public function restore(string $machineName): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        
        if (!$extension) {
            throw new \RuntimeException("Extension '{$machineName}' not found.");
        }

        if ($extension->status !== 'pending_removal') {
            throw new \RuntimeException("Extension '{$machineName}' is not pending removal.");
        }

        // Cancel pending deletions
        $this->repository->cancelPendingDeletion($machineName);

        // Restore to disabled state
        $this->repository->update($extension->id, [
            'status' => 'disabled',
        ]);

        $this->repository->logAction($machineName, 'backup_restored', $extension->id);

        return true;
    }

    /**
     * Check if extension is installed
     */
    public function isInstalled(string $machineName): bool
    {
        return $this->repository->exists($machineName);
    }

    /**
     * Check if extension is enabled
     */
    public function isEnabled(string $machineName): bool
    {
        $extension = $this->repository->findByMachineName($machineName);
        return $extension && $extension->status === 'enabled';
    }

    /**
     * Get extension setting
     */
    public function getSetting(string $key, ?int $extensionId = null, $default = null)
    {
        return $this->repository->getSetting($key, $extensionId, $default);
    }

    /**
     * Set extension setting
     */
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

    /**
     * Get audit log
     */
    public function getAuditLog(?string $machineName = null, int $limit = 50): Collection
    {
        return $this->repository->getAuditLog($machineName, $limit);
    }

    /**
     * Process pending deletions (called by cron)
     */
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

    // ==========================================
    // Protected Methods
    // ==========================================

    /**
     * Load extension manifest
     */
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

    /**
     * Find manifest for extension
     */
    protected function findManifest(string $machineName): ?array
    {
        $path = $this->pluginsPath . '/' . $machineName . '/extension.json';
        return $this->loadManifest($path);
    }

    /**
     * Check extension dependencies
     */
    protected function checkDependencies(array $manifest): void
    {
        $dependencies = $manifest['dependencies'] ?? [];

        foreach ($dependencies as $dep) {
            if (!$this->isEnabled($dep)) {
                throw new \RuntimeException("Required dependency '{$dep}' is not installed or enabled.");
            }
        }

        // Check PHP version
        if (!empty($manifest['requires']['php'])) {
            $required = ltrim($manifest['requires']['php'], '>=<');
            if (version_compare(PHP_VERSION, $required, '<')) {
                throw new \RuntimeException("PHP {$manifest['requires']['php']} required, you have " . PHP_VERSION);
            }
        }
    }

    /**
     * Run a Symfony task
     */
    protected function runSymfonyTask(string $task): void
    {
        $atomPath = dirname($this->pluginsPath);
        $command = "cd {$atomPath} && php symfony {$task} 2>&1";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("Task '{$task}' failed: " . implode("\n", $output));
        }
    }

    /**
     * Update Symfony setting_i18n plugins array
     */
    private function updateSymfonyPlugins(string $machineName, bool $add): void
    {
        try {
            $row = DB::table('setting_i18n')
                ->where('id', 1)
                ->where('culture', 'en')
                ->first();
            
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
                DB::table('setting_i18n')
                    ->where('id', 1)
                    ->where('culture', 'en')
                    ->update(['value' => serialize($plugins)]);
            } elseif (!$add && $key !== false) {
                unset($plugins[$key]);
                $plugins = array_values($plugins);
                DB::table('setting_i18n')
                    ->where('id', 1)
                    ->where('culture', 'en')
                    ->update(['value' => serialize($plugins)]);
            }
        } catch (\Exception $e) {
            // Silently continue if setting_i18n doesn't exist
        }
    }

}
