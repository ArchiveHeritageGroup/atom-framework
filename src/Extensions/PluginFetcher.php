<?php
namespace AtomFramework\Extensions;

class PluginFetcher
{
    protected string $repoUrl = 'https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git';
    protected string $pluginsPath;
    protected ?string $tempPath = null;

    public function __construct(string $pluginsPath = '/usr/share/nginx/atom/plugins')
    {
        $this->pluginsPath = $pluginsPath;
    }

    /**
     * Fetch a specific plugin from GitHub
     */
    public function fetch(string $machineName): bool
    {
        $targetPath = "{$this->pluginsPath}/{$machineName}";
        if (is_dir($targetPath)) {
            return true; // Already exists
        }
        $this->cloneRepo();
        $sourcePath = "{$this->tempPath}/{$machineName}";
        if (!is_dir($sourcePath)) {
            $this->cleanup();
            return false;
        }
        exec("cp -r {$sourcePath} {$targetPath} 2>&1", $output, $code);
        $this->cleanup();
        return $code === 0;
    }

    /**
     * Get list of available remote plugins
     */
    public function getRemotePlugins(): array
    {
        $this->cloneRepo();
        $plugins = [];
        $dirs = glob("{$this->tempPath}/*Plugin");
        foreach ($dirs as $dir) {
            $extensionJson = "{$dir}/extension.json";
            if (file_exists($extensionJson)) {
                $manifest = json_decode(file_get_contents($extensionJson), true);
                if ($manifest) {
                    $manifest['machine_name'] = $manifest['machine_name'] ?? basename($dir);
                    $manifest['remote'] = true;
                    $manifest['local'] = is_dir("{$this->pluginsPath}/" . basename($dir));
                    $plugins[] = $manifest;
                }
            }
        }
        $this->cleanup();
        return $plugins;
    }

    /**
     * Get remote manifest for a specific plugin
     */
    public function getRemoteManifest(string $machineName): ?array
    {
        $this->cloneRepo();
        
        $extensionJson = "{$this->tempPath}/{$machineName}/extension.json";
        
        if (!file_exists($extensionJson)) {
            $this->cleanup();
            return null;
        }
        
        $manifest = json_decode(file_get_contents($extensionJson), true);
        
        if ($manifest) {
            $manifest['machine_name'] = $manifest['machine_name'] ?? $machineName;
            $manifest['remote'] = true;
        }
        
        $this->cleanup();
        return $manifest;
    }

    /**
     * Check if update is available for a plugin
     */
    public function hasUpdate(string $machineName, string $currentVersion): ?string
    {
        $manifest = $this->getRemoteManifest($machineName);
        
        if (!$manifest) {
            return null;
        }
        
        $remoteVersion = $manifest['version'] ?? '0.0.0';
        
        if (version_compare($remoteVersion, $currentVersion, '>')) {
            return $remoteVersion;
        }
        
        return null;
    }

    /**
     * Get all available updates for installed plugins
     */
    public function getAvailableUpdates(array $installedPlugins): array
    {
        $this->cloneRepo();
        $updates = [];
        
        foreach ($installedPlugins as $name => $version) {
            $extensionJson = "{$this->tempPath}/{$name}/extension.json";
            
            if (file_exists($extensionJson)) {
                $manifest = json_decode(file_get_contents($extensionJson), true);
                
                if ($manifest) {
                    $remoteVersion = $manifest['version'] ?? '0.0.0';
                    
                    if (version_compare($remoteVersion, $version, '>')) {
                        $updates[$name] = [
                            'current' => $version,
                            'available' => $remoteVersion,
                            'manifest' => $manifest,
                        ];
                    }
                }
            }
        }
        
        $this->cleanup();
        return $updates;
    }

    protected function cloneRepo(): void
    {
        if ($this->tempPath && is_dir($this->tempPath)) {
            return;
        }
        $this->tempPath = '/tmp/ahg-plugins-' . getmypid();
        exec("git clone --depth 1 {$this->repoUrl} {$this->tempPath} 2>/dev/null");
    }

    protected function cleanup(): void
    {
        if ($this->tempPath && is_dir($this->tempPath)) {
            exec("rm -rf {$this->tempPath}");
            $this->tempPath = null;
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
