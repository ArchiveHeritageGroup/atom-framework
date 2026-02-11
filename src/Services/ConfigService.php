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
