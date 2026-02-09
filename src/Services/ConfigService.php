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
