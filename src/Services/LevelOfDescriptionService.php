<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Level of Description Service.
 *
 * Manages archival levels with GLAM sector filtering.
 */
class LevelOfDescriptionService
{
    public const TAXONOMY_ID = 34;

    // Sector constants
    public const SECTOR_ARCHIVE = 'archive';
    public const SECTOR_MUSEUM = 'museum';
    public const SECTOR_LIBRARY = 'library';
    public const SECTOR_GALLERY = 'gallery';
    public const SECTOR_DAM = 'dam';
    public const SECTOR_ARCHAEOLOGY = 'archaeology';

    public const ALL_SECTORS = [
        self::SECTOR_ARCHIVE,
        self::SECTOR_MUSEUM,
        self::SECTOR_LIBRARY,
        self::SECTOR_GALLERY,
        self::SECTOR_DAM,
        self::SECTOR_ARCHAEOLOGY,
    ];

    // Plugin to sector mapping
    public const SECTOR_PLUGINS = [
        self::SECTOR_ARCHIVE => null, // Always available
        self::SECTOR_MUSEUM => ['sfMuseumPlugin', 'ahgMuseumPlugin'],
        self::SECTOR_LIBRARY => ['ahgLibraryPlugin', 'arLibraryPlugin'],
        self::SECTOR_GALLERY => ['arGalleryPlugin', 'ahgGalleryPlugin'],
        self::SECTOR_DAM => ['ahgDAMPlugin', 'arDAMPlugin'],
        self::SECTOR_ARCHAEOLOGY => ['ahgArchaeologyPlugin'],
    ];

    // Default levels per sector (by name - IDs vary between installations)
    public const SECTOR_DEFAULT_LEVELS = [
        self::SECTOR_ARCHIVE => [
            'Record group', 'Fonds', 'Subfonds', 'Collection',
            'Series', 'Subseries', 'File', 'Item', 'Part'
        ],
        self::SECTOR_MUSEUM => [
            '3D Model', 'Artifact', 'Artwork', 'Installation', 'Object', 'Specimen'
        ],
        self::SECTOR_LIBRARY => [
            'Book', 'Monograph', 'Periodical', 'Journal', 'Article', 'Manuscript', 'Document'
        ],
        self::SECTOR_GALLERY => [
            'Artwork', 'Photograph', 'Installation'
        ],
        self::SECTOR_DAM => [
            'Photograph', 'Audio', 'Video', 'Image', 'Document', '3D Model', 'Dataset'
        ],
        // Archaeology keeps the ISAD spine - series is the site, subseries the
        // trench, file the stratigraphic context - and adds only the two levels
        // ISAD has no word for. Nothing nests below a find or a sample, so they
        // cannot conflict with the hierarchy above them.
        self::SECTOR_ARCHAEOLOGY => [
            'Fonds', 'Series', 'Subseries', 'File', 'Item',
            'Find', 'Sample',
        ],
    ];

    /**
     * Get available sectors based on enabled plugins.
     */
    public static function getAvailableSectors(): array
    {
        $available = [self::SECTOR_ARCHIVE]; // Archive always available

        foreach (self::SECTOR_PLUGINS as $sector => $plugins) {
            if ($plugins === null) {
                continue; // Already added
            }

            foreach ($plugins as $plugin) {
                if (self::isPluginEnabled($plugin)) {
                    $available[] = $sector;
                    break;
                }
            }
        }

        return $available;
    }

    /**
     * Check if a plugin is enabled.
     */
    public static function isPluginEnabled(string $pluginName): bool
    {
        try {
            // Check atom_plugin table
            $enabled = DB::table('atom_plugin')
                ->where('name', $pluginName)
                ->where('is_enabled', 1)
                ->exists();

            return $enabled;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get levels appropriate for a sector (based on SECTOR_DEFAULT_LEVELS).
     */
    public static function getLevelsForSector(string $sector, ?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();
        $levelNames = self::SECTOR_DEFAULT_LEVELS[$sector] ?? [];

        if (empty($levelNames)) {
            return new Collection([]);
        }

        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.taxonomy_id', self::TAXONOMY_ID)
            ->whereIn('ti.name', $levelNames)
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 'ti.culture', 's.slug')
            ->get();
    }

    /**
     * Get all levels of description.
     */
    public static function getAll(?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();

        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.taxonomy_id', self::TAXONOMY_ID)
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 'ti.culture', 's.slug')
            ->get();
    }

