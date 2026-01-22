<?php

namespace AtomFramework\Helpers;

use Illuminate\Database\Capsule\Manager as DB;

class DisplayStandardHelper
{
    /**
     * Map of display standard codes to their plugin names
     */
    private static array $codeToPlugin = [
        'isad' => 'sfIsadPlugin',
        'dc' => 'sfDcPlugin',
        'mods' => 'sfModsPlugin',
        'rad' => 'sfRadPlugin',
        'dacs' => 'arDacsPlugin',
        'museum' => 'ahgMuseumPlugin',
        'library' => 'ahgLibraryPlugin',
        'dam' => 'ahgDAMPlugin',
        'gallery' => 'ahgGalleryPlugin',
    ];

    /**
     * Core plugins that are always available (part of base AtoM)
     */
    private static array $corePlugins = [
        'sfIsadPlugin',
        'sfDcPlugin',
        'sfModsPlugin',
        'sfRadPlugin',
        'arDacsPlugin',
    ];

    /**
     * Get available display standards based on enabled plugins
     *
     * @param string $culture Language culture code
     * @return array [id => name] of available display standards
     */
    public static function getAvailable(string $culture = 'en'): array
    {
        // Get enabled AHG plugins
        $enabledPlugins = DB::table('atom_plugin')
            ->where('is_enabled', 1)
            ->pluck('name')
            ->toArray();

        // Add core plugins (always available)
        $availablePlugins = array_merge(self::$corePlugins, $enabledPlugins);

        // Get codes for available plugins
        $availableCodes = [];
        foreach (self::$codeToPlugin as $code => $plugin) {
            if (in_array($plugin, $availablePlugins)) {
                $availableCodes[] = $code;
            }
        }

        // Get terms for available codes
        $terms = DB::table('term as t')
            ->join('term_i18n as ti', 't.id', '=', 'ti.id')
            ->where('t.taxonomy_id', 70) // Display standard taxonomy
            ->where('ti.culture', $culture)
            ->whereIn('t.code', $availableCodes)
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 't.code')
            ->get();

        $result = [];
        foreach ($terms as $term) {
            $result[$term->id] = $term->name;
        }

        return $result;
    }

    /**
     * Get term ID by code
     *
     * @param string $code Display standard code (e.g., 'museum')
     * @return int|null Term ID or null if not found
     */
    public static function getTermIdByCode(string $code): ?int
    {
        $id = DB::table('term')
            ->where('code', $code)
            ->where('taxonomy_id', 70)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Check if a display standard plugin is enabled
     *
     * @param string $code Display standard code
     * @return bool
     */
    public static function isEnabled(string $code): bool
    {
        $plugin = self::$codeToPlugin[$code] ?? null;

        if (!$plugin) {
            return false;
        }

        // Core plugins are always enabled
        if (in_array($plugin, self::$corePlugins)) {
            return true;
        }

        return (bool) DB::table('atom_plugin')
            ->where('name', $plugin)
            ->where('is_enabled', 1)
            ->exists();
    }
}
