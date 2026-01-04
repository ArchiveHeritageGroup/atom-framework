<?php
declare(strict_types=1);

namespace AtomExtensions\Migrations;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Extended Levels of Description Migration.
 *
 * Adds GLAM sector-specific levels: Museum, Library, Gallery, DAM.
 * Uses NAME-based lookups, not hardcoded IDs.
 */
class ExtendedLevelsOfDescription
{
    public const TAXONOMY_ID = 34; // LEVEL_OF_DESCRIPTION_ID

    // Level definitions - NO hardcoded IDs
    public const LEVELS = [
        // Museum/Gallery
        ['name' => 'Object', 'slug' => 'object-level', 'sectors' => ['museum']],
        ['name' => 'Installation', 'slug' => 'installation', 'sectors' => ['museum', 'gallery']],
        ['name' => 'Artwork', 'slug' => 'artwork', 'sectors' => ['museum', 'gallery']],
        ['name' => 'Artifact', 'slug' => 'artifact', 'sectors' => ['museum']],
        ['name' => 'Specimen', 'slug' => 'specimen', 'sectors' => ['museum']],
        ['name' => '3D Model', 'slug' => '3d-model', 'sectors' => ['museum', 'dam']],
        // Library
        ['name' => 'Document', 'slug' => 'document', 'sectors' => ['library', 'dam']],
        ['name' => 'Book', 'slug' => 'book', 'sectors' => ['library']],
        ['name' => 'Monograph', 'slug' => 'monograph', 'sectors' => ['library']],
        ['name' => 'Periodical', 'slug' => 'periodical', 'sectors' => ['library']],
        ['name' => 'Journal', 'slug' => 'journal', 'sectors' => ['library']],
        ['name' => 'Manuscript', 'slug' => 'manuscript', 'sectors' => ['library']],
        ['name' => 'Article', 'slug' => 'article', 'sectors' => ['library']],
        // DAM
        ['name' => 'Photograph', 'slug' => 'photograph', 'sectors' => ['dam', 'gallery']],
        ['name' => 'Audio', 'slug' => 'audio', 'sectors' => ['dam']],
        ['name' => 'Video', 'slug' => 'video', 'sectors' => ['dam']],
        ['name' => 'Image', 'slug' => 'image', 'sectors' => ['dam']],
        ['name' => 'Dataset', 'slug' => 'dataset', 'sectors' => ['dam']],
    ];

    // Sector display order by NAME
    public const SECTOR_ORDER = [
        'archive' => [
            'Record group' => 10, 'Fonds' => 20, 'Subfonds' => 30, 'Collection' => 40,
            'Series' => 50, 'Subseries' => 60, 'File' => 70, 'Item' => 80, 'Part' => 90
        ],
        'museum' => [
            '3D Model' => 10, 'Artifact' => 20, 'Artwork' => 30,
            'Installation' => 40, 'Object' => 50, 'Specimen' => 60
        ],
        'library' => [
            'Book' => 10, 'Monograph' => 20, 'Periodical' => 30, 'Journal' => 40,
            'Article' => 45, 'Manuscript' => 50, 'Document' => 60
        ],
        'gallery' => [
            'Artwork' => 10, 'Photograph' => 20, 'Installation' => 40
        ],
        'dam' => [
            'Photograph' => 10, 'Audio' => 20, 'Video' => 30, 'Image' => 40,
            'Document' => 50, '3D Model' => 60, 'Dataset' => 70
        ],
    ];

    public static function up(): array
    {
        $results = ['created' => [], 'skipped' => [], 'sectors_added' => [], 'errors' => []];

        // Create sector mapping table if not exists
        self::createSectorTable();

        foreach (self::LEVELS as $level) {
            try {
                // Find term by name in taxonomy 34
                $termId = self::findOrCreateTerm($level, $results);
                
                if ($termId) {
                    // Ensure sector mappings
                    self::ensureSectorMappings($termId, $level['name'], $level['sectors'], $results);
                }

            } catch (\Exception $e) {
                $results['errors'][] = "{$level['name']}: " . $e->getMessage();
            }
        }

        // Ensure archive levels have sector mappings
        self::ensureArchiveSectorMappings($results);

        return $results;
    }

