<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class BackupSettingsService
{
    private static ?array $cache = null;
    private array $atomConfig = [];
    private static ?string $atomRoot = null;

    public function __construct()
    {
        $this->loadAtomConfig();
    }

    /**
     * Detect AtoM root path dynamically
     */
    public static function getAtomRoot(): string
    {
        if (self::$atomRoot !== null) {
            return self::$atomRoot;
        }

        // Method 1: Symfony sfConfig (if loaded)
        if (class_exists('sfConfig')) {
            $root = \sfConfig::get('sf_root_dir');
            if ($root && is_dir($root)) {
                self::$atomRoot = $root;
                return self::$atomRoot;
            }
        }

        // Method 2: Environment variable
        $envRoot = getenv('ATOM_ROOT');
        if ($envRoot && is_dir($envRoot)) {
            self::$atomRoot = $envRoot;
            return self::$atomRoot;
        }

        // Method 3: Relative to framework (atom-framework is inside AtoM root)
        $frameworkDir = dirname(__DIR__, 2);
        $parentDir = dirname($frameworkDir);
        if (file_exists($parentDir . '/config/config.php')) {
            self::$atomRoot = $parentDir;
            return self::$atomRoot;
        }

        // Method 4: Common constant (set by AtoM bootstrap)
        if (defined('SF_ROOT_DIR')) {
            self::$atomRoot = SF_ROOT_DIR;
            return self::$atomRoot;
        }

        // Method 5: Working directory fallback
        $cwd = getcwd();
        if (file_exists($cwd . '/config/config.php')) {
            self::$atomRoot = $cwd;
            return self::$atomRoot;
        }

        self::$atomRoot = '';
        return self::$atomRoot;
    }

    /**
     * Load AtoM's database config from config.php
     */
    private function loadAtomConfig(): void
    {
        $atomRoot = self::getAtomRoot();
        if (empty($atomRoot)) {
            return;
        }

        $configPath = $atomRoot . '/config/config.php';
        if (!file_exists($configPath)) {
            return;
        }

        $config = require $configPath;
        if (!isset($config['all']['propel']['param'])) {
            return;
        }

        $propel = $config['all']['propel']['param'];
        
        $dsn = $propel['dsn'] ?? '';
        $dbname = 'archive';
        $host = 'localhost';
        $port = 3306;
        
        if (preg_match('/dbname=([^;]+)/', $dsn, $m)) {
            $dbname = $m[1];
        }
        if (preg_match('/host=([^;]+)/', $dsn, $m)) {
            $host = $m[1];
        }
        if (preg_match('/port=([^;]+)/', $dsn, $m)) {
            $port = (int)$m[1];
        }
        
        $this->atomConfig = [
            'db_host' => $host,
            'db_name' => $dbname,
            'db_user' => $propel['username'] ?? 'root',
            'db_password' => $propel['password'] ?? '',
            'db_port' => $port,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->getAllSettings();
        
        if (isset($settings[$key]) && $settings[$key] !== null && $settings[$key] !== '') {
            return $settings[$key];
        }
        
        if (isset($this->atomConfig[$key])) {
            return $this->atomConfig[$key];
        }
        
        return $default;
    }

    public function getAllSettings(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = $this->getDefaults();

        try {
            $rows = DB::table('backup_setting')->get();
            
            foreach ($rows as $row) {
                $value = $row->setting_value;
                $type = $row->setting_type ?? 'string';
                
                if ($type === 'boolean') {
                    $value = in_array(strtolower($value), ['1', 'true', 'yes']);
                } elseif ($type === 'integer') {
                    $value = (int)$value;
                } elseif ($type === 'json') {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : $value;
                }
                
                $defaults[$row->setting_key] = $value;
            }
        } catch (\Exception $e) {
            // Table doesn't exist, use defaults + atom config
        }

        foreach ($this->atomConfig as $key => $value) {
            if (!isset($defaults[$key]) || $defaults[$key] === null || $defaults[$key] === '') {
                $defaults[$key] = $value;
            }
        }

        self::$cache = $defaults;
        return self::$cache;
    }

    public function set(string $key, mixed $value): bool
    {
        try {
            $exists = DB::table('backup_setting')
                ->where('setting_key', $key)
                ->exists();

            $type = match(true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'json',
                default => 'string'
            };

            if ($type === 'boolean') {
                $value = $value ? '1' : '0';
            } elseif ($type === 'json' && is_array($value)) {
                $value = json_encode($value);
            }

            if ($exists) {
                DB::table('backup_setting')
                    ->where('setting_key', $key)
                    ->update([
                        'setting_value' => (string)$value,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                DB::table('backup_setting')->insert([
                    'setting_key' => $key,
                    'setting_value' => (string)$value,
                    'setting_type' => $type,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            self::$cache = null;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function saveAll(array $settings): bool
    {
        try {
            foreach ($settings as $key => $value) {
                $this->set($key, $value);
            }
            self::$cache = null;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clearCache(): void
    {
        self::$atomRoot = null;
        self::$cache = null;
    }

    private function getDefaults(): array
    {
        return [
            'backup_path' => '/var/backups/atom',
            'log_path' => '/var/log/atom/backup.log',
            'max_backups' => 30,
            'retention_days' => 90,
            'include_database' => true,
            'include_uploads' => true,
            'include_plugins' => true,
            'include_framework' => true,
            'compression_level' => 6,
            'notify_email' => '',
            'notify_on_success' => false,
            'notify_on_failure' => true,
            'custom_plugins' => ['ahgThemeB5Plugin', 'ahgSecurityClearancePlugin'],
        ];
    }

    /**
     * Get DB config from AtoM config.php (for display purposes)
     */
    public function getDbConfigFromFile(): array
    {
        return $this->atomConfig;
    }

    /**
     * Alias for getAllSettings() - for template compatibility
     */
    public function all(): array
    {
        return $this->getAllSettings();
    }
}