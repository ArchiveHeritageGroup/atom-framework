<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Slug Service - Replaces QubitSlug.
 *
 * Provides slug generation and management.
 * Maintains full compatibility with AtoM's slug patterns.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class SlugService
{
    /**
     * Slug basis constants (from QubitSlug).
     */
    public const SLUG_BASIS_TITLE = 0;
    public const SLUG_BASIS_IDENTIFIER = 1;
    public const SLUG_BASIS_REFERENCE_CODE = 2;
    public const SLUG_RESTRICTIVE = 0;
    public const SLUG_PERMISSIVE = 1;

    /**
     * Get slug by object ID.
     *
     * Replaces: QubitSlug::getByObjectId($id)
     */
    public static function getByObjectId(int $objectId): ?object
    {
        return DB::table('slug')
            ->where('object_id', $objectId)
            ->first();
    }

    /**
     * Get object ID by slug.
     *
     * Replaces: QubitSlug::getBySlug($slug)
     */
    public static function getBySlug(string $slug): ?object
    {
        return DB::table('slug')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Generate unique slug from string.
     */
    public static function generateSlug(string $string, int $objectId = null): string
    {
        // Transliterate and clean
        $slug = self::slugify($string);

        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;

        while (self::slugExists($slug, $objectId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     */
    public static function slugExists(string $slug, ?int $excludeObjectId = null): bool
    {
        $query = DB::table('slug')->where('slug', $slug);

        if ($excludeObjectId) {
            $query->where('object_id', '!=', $excludeObjectId);
        }

        return $query->exists();
    }

    /**
     * Create slug for object.
     */
    public static function createSlug(int $objectId, string $string): object
    {
        $slug = self::generateSlug($string, $objectId);

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);

        return (object) [
            'object_id' => $objectId,
            'slug' => $slug,
        ];
    }

    /**
     * Update slug for object.
     */
    public static function updateSlug(int $objectId, string $newSlug): bool
    {
        // Ensure uniqueness
        $slug = self::generateSlug($newSlug, $objectId);

        return DB::table('slug')
            ->where('object_id', $objectId)
            ->update(['slug' => $slug]) > 0;
    }

    /**
     * Delete slug for object.
     */
    public static function deleteSlug(int $objectId): bool
    {
        return DB::table('slug')
            ->where('object_id', $objectId)
            ->delete() > 0;
    }

    /**
     * Convert string to URL-safe slug.
     */
    public static function slugify(string $string): string
    {
        // Convert to lowercase
        $slug = mb_strtolower($string, 'UTF-8');

        // Transliterate non-ASCII characters
        $slug = self::transliterate($slug);

        // Replace non-alphanumeric characters with dashes
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing dashes
        $slug = trim($slug, '-');

        // Limit length
        if (strlen($slug) > 250) {
            $slug = substr($slug, 0, 250);
            $slug = rtrim($slug, '-');
        }

        // Fallback for empty slugs
        if (empty($slug)) {
            $slug = 'untitled-' . time();
        }

        return $slug;
    }

    /**
     * Transliterate non-ASCII characters.
     */
    private static function transliterate(string $string): string
    {
        $transliterations = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ø' => 'o', 'æ' => 'ae',
            'þ' => 'th', 'ð' => 'dh', 'ý' => 'y', 'ÿ' => 'y',
        ];

        return strtr($string, $transliterations);
    }

    /**
     * Get slug basis setting.
     */
    public static function getSlugBasis(): int
    {
        $setting = SettingService::getValue('slug_basis_informationobject');

        return (int) ($setting ?? self::SLUG_BASIS_TITLE);
    }

    /**
     * Check if permissive slug creation is enabled.
     */
    public static function isPermissive(): bool
    {
        $setting = SettingService::getValue('permissive_slug_creation');

        return (int) $setting === self::SLUG_PERMISSIVE;
    }

    /**
     * Table name constant (replaces QubitSlug::TABLE_NAME).
     */
    public const TABLE_NAME = 'slug';

    /**
     * Get valid slug characters for regex (replaces QubitSlug::getValidSlugChars).
     */
    public static function getValidSlugChars(): string
    {
        return 'a-z0-9_-';
    }

}