    protected static function findOrCreateTerm(array $level, array &$results): ?int
    {
        // Look up by name
        $existing = DB::table('term as t')
            ->join('term_i18n as ti', 't.id', '=', 'ti.id')
            ->where('t.taxonomy_id', self::TAXONOMY_ID)
            ->where('ti.culture', 'en')
            ->where('ti.name', $level['name'])
            ->first();

        if ($existing) {
            $results['skipped'][] = $level['name'];
            return $existing->id;
        }

        // Create new term - get next available ID
        $maxId = DB::table('object')->max('id') ?? 0;
        $newId = max($maxId + 1, 2000); // Start GLAM IDs at 2000+ to avoid conflicts

        // Create object
        DB::table('object')->insert([
            'id' => $newId,
            'class_name' => 'QubitTerm',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create term
        DB::table('term')->insert([
            'id' => $newId,
            'taxonomy_id' => self::TAXONOMY_ID,
            'source_culture' => 'en',
            'class_name' => 'QubitTerm',
        ]);

        // Create term_i18n
        DB::table('term_i18n')->insert([
            'id' => $newId,
            'culture' => 'en',
            'name' => $level['name'],
        ]);

        // Create unique slug
        $slug = self::createUniqueSlug($level['slug'], $newId);
        DB::table('slug')->insert([
            'object_id' => $newId,
            'slug' => $slug,
        ]);

        $results['created'][] = $level['name'];
        return $newId;
    }

    protected static function createUniqueSlug(string $baseSlug, int $objectId): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    protected static function createSectorTable(): void
    {
        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");
        
        if (empty($tableExists)) {
            DB::statement("
                CREATE TABLE level_of_description_sector (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    term_id INT NOT NULL,
                    sector VARCHAR(50) NOT NULL,
                    display_order INT DEFAULT 100,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_term_sector (term_id, sector),
                    INDEX idx_sector (sector)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    protected static function ensureSectorMappings(int $termId, string $termName, array $sectors, array &$results): void
    {
        foreach ($sectors as $sector) {
            $order = self::SECTOR_ORDER[$sector][$termName] ?? 100;
            
            $exists = DB::table('level_of_description_sector')
                ->where('term_id', $termId)
                ->where('sector', $sector)
                ->exists();

            if (!$exists) {
                DB::table('level_of_description_sector')->insert([
                    'term_id' => $termId,
                    'sector' => $sector,
                    'display_order' => $order,
                ]);
                $results['sectors_added'][] = "{$termName} -> {$sector}";
            }
        }
    }

    protected static function ensureArchiveSectorMappings(array &$results): void
    {
        // Standard AtoM archive level names
        $archiveLevels = ['Record group', 'Fonds', 'Subfonds', 'Collection', 'Series', 'Subseries', 'File', 'Item', 'Part'];
        
        foreach ($archiveLevels as $levelName) {
            // Find by name
            $term = DB::table('term as t')
                ->join('term_i18n as ti', 't.id', '=', 'ti.id')
                ->where('t.taxonomy_id', self::TAXONOMY_ID)
                ->where('ti.culture', 'en')
                ->where('ti.name', $levelName)
                ->first();

            if ($term) {
                $exists = DB::table('level_of_description_sector')
                    ->where('term_id', $term->id)
                    ->where('sector', 'archive')
                    ->exists();

                if (!$exists) {
                    $order = self::SECTOR_ORDER['archive'][$levelName] ?? 100;
                    DB::table('level_of_description_sector')->insert([
                        'term_id' => $term->id,
                        'sector' => 'archive',
                        'display_order' => $order,
                    ]);
                    $results['sectors_added'][] = "{$levelName} -> archive";
                }
            }
        }
    }

    public static function down(): array
    {
        $results = ['removed' => [], 'errors' => []];

        // Only remove GLAM levels we created, not archive levels
        foreach (self::LEVELS as $level) {
            try {
                // Find by name
                $term = DB::table('term as t')
                    ->join('term_i18n as ti', 't.id', '=', 'ti.id')
                    ->where('t.taxonomy_id', self::TAXONOMY_ID)
                    ->where('ti.culture', 'en')
                    ->where('ti.name', $level['name'])
                    ->first();

                if (!$term) {
                    continue;
                }

                // Remove sector mappings
                DB::table('level_of_description_sector')
                    ->where('term_id', $term->id)
                    ->delete();

                // Remove slug
                DB::table('slug')
                    ->where('object_id', $term->id)
                    ->delete();

                // Remove term_i18n
                DB::table('term_i18n')
                    ->where('id', $term->id)
                    ->delete();

                // Remove term
                DB::table('term')
                    ->where('id', $term->id)
                    ->delete();

                // Remove object
                DB::table('object')
                    ->where('id', $term->id)
                    ->delete();

                $results['removed'][] = $level['name'];

            } catch (\Exception $e) {
                $results['errors'][] = "{$level['name']}: " . $e->getMessage();
            }
        }

        return $results;
    }
}
