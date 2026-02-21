<?php
/**
 * Plugin catalog reader and toggle manager for the web wizard
 */
class PluginManager
{
    private string $pluginsPath;
    private ?PDO $pdo = null;

    public function __construct(string $atomPath)
    {
        $this->pluginsPath = $atomPath . '/atom-ahg-plugins';
        $this->connectDB();
    }

    /**
     * Get all plugins grouped by category
     */
    public function getCatalog(): array
    {
        $plugins = [];

        if (!is_dir($this->pluginsPath)) {
            return $plugins;
        }

        foreach (glob("{$this->pluginsPath}/*/extension.json") as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;

            $name = $data['name'] ?? basename(dirname($file));
            $category = $data['category'] ?? 'general';

            // Check if enabled in DB
            $enabled = false;
            $locked = false;
            if ($this->pdo) {
                $stmt = $this->pdo->prepare('SELECT is_enabled, is_locked FROM atom_plugin WHERE name = ?');
                $stmt->execute([$name]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $enabled = (bool)$row['is_enabled'];
                    $locked = (bool)$row['is_locked'];
                }
            }

            $plugins[$category][] = [
                'name' => $name,
                'description' => $data['description'] ?? '',
                'version' => $data['version'] ?? '',
                'category' => $category,
                'enabled' => $enabled,
                'locked' => $locked,
                'dependencies' => $data['dependencies'] ?? [],
            ];
        }

        ksort($plugins);
        return $plugins;
    }

    /**
     * Enable a plugin
     */
    public function enable(string $name): bool
    {
        if (!$this->pdo) return false;

        $stmt = $this->pdo->prepare('UPDATE atom_plugin SET is_enabled = 1 WHERE name = ? AND is_locked = 0');
        $stmt->execute([$name]);

        // Create plugin symlink
        $pluginDir = "{$this->pluginsPath}/{$name}";
        $atomPath = dirname($this->pluginsPath);
        $symlinkPath = "{$atomPath}/plugins/{$name}";

        if (is_dir($pluginDir) && !file_exists($symlinkPath)) {
            symlink($pluginDir, $symlinkPath);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Disable a plugin
     */
    public function disable(string $name): bool
    {
        if (!$this->pdo) return false;

        $stmt = $this->pdo->prepare('UPDATE atom_plugin SET is_enabled = 0 WHERE name = ? AND is_locked = 0 AND is_core = 0');
        $stmt->execute([$name]);

        // Remove plugin symlink
        $atomPath = dirname($this->pluginsPath);
        $symlinkPath = "{$atomPath}/plugins/{$name}";

        if (is_link($symlinkPath)) {
            unlink($symlinkPath);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Batch enable/disable
     */
    public function setPlugins(array $enabled, array $disabled): array
    {
        $results = ['enabled' => [], 'disabled' => [], 'errors' => []];

        foreach ($enabled as $name) {
            if ($this->enable($name)) {
                $results['enabled'][] = $name;
            } else {
                $results['errors'][] = "Could not enable: {$name}";
            }
        }

        foreach ($disabled as $name) {
            if ($this->disable($name)) {
                $results['disabled'][] = $name;
            }
        }

        return $results;
    }

    private function connectDB(): void
    {
        $conf = $this->loadConfig();
        $host = $conf['DB_HOST'] ?? 'localhost';
        $name = $conf['DB_NAME'] ?? 'atom';
        $user = $conf['DB_USER'] ?? 'atom';
        $pass = $conf['DB_PASS'] ?? '';

        try {
            $this->pdo = new PDO("mysql:host={$host};dbname={$name}", $user, $pass, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Exception $e) {
            $this->pdo = null;
        }
    }

    private function loadConfig(): array
    {
        $conf = [];
        $file = '/etc/atom-heratio/atom-heratio.conf';
        if (file_exists($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with($line, '#')) continue;
                if (strpos($line, '=') !== false) {
                    [$key, $val] = explode('=', $line, 2);
                    $conf[trim($key)] = trim(trim($val), '"\'');
                }
            }
        }
        return $conf;
    }
}
