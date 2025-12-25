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
