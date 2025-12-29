<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class BackupSettingsService
{
    private static ?array $cache = null;
    private array $dbConfigFromFile = [];
    private string $atomRoot;

    public function __construct()
    {
        // Get AtoM root directory - check if sfConfig exists first
        if (class_exists('sfConfig', false)) {
            $this->atomRoot = \sfConfig::get('sf_root_dir', '/usr/share/nginx/archive');
        } else {
            // Fallback: calculate from this file's location
            // This file is at: atom-framework/src/Services/BackupSettingsService.php
            // AtoM root is at: atom-framework/../ = /usr/share/nginx/archive
            $this->atomRoot = dirname(dirname(dirname(__DIR__)));
        }
        
        $this->loadDbConfigFromFile();
    }

    /**
     * Load database credentials from AtoM's config.php
     * 
     * config.php structure:
     * return array(
     *   'all' => array(
     *     'propel' => array(
     *       'param' => array(
     *         'dsn' => 'mysql:dbname=archive;port=3306',
     *         'username' => 'root',
     *         'password' => 'xxx',
     *       )
     *     )
     *   )
     * );
     */
    private function loadDbConfigFromFile(): void
    {
        $configFile = $this->atomRoot . '/config/config.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            
            if (is_array($config)) {
                // AtoM/Propel structure: all -> propel -> param
                $params = $config['all']['propel']['param'] ?? [];
                
                if (!empty($params)) {
                    // Parse DSN: mysql:dbname=archive;port=3306;host=localhost
                    if (isset($params['dsn'])) {
                        $dsn = $params['dsn'];
                        
                        if (preg_match('/dbname=([^;]+)/', $dsn, $m)) {
                            $this->dbConfigFromFile['db_name'] = $m[1];
                        }
                        if (preg_match('/host=([^;]+)/', $dsn, $m)) {
                            $this->dbConfigFromFile['db_host'] = $m[1];
                        } else {
                            // Default to localhost if not in DSN
                            $this->dbConfigFromFile['db_host'] = 'localhost';
                        }
                        if (preg_match('/port=([^;]+)/', $dsn, $m)) {
                            $this->dbConfigFromFile['db_port'] = (int)$m[1];
                        }
                    }
                    
                    $this->dbConfigFromFile['db_user'] = $params['username'] ?? 'root';
                    $this->dbConfigFromFile['db_password'] = $params['password'] ?? '';
                    $this->dbConfigFromFile['db_port'] = $this->dbConfigFromFile['db_port'] ?? 3306;
                    
                    return;
                }
                
                // Alternative structure: database -> ...
                if (isset($config['database'])) {
                    $db = $config['database'];
                    $this->dbConfigFromFile = [
                        'db_host' => $db['host'] ?? 'localhost',
                        'db_name' => $db['database'] ?? $db['name'] ?? 'archive',
                        'db_user' => $db['username'] ?? $db['user'] ?? 'root',
                        'db_password' => $db['password'] ?? '',
                        'db_port' => (int)($db['port'] ?? 3306),
                    ];
                    return;
                }
            }
        }

        // Fallback to defaults if config.php not found or invalid
        $this->dbConfigFromFile = [
            'db_host' => 'localhost',
            'db_name' => 'archive',
            'db_user' => 'root',
            'db_password' => '',
            'db_port' => 3306,
        ];
    }

    /**
     * Get database config from AtoM's config file
     */
    public function getDbConfigFromFile(): array
    {
        return $this->dbConfigFromFile;
    }

    /**
     * Get AtoM root directory
     */
    public function getAtomRoot(): string
    {
        return $this->atomRoot;
    }

    /**
     * Get config file path
     */
    public function getConfigFilePath(): string
    {
        return $this->atomRoot . '/config/config.php';
    }

    /**
     * Get a setting value - DB credentials come from config file
     */
    public function get(string $key, $default = null)
    {
        // Database credentials always from config file
        $dbKeys = ['db_host', 'db_name', 'db_user', 'db_password', 'db_port'];
        if (in_array($key, $dbKeys)) {
            return $this->dbConfigFromFile[$key] ?? $default;
        }

        $settings = $this->all();
        return $settings[$key] ?? $default;
    }

    public function set(string $key, $value, string $type = 'string', string $description = ''): bool
    {
        $dbKeys = ['db_host', 'db_name', 'db_user', 'db_password', 'db_port'];
        if (in_array($key, $dbKeys)) {
            return false;
        }

        try {
            DB::table('backup_setting')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => is_array($value) ? json_encode($value) : (string)$value,
                    'setting_type' => $type,
                    'description' => $description ?: null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
            self::$cache = null;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function all(): array
    {
        if (self::$cache !== null) {
            return array_merge(self::$cache, $this->dbConfigFromFile);
        }

        try {
            $rows = DB::table('backup_setting')->get();
            $settings = [];
            
            foreach ($rows as $row) {
                if (in_array($row->setting_key, ['db_host', 'db_name', 'db_user', 'db_password', 'db_port'])) {
                    continue;
                }

                $value = $row->setting_value;
                
                switch ($row->setting_type) {
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'boolean':
                        $value = (bool)$value;
                        break;
                    case 'json':
                        $value = json_decode($value, true) ?? [];
                        break;
                }
                
                $settings[$row->setting_key] = $value;
            }
            
            self::$cache = $settings;
            return array_merge($settings, $this->dbConfigFromFile);
        } catch (\Exception $e) {
            return array_merge($this->getDefaults(), $this->dbConfigFromFile);
        }
    }

    public function getAllWithMeta(): array
    {
        try {
            return DB::table('backup_setting')
                ->whereNotIn('setting_key', ['db_host', 'db_name', 'db_user', 'db_password', 'db_port'])
                ->orderBy('setting_key')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function saveMultiple(array $settings): bool
    {
        $dbKeys = ['db_host', 'db_name', 'db_user', 'db_password', 'db_port'];
        $settings = array_filter($settings, fn($key) => !in_array($key, $dbKeys), ARRAY_FILTER_USE_KEY);

        try {
            foreach ($settings as $key => $value) {
                $row = DB::table('backup_setting')->where('setting_key', $key)->first();
                $type = $row->setting_type ?? 'string';
                
                if ($type === 'boolean') {
                    $value = $value ? '1' : '0';
                } elseif ($type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                }
                
                DB::table('backup_setting')
                    ->updateOrInsert(
                        ['setting_key' => $key],
                        [
                            'setting_value' => (string)$value,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
            }
            self::$cache = null;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clearCache(): void
    {
        self::$cache = null;
    }


    /**
     * Get AHG plugins from database for backup
     */
    private function getAhgPlugins(): array
    {
        try {
            $plugins = DB::table('atom_plugin')
                ->where('category', 'ahg')
                ->orWhere('name', 'sfMuseumPlugin')
                ->orWhere('name', 'IiifViewerFramework')
                ->orWhere('name', 'arPluginManagerPlugin')
                ->orWhere('name', 'arAHGThemeB5Plugin')
                ->pluck('name')
                ->toArray();
            
            return !empty($plugins) ? $plugins : $this->getDefaultAhgPlugins();
        } catch (\Exception $e) {
            return $this->getDefaultAhgPlugins();
        }
    }

    /**
     * Fallback list if database unavailable
     */
    private function getDefaultAhgPlugins(): array
    {
        return [
            'ar3DModelPlugin',
            'ahgAccessRequestPlugin',
            'arAHGThemeB5Plugin',
            'ahgAuditTrailPlugin',
            'arConditionPlugin',
            'ahgDAMPlugin',
            'ahgDisplayPlugin',
            'arDonorAgreementPlugin',
            'arDonorPlugin',
            'arExtendedRightsPlugin',
            'arGalleryPlugin',
            'arGrapPlugin',
            'arIiifCollectionPlugin',
            'ahgLibraryPlugin',
            'arPluginManagerPlugin',
            'ahgResearchPlugin',
            'arRicExplorerPlugin',
            'ahgSecurityClearancePlugin',
            'arSpectrumPlugin',
            'IiifViewerFramework',
            'sfMuseumPlugin',
        ];
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
            'custom_plugins' => $this->getAhgPlugins(),
        ];
    }
}
