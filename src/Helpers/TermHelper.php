<?php

namespace AtomFramework\Helpers;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Lightweight term lookup helper — replaces QubitTerm::getById() for read-only access.
 *
 * Uses Laravel Query Builder with per-request caching.
 * Does NOT replace Propel for write operations (save, setName, etc.).
 */
class TermHelper
{
    /** @var array<string, string> Per-request name cache keyed by "id:culture" */
    private static array $nameCache = [];

    /** @var array<int, bool> Per-request existence cache */
    private static array $existsCache = [];

    /**
     * Get the localized name of a term by ID.
     *
     * Replaces: QubitTerm::getById($id)->getName(['culture' => $culture])
     * Replaces: QubitTerm::getById($id)->__toString()  (in string concatenation)
     * Replaces: QubitTerm::getById($id)->name
     *
     * @param int|string|null $id      Term ID
     * @param string|null     $culture ISO culture code (e.g. 'en', 'af'). Null = current user culture.
     *
     * @return string Term name, or empty string if not found
     */
    public static function name($id, ?string $culture = null): string
    {
        if (empty($id) || !is_numeric($id)) {
            return '';
        }

        $id = (int) $id;
        $culture = $culture ?? self::getCurrentCulture();
        $cacheKey = $id . ':' . $culture;

        if (isset(self::$nameCache[$cacheKey])) {
            return self::$nameCache[$cacheKey];
        }

        try {
            // Try requested culture first
            $name = DB::table('term_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->value('name');

            // Fall back to source culture if not found
            if (null === $name && 'en' !== $culture) {
                $name = DB::table('term_i18n')
                    ->where('id', $id)
                    ->where('culture', 'en')
                    ->value('name');
            }

            // Last resort: any available culture
            if (null === $name) {
                $name = DB::table('term_i18n')
                    ->where('id', $id)
                    ->value('name');
            }

            $result = $name ?? '';
        } catch (\Exception $e) {
            $result = '';
        }

        self::$nameCache[$cacheKey] = $result;
        self::$existsCache[$id] = ('' !== $result);

        return $result;
    }

    /**
     * Check if a term exists by ID.
     *
     * Replaces: null === QubitTerm::getById($id)  →  !term_exists($id)
     *
     * @param int|string|null $id Term ID
     *
     * @return bool
     */
    public static function exists($id): bool
    {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }

        $id = (int) $id;

        if (isset(self::$existsCache[$id])) {
            return self::$existsCache[$id];
        }

        try {
            $result = DB::table('term')->where('id', $id)->exists();
        } catch (\Exception $e) {
            $result = false;
        }

        self::$existsCache[$id] = $result;

        return $result;
    }

    /**
     * Clear the per-request cache. Useful in long-running CLI tasks.
     */
    public static function clearCache(): void
    {
        self::$nameCache = [];
        self::$existsCache = [];
    }

    /**
     * Get current user culture, with fallback for CLI context.
     */
    private static function getCurrentCulture(): string
    {
        try {
            if (class_exists('sfContext', false) && \sfContext::hasInstance()) {
                return \sfContext::getInstance()->getUser()->getCulture();
            }
        } catch (\Exception $e) {
            // sfContext not available
        }

        return 'en';
    }
}
