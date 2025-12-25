<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * AHG Settings Service
 * 
 * Centralized service for reading AHG settings from database.
 * Use this class to check settings before executing features.
 */
class AhgSettingsService
{
    private static ?array $cache = null;
    
    /**
     * Get a single setting value
     */
    public static function get(string $key, $default = null)
    {
        self::loadCache();
        return self::$cache[$key] ?? $default;
    }
    
    /**
     * Get a boolean setting (handles string 'true'/'false')
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return $value === 'true' || $value === '1' || $value === true;
    }
    
    /**
     * Get an integer setting
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }
    
    /**
     * Get all settings for a group
     */
    public static function getGroup(string $group): array
    {
        self::loadCache();
        $result = [];
        foreach (self::$cache as $key => $value) {
            // Settings are stored with group info in separate column
            // This gets all settings - filter by prefix convention
            $result[$key] = $value;
        }
        return $result;
    }
    
    /**
     * Check if a feature is enabled
     */
    public static function isEnabled(string $feature): bool
    {
        $key = match($feature) {
            'theme' => 'ahg_theme_enabled',
            'metadata' => 'meta_extract_on_upload',
            'spectrum' => 'spectrum_enabled',
            'iiif' => 'iiif_enabled',
            'data_protection', 'privacy' => 'dp_enabled',
            'faces', 'face_detection' => 'face_detect_enabled',
            'jobs' => 'jobs_enabled',
            default => $feature . '_enabled'
        };
        return self::getBool($key, false);
    }
    
    /**
     * Load all settings into cache
     */
    private static function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }
        
        self::$cache = [];
        
        try {
            $rows = DB::table('ahg_settings')->get();
            foreach ($rows as $row) {
                self::$cache[$row->setting_key] = $row->setting_value;
            }
        } catch (\Exception $e) {
            error_log('AhgSettingsService: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear the cache (call after saving settings)
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
    
    /**
     * Save a setting
     */
    public static function set(string $key, $value, string $group = 'general'): bool
    {
        try {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'setting_group' => $group,
                    'updated_at' => now()
                ]
            );
            self::clearCache();
            return true;
        } catch (\Exception $e) {
            error_log('AhgSettingsService::set error: ' . $e->getMessage());
            return false;
        }
    }
}
