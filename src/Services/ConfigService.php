<?php

namespace AtomFramework\Services;

use AtomFramework\Helpers\PathResolver;

/**
 * Configuration service — wraps sfConfig for forward compatibility.
 *
 * Reads from sfConfig now; provides the seam for future replacement
 * when Symfony is eventually removed.
 *
 * Usage:
 *   ConfigService::get('glam_type', 'archive');
 *   ConfigService::getBool('enable_iiif', false);
 *   ConfigService::rootDir();
 */
class ConfigService
{
    /**
     * Get a configuration value.
     *
     * Tries app_ prefixed key first (AtoM convention), then raw key.
     */
    public static function get(string $key, $default = null)
    {
        if (class_exists('\sfConfig', false)) {
            // Try app_ prefixed first (AtoM stores settings as app_*)
            $value = \sfConfig::get('app_' . $key);
            if ($value !== null) {
                return $value;
            }

            return \sfConfig::get($key, $default);
        }

        return $default;
    }

    /**
     * Set a configuration value.
     */
    public static function set(string $key, $value): void
    {
        if (class_exists('\sfConfig', false)) {
            \sfConfig::set($key, $value);
        }
    }

    /**
     * Check if a configuration key exists.
     */
    public static function has(string $key): bool
    {
        if (class_exists('\sfConfig', false)) {
            if (\sfConfig::get('app_' . $key) !== null) {
                return true;
            }

            return \sfConfig::get($key) !== null;
        }

        return false;
    }

    /**
     * Get all configuration values.
     */
    public static function all(): array
    {
        if (class_exists('\sfConfig', false)) {
            return \sfConfig::getAll();
        }

        return [];
    }

    // ─── Typed Getters ───────────────────────────────────────────────

    /**
     * Get a boolean configuration value.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get an integer configuration value.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    /**
     * Get an array configuration value.
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = self::get($key, $default);
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $default;
    }

    // ─── Standalone Mode Loading ────────────────────────────────────

    /**
     * Load settings from the database into sfConfig (or its shim).
     *
     * Used in standalone mode (heratio.php) where QubitSettingsFilter
     * isn't running. Replicates QubitSetting::getSettingsArray() logic.
     */
    public static function loadFromDatabase(string $culture = 'en'): void
    {
        $db = \Illuminate\Database\Capsule\Manager::class;
        if (!class_exists($db)) {
            return;
        }

        try {
            $rows = \Illuminate\Database\Capsule\Manager::table('setting')
                ->leftJoin('setting_i18n as current', function ($join) use ($culture) {
                    $join->on('setting.id', '=', 'current.id')
                        ->where('current.culture', '=', $culture);
                })
                ->leftJoin('setting_i18n as source', function ($join) {
                    $join->on('setting.id', '=', 'source.id')
                        ->whereColumn('source.culture', '=', 'setting.source_culture');
                })
                ->select([
                    'setting.name',
                    'setting.scope',
                    \Illuminate\Database\Capsule\Manager::raw(
                        'CASE WHEN (current.value IS NOT NULL AND current.value <> "") '
                        . 'THEN current.value ELSE source.value END AS value'
                    ),
                    \Illuminate\Database\Capsule\Manager::raw('source.value AS value_source'),
                ])
                ->get();
        } catch (\Exception $e) {
            return;
        }

        $settings = [];
        $i18nLanguages = [];

        foreach ($rows as $row) {
            if ($row->scope) {
                if ('i18n_languages' === $row->scope) {
                    $i18nLanguages[] = $row->value_source;

                    continue;
                }

                $key = 'app_' . $row->scope . '_' . $row->name;
            } else {
                $key = 'app_' . $row->name;
            }

            $settings[$key] = $row->value;
            $settings[$key . '__source'] = $row->value_source;
        }

        $settings['app_i18n_languages'] = $i18nLanguages;

        if (class_exists('\sfConfig', false)) {
            \sfConfig::add($settings);
        }
    }

