<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Dropdown Service — column-aware validation and resolution.
 *
 * Bridges the ahg_dropdown table with ahg_dropdown_column_map to provide:
 * - Column → taxonomy mapping (which dropdown controls which column)
 * - Value validation against dropdown values
 * - Label resolution for display
 * - Bulk validation for imports/migrations
 *
 * This replaces all hardcoded ENUM values with database-driven dropdowns
 * managed via Admin > AHG Settings > Dropdown Manager.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class DropdownService
{
    protected static array $mapCache = [];
    protected static array $valuesCache = [];

    // ========================================================================
    // COLUMN MAPPING
    // ========================================================================

    /**
     * Get the taxonomy name for a table.column.
     */
    public static function getTaxonomy(string $table, string $column): ?string
    {
        $key = "{$table}.{$column}";

        if (isset(self::$mapCache[$key])) {
            return self::$mapCache[$key];
        }

        $map = DB::table('ahg_dropdown_column_map')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first();

        $taxonomy = $map->taxonomy ?? null;
        self::$mapCache[$key] = $taxonomy;

        return $taxonomy;
    }

    /**
     * Check if a column is mapped to a dropdown.
     */
    public static function isMapped(string $table, string $column): bool
    {
        return self::getTaxonomy($table, $column) !== null;
    }

    /**
     * Check if a column is strict (only dropdown values allowed).
     */
    public static function isStrict(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        $map = DB::table('ahg_dropdown_column_map')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first();

        return (bool) ($map->is_strict ?? true);
    }

    /**
     * Get all column mappings for a table.
     */
    public static function getMappingsForTable(string $table): array
    {
        return DB::table('ahg_dropdown_column_map')
            ->where('table_name', $table)
            ->get()
            ->keyBy('column_name')
            ->all();
    }

    // ========================================================================
    // VALIDATION
    // ========================================================================

    /**
     * Validate a value against the dropdown for a column.
     * Returns true if valid, false if invalid.
     * Returns true for unmapped columns or non-strict columns with any value.
     */
    public static function isValid(string $table, string $column, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $taxonomy = self::getTaxonomy($table, $column);
        if ($taxonomy === null) {
            return true; // Not mapped, allow anything
        }

        $values = self::getValidValues($taxonomy);

        if (in_array($value, $values, true)) {
            return true;
        }

        // Non-strict columns allow freetext
        if (!self::isStrict($table, $column)) {
            return true;
        }

        return false;
    }

    /**
     * Validate a value directly against a taxonomy.
     */
    public static function isValidForTaxonomy(string $taxonomy, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, self::getValidValues($taxonomy), true);
    }

    /**
     * Get all valid value codes for a taxonomy.
     */
    public static function getValidValues(string $taxonomy): array
    {
        if (isset(self::$valuesCache[$taxonomy])) {
            return self::$valuesCache[$taxonomy];
        }

        $values = DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();

        self::$valuesCache[$taxonomy] = $values;

        return $values;
    }

    /**
     * Get valid values as [code => label] for a column.
     */
    public static function getChoicesForColumn(string $table, string $column, bool $includeEmpty = true): array
    {
        $taxonomy = self::getTaxonomy($table, $column);
        if ($taxonomy === null) {
            return [];
        }

        return self::getChoices($taxonomy, $includeEmpty);
    }

    /**
     * Get valid values as [code => label] for a taxonomy.
     */
    public static function getChoices(string $taxonomy, bool $includeEmpty = true): array
    {
        $choices = $includeEmpty ? ['' => ''] : [];

        $terms = DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->select(['code', 'label'])
            ->get();

        foreach ($terms as $term) {
            $choices[$term->code] = $term->label;
        }

        return $choices;
    }

    /**
     * Get choices with full attributes (code, label, color, icon).
     */
    public static function getChoicesWithAttributes(string $taxonomy): array
    {
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->select(['id', 'code', 'label', 'color', 'icon', 'sort_order', 'is_default', 'metadata'])
            ->get()
            ->keyBy('code')
            ->all();
    }

    // ========================================================================
    // LABEL RESOLUTION
    // ========================================================================

    /**
     * Resolve a code to its display label for a column.
     */
    public static function resolveLabel(string $table, string $column, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $taxonomy = self::getTaxonomy($table, $column);
        if ($taxonomy === null) {
            return $code; // No mapping, return raw value
        }

        return self::resolveLabelForTaxonomy($taxonomy, $code);
    }

    /**
     * Resolve a code to its display label for a taxonomy.
     */
    public static function resolveLabelForTaxonomy(string $taxonomy, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $cacheKey = "{$taxonomy}:{$code}";

        $label = DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('code', $code)
            ->value('label');

        return $label ?? $code;
    }

    /**
     * Resolve a code to its color for a taxonomy.
     */
    public static function resolveColor(string $taxonomy, string $code): ?string
    {
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('code', $code)
            ->value('color');
    }

    /**
     * Get the default value for a column.
     */
    public static function getDefault(string $table, string $column): ?string
    {
        $taxonomy = self::getTaxonomy($table, $column);
        if ($taxonomy === null) {
            return null;
        }

        return self::getDefaultForTaxonomy($taxonomy);
    }

    /**
     * Get the default value code for a taxonomy.
     */
    public static function getDefaultForTaxonomy(string $taxonomy): ?string
    {
        $default = DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->where('is_default', 1)
            ->value('code');

        if ($default !== null) {
            return $default;
        }

        // Fall back to first term
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->value('code');
    }

    // ========================================================================
    // BULK OPERATIONS
    // ========================================================================

    /**
     * Validate multiple values for a table row.
     * Returns array of invalid columns with details.
     */
    public static function validateRow(string $table, array $row): array
    {
        $errors = [];
        $mappings = self::getMappingsForTable($table);

        foreach ($mappings as $column => $map) {
            if (!isset($row[$column]) || $row[$column] === null || $row[$column] === '') {
                continue;
            }

            $value = $row[$column];
            $validValues = self::getValidValues($map->taxonomy);

            if (!in_array($value, $validValues, true) && $map->is_strict) {
                $errors[$column] = [
                    'value' => $value,
                    'taxonomy' => $map->taxonomy,
                    'valid_values' => $validValues,
                ];
            }
        }

        return $errors;
    }

    /**
     * Get statistics about dropdown coverage.
     */
    public static function getStats(): array
    {
        $totalTaxonomies = DB::table('ahg_dropdown')
            ->select('taxonomy')
            ->distinct()
            ->count('taxonomy');

        $totalValues = DB::table('ahg_dropdown')->count();

        $activeValues = DB::table('ahg_dropdown')
            ->where('is_active', 1)
            ->count();

        $mappedColumns = DB::table('ahg_dropdown_column_map')->count();

        $strictColumns = DB::table('ahg_dropdown_column_map')
            ->where('is_strict', 1)
            ->count();

        return [
            'taxonomies' => $totalTaxonomies,
            'total_values' => $totalValues,
            'active_values' => $activeValues,
            'mapped_columns' => $mappedColumns,
            'strict_columns' => $strictColumns,
        ];
    }

    // ========================================================================
    // CACHE MANAGEMENT
    // ========================================================================

    /**
     * Clear all caches.
     */
    public static function clearCache(): void
    {
        self::$mapCache = [];
        self::$valuesCache = [];
    }
}