    /**
     * Get levels by sector (from level_of_description_sector table).
     */
    public static function getBySector(string $sector, ?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();

        // Check if sector table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");

        if (empty($tableExists)) {
            // Fallback to default levels for sector
            return self::getLevelsForSector($sector, $culture);
        }

        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->join('level_of_description_sector as los', 't.id', '=', 'los.term_id')
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.taxonomy_id', self::TAXONOMY_ID)
            ->where('los.sector', $sector)
            ->orderBy('los.display_order')
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 'ti.culture', 's.slug', 'los.sector', 'los.display_order')
            ->get();
    }

    /**
     * Get levels for multiple sectors.
     */
    public static function getBySectors(array $sectors, ?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();

        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");

        if (empty($tableExists)) {
            return self::getAll($culture);
        }

        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->join('level_of_description_sector as los', 't.id', '=', 'los.term_id')
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.taxonomy_id', self::TAXONOMY_ID)
            ->whereIn('los.sector', $sectors)
            ->orderBy('los.display_order')
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 'ti.culture', 's.slug')
            ->distinct()
            ->get();
    }

    /**
     * Get level by ID.
     */
    public static function getById(int $id, ?string $culture = null): ?object
    {
        $culture = $culture ?? CultureHelper::getCulture();

        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.id', $id)
            ->select('t.id', 'ti.name', 'ti.culture', 's.slug')
            ->first();
    }

    /**
     * Get sector from template name.
     */
    public static function getSectorFromTemplate(string $template): string
    {
        $mapping = [
            'isad' => self::SECTOR_ARCHIVE,
            'rad' => self::SECTOR_ARCHIVE,
            'dacs' => self::SECTOR_ARCHIVE,
            'dc' => self::SECTOR_ARCHIVE,
            'mods' => self::SECTOR_LIBRARY,
            'museum' => self::SECTOR_MUSEUM,
            'cco' => self::SECTOR_MUSEUM,
            'cdwa' => self::SECTOR_GALLERY,
            'library' => self::SECTOR_LIBRARY,
            'gallery' => self::SECTOR_GALLERY,
            'dam' => self::SECTOR_DAM,
        ];

        return $mapping[strtolower($template)] ?? self::SECTOR_ARCHIVE;
    }

    /**
     * Get levels as form choices.
     */
    public static function getChoices(?string $sector = null, ?string $culture = null): array
    {
        $levels = $sector
            ? self::getBySector($sector, $culture)
            : self::getAll($culture);

        $choices = [];
        foreach ($levels as $level) {
            $choices[$level->id] = $level->name;
        }

        return $choices;
    }

    /**
     * Get levels grouped by sector.
     */
    public static function getGroupedBySector(?string $culture = null): array
    {
        $culture = $culture ?? CultureHelper::getCulture();

        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");

        if (empty($tableExists)) {
            return ['archive' => self::getAll($culture)->toArray()];
        }

        $grouped = [];

        foreach (self::ALL_SECTORS as $sector) {
            $levels = self::getBySector($sector, $culture);
            if ($levels->isNotEmpty()) {
                $grouped[$sector] = $levels->toArray();
            }
        }

        return $grouped;
    }

    /**
     * Get levels for current context (auto-detect sector from template setting).
     */
    public static function getForCurrentContext(?string $culture = null): Collection
    {
        $sector = self::detectCurrentSector();

        if ($sector) {
            return self::getBySector($sector, $culture);
        }

        return self::getAll($culture);
    }

    /**
     * Get choices for current context.
     */
    public static function getChoicesForContext(?string $culture = null): array
    {
        $sector = self::detectCurrentSector();
        return self::getChoices($sector, $culture);
    }

    /**
     * Detect current sector from template setting or context.
     */
    public static function detectCurrentSector(): ?string
    {
        $template = null;

        try {
            if (class_exists('sfConfig')) {
                $template = \sfConfig::get('app_default_template_informationobject');
            }

            if (!$template) {
                $setting = DB::table('setting as s')
                    ->join('setting_i18n as si', 's.id', '=', 'si.id')
                    ->where('s.name', 'informationobject')
                    ->where('s.scope', 'default_template')
                    ->value('si.value');

                $template = $setting ?: 'isad';
            }
        } catch (\Exception $e) {
            $template = 'isad';
        }

        return self::getSectorFromTemplate($template ?? 'isad');
    }

    /**
     * Count levels per sector.
     */
    public static function countBySector(): array
    {
        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");

        if (empty($tableExists)) {
            return [];
        }

        $counts = DB::table('level_of_description_sector')
            ->select('sector', DB::raw('COUNT(*) as count'))
            ->groupBy('sector')
            ->pluck('count', 'sector')
            ->toArray();

        return $counts;
    }

    /**
     * Create any level term declared in SECTOR_DEFAULT_LEVELS that is missing.
     *
     * bin/migrate-levels.php has always advertised this, but the method was
     * never written - running `up` fatalled on an undefined method, so the CLI
     * was a front end to nothing.
     *
     * Idempotent: an existing term is skipped, never duplicated. Matching is by
     * name within taxonomy 34, because ids differ between installations.
     *
     * @return array{created: string[], skipped: string[], errors: string[]}
     */
    public static function migrate(): array
    {
        $results = ['created' => [], 'skipped' => [], 'errors' => []];

        // Sectors share level names (Photograph is in both gallery and DAM), so
        // work from a deduplicated set rather than creating twice.
        $wanted = [];
        foreach (self::SECTOR_DEFAULT_LEVELS as $levels) {
            foreach ($levels as $name) {
                $wanted[$name] = true;
            }
        }

        $existing = self::existingLevelNames();

        foreach (array_keys($wanted) as $name) {
            if (isset($existing[mb_strtolower($name)])) {
                $results['skipped'][] = $name;

                continue;
            }

            try {
                self::createLevelTerm($name);
                $results['created'][] = $name;
                $existing[mb_strtolower($name)] = true;
            } catch (\Throwable $e) {
                $results['errors'][] = sprintf('%s: %s', $name, $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Remove level terms this migration added, where it is safe to do so.
     *
     * Deliberately conservative. A term is only removed when it is:
     *   - NOT part of the archive sector (those are base AtoM's own levels and
     *     must survive a rollback), and
     *   - not referenced by any information object.
     *
     * @return array{removed: string[], skipped: string[], errors: string[]}
     */
    public static function rollback(): array
    {
        $results = ['removed' => [], 'skipped' => [], 'errors' => []];

        $protected = [];
        foreach (self::SECTOR_DEFAULT_LEVELS[self::SECTOR_ARCHIVE] as $name) {
            $protected[mb_strtolower($name)] = true;
        }

        $candidates = [];
        foreach (self::SECTOR_DEFAULT_LEVELS as $sector => $levels) {
            if (self::SECTOR_ARCHIVE === $sector) {
                continue;
            }
            foreach ($levels as $name) {
                if (!isset($protected[mb_strtolower($name)])) {
                    $candidates[$name] = true;
                }
            }
        }

        foreach (array_keys($candidates) as $name) {
            try {
                $termId = DB::table('term as t')
                    ->join('term_i18n as ti', 'ti.id', '=', 't.id')
                    ->where('t.taxonomy_id', self::TAXONOMY_ID)
                    ->where('ti.name', $name)
                    ->value('t.id');

                if (!$termId) {
                    continue;   // never created, or already gone
                }

                $inUse = DB::table('information_object')->where('level_of_description_id', $termId)->count();
                if ($inUse > 0) {
                    $results['skipped'][] = sprintf('%s (in use by %d record(s))', $name, $inUse);

                    continue;
                }

                // object cascades to term, term_i18n and slug.
                DB::table('object')->where('id', $termId)->delete();
                $results['removed'][] = $name;
            } catch (\Throwable $e) {
                $results['errors'][] = sprintf('%s: %s', $name, $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Level term names already present in the taxonomy, keyed lowercase.
     */
    private static function existingLevelNames(): array
    {
        $names = [];
        $rows = DB::table('term as t')
            ->join('term_i18n as ti', 'ti.id', '=', 't.id')
            ->where('t.taxonomy_id', self::TAXONOMY_ID)
            ->whereNotNull('ti.name')
            ->pluck('ti.name');

        foreach ($rows as $name) {
            $names[mb_strtolower((string) $name)] = true;
        }

        return $names;
    }

    /**
     * Create one level term through AtoM's entity-inheritance chain.
     */
    private static function createLevelTerm(string $name): int
    {
        $now = date('Y-m-d H:i:s');

        $termId = DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('term')->insert([
            'id' => $termId,
            'taxonomy_id' => self::TAXONOMY_ID,
            'source_culture' => 'en',
        ]);

        DB::table('term_i18n')->insert([
            'id' => $termId,
            'culture' => 'en',
            'name' => $name,
        ]);

        // Slugs must be unique across every object, not just terms.
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
        $slug = $base;
        $i = 2;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        DB::table('slug')->insert(['object_id' => $termId, 'slug' => $slug]);

        return $termId;
    }
}