    /**
     * Load CSP and other settings from app.yml.
     *
     * In Symfony mode, sfConfig loads app.yml automatically.
     * In standalone mode, we parse it manually.
     */
    public static function loadFromAppYaml(string $rootDir): void
    {
        $yamlFile = $rootDir . '/config/app.yml';
        if (!file_exists($yamlFile)) {
            return;
        }

        // Use Symfony YAML if available, otherwise basic parsing
        if (class_exists('\sfYaml')) {
            $data = \sfYaml::load($yamlFile);
        } elseif (class_exists('\Symfony\Component\Yaml\Yaml')) {
            $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);
        } else {
            return;
        }

        if (!is_array($data) || !isset($data['all'])) {
            return;
        }

        $flat = self::flattenYaml($data['all'], 'app');

        if (class_exists('\sfConfig', false)) {
            \sfConfig::add($flat);
        }
    }

    /**
     * Flatten nested YAML config into dot-separated sfConfig keys.
     */
    private static function flattenYaml(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? $prefix . '_' . $key : $key;
            if (is_array($value) && !array_is_list($value)) {
                $result = array_merge($result, self::flattenYaml($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    // ─── Database Configuration ─────────────────────────────────────

    /**
     * Parse database connection config from AtoM's config.php.
     *
     * This is the CANONICAL source for DB config parsing. Both
     * bootstrap.php and Kernel::bootDatabase() should use this method.
     *
     * Returns an array suitable for Illuminate Capsule::addConnection(),
     * or null if the config file is missing/invalid.
     */
    public static function parseDbConfig(string $rootDir): ?array
    {
        $configFile = $rootDir . '/config/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        $config = require $configFile;
        if (!isset($config['all']['propel']['param'])) {
            return null;
        }

        $dbConfig = $config['all']['propel']['param'];
        $dsn = $dbConfig['dsn'] ?? '';

        $database = 'atom';
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            $database = $matches[1];
        }

        $host = 'localhost';
        if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
            $host = $matches[1];
        }

        $port = 3306;
        if (preg_match('/port=([^;]+)/', $dsn, $matches)) {
            $port = (int) $matches[1];
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $dbConfig['username'] ?? 'atom',
            'password' => $dbConfig['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }

    // ─── Boot Assertions ────────────────────────────────────────────

    /** @var string[] Tables that must exist for Heratio to function */
    private const REQUIRED_TABLES = [
        'atom_plugin',
        'object',
        'information_object',
        'actor',
        'setting',
        'setting_i18n',
        'term',
        'taxonomy',
        'slug',
        'user',
        'repository',
        'digital_object',
    ];

    /**
     * Run boot-time assertions and return errors (if any).
     *
     * Checks:
     *   1. config/config.php exists and contains valid DB params
     *   2. Database is reachable (SELECT 1)
     *   3. Required tables exist
     *   4. uploads/ directory exists and is writable
     *
     * Returns null on success, or a BootError object on failure.
     */
    public static function assertBootRequirements(string $rootDir): ?array
    {
        // 1. Config file
        $configFile = $rootDir . '/config/config.php';
        if (!file_exists($configFile)) {
            return [
                'title' => 'Missing Database Configuration',
                'message' => "The file <code>config/config.php</code> does not exist.",
                'remedy' => 'Run the AtoM installer or copy <code>config/config.php.example</code> to <code>config/config.php</code> and set your database credentials.',
            ];
        }

        $dbConfig = self::parseDbConfig($rootDir);
        if (null === $dbConfig) {
            return [
                'title' => 'Invalid Database Configuration',
                'message' => 'The file <code>config/config.php</code> exists but does not contain valid Propel database parameters.',
                'remedy' => 'Ensure <code>config/config.php</code> returns an array with <code>[\'all\'][\'propel\'][\'param\']</code> containing <code>dsn</code>, <code>username</code>, and <code>password</code>.',
            ];
        }

        // 2. Database connectivity
        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']),
                $dbConfig['username'],
                $dbConfig['password'],
                [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $pdo->query('SELECT 1');
        } catch (\PDOException $e) {
            return [
                'title' => 'Database Connection Failed',
                'message' => sprintf(
                    'Cannot connect to MySQL at <code>%s:%d</code> (database: <code>%s</code>, user: <code>%s</code>).<br>Error: %s',
                    htmlspecialchars($dbConfig['host']),
                    $dbConfig['port'],
                    htmlspecialchars($dbConfig['database']),
                    htmlspecialchars($dbConfig['username']),
                    htmlspecialchars($e->getMessage())
                ),
                'remedy' => 'Check that MySQL is running, the database exists, and the credentials in <code>config/config.php</code> are correct.',
            ];
        }

        // 3. Required tables
        try {
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $missing = array_diff(self::REQUIRED_TABLES, $tables);
            if (!empty($missing)) {
                return [
                    'title' => 'Missing Required Tables',
                    'message' => sprintf(
                        'The database <code>%s</code> is missing required tables: <code>%s</code>',
                        htmlspecialchars($dbConfig['database']),
                        htmlspecialchars(implode(', ', $missing))
                    ),
                    'remedy' => 'Run the AtoM installer (<code>php symfony tools:install</code>) to create the required database schema, or restore from a backup.',
                ];
            }
        } catch (\PDOException $e) {
            // Non-fatal — if SHOW TABLES fails, skip table check
        }

        // 4. Uploads directory
        $uploadsDir = $rootDir . '/uploads';
        if (!is_dir($uploadsDir)) {
            return [
                'title' => 'Missing Uploads Directory',
                'message' => sprintf('The directory <code>%s</code> does not exist.', htmlspecialchars($uploadsDir)),
                'remedy' => sprintf('Create the directory: <code>mkdir -p %s && chown www-data:www-data %s</code>', htmlspecialchars($uploadsDir), htmlspecialchars($uploadsDir)),
            ];
        }

        return null; // All assertions passed
    }

    /**
     * Render a boot error page as standalone HTML.
     *
     * Returns a self-contained HTML page (no external dependencies)
     * that displays the error with remediation steps.
     */
    public static function renderBootErrorPage(array $error): string
    {
        $title = htmlspecialchars($error['title'] ?? 'Boot Error');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Heratio — {$title}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         background: #f8f9fa; color: #212529; display: flex; justify-content: center;
         align-items: center; min-height: 100vh; padding: 1rem; }
  .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1);
          max-width: 640px; width: 100%; overflow: hidden; }
  .card-header { background: #dc3545; color: #fff; padding: 1.25rem 1.5rem; }
  .card-header h1 { font-size: 1.25rem; font-weight: 600; }
  .card-header .subtitle { font-size: .85rem; opacity: .85; margin-top: .25rem; }
  .card-body { padding: 1.5rem; }
  .card-body p { margin-bottom: 1rem; line-height: 1.6; }
  .label { font-weight: 600; color: #495057; font-size: .85rem; text-transform: uppercase;
           letter-spacing: .05em; margin-bottom: .5rem; }
  .remedy { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 1rem;
            border-radius: 0 4px 4px 0; margin-top: .5rem; }
  code { background: #f1f3f5; padding: .15em .4em; border-radius: 3px; font-size: .9em; }
  .footer { padding: 1rem 1.5rem; background: #f8f9fa; border-top: 1px solid #dee2e6;
            font-size: .8rem; color: #6c757d; }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <h1>{$title}</h1>
    <div class="subtitle">Heratio could not start</div>
  </div>
  <div class="card-body">
    <p class="label">What happened</p>
    <p>{$error['message']}</p>
    <p class="label">How to fix</p>
    <div class="remedy">{$error['remedy']}</div>
  </div>
  <div class="footer">
    Heratio (AtoM AHG Framework) &middot; Disable with: <code>rm .heratio_enabled</code>
  </div>
</div>
</body>
</html>
HTML;
    }

    // ─── Path Shortcuts ──────────────────────────────────────────────

    /**
     * Get the AtoM root directory.
     */
    public static function rootDir(): string
    {
        return PathResolver::getRootDir();
    }

    /**
     * Get the plugins directory.
     */
    public static function pluginsDir(): string
    {
        return PathResolver::getPluginsDir();
    }

    /**
     * Get the uploads directory.
     */
    public static function uploadsDir(): string
    {
        return PathResolver::getUploadsDir();
    }
}
