<?php
declare(strict_types=1);

namespace AtomExtensions\Migrations;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Extended Levels of Description Migration.
 * 
 * Adds GLAM sector-specific levels: Museum, Library, Gallery, DAM.
 */
class ExtendedLevelsOfDescription
{
    public const TAXONOMY_ID = 34; // LEVEL_OF_DESCRIPTION_ID
    
    // New level IDs (starting from 500 to avoid conflicts)
    public const LEVELS = [
        // Museum
        ['id' => 500, 'name' => 'Object', 'sector' => 'museum', 'order' => 10],
        ['id' => 501, 'name' => 'Artwork', 'sector' => 'museum,gallery', 'order' => 20],
        ['id' => 502, 'name' => 'Artifact', 'sector' => 'museum', 'order' => 30],
        ['id' => 503, 'name' => 'Specimen', 'sector' => 'museum', 'order' => 40],
        // Library
        ['id' => 504, 'name' => 'Book', 'sector' => 'library', 'order' => 10],
        ['id' => 505, 'name' => 'Journal', 'sector' => 'library', 'order' => 20],
        ['id' => 506, 'name' => 'Periodical', 'sector' => 'library', 'order' => 30],
        ['id' => 507, 'name' => 'Article', 'sector' => 'library', 'order' => 40],
        ['id' => 508, 'name' => 'Manuscript', 'sector' => 'library,archive', 'order' => 50],
        // Gallery
        ['id' => 509, 'name' => 'Photograph', 'sector' => 'gallery,dam', 'order' => 10],
        ['id' => 510, 'name' => 'Print', 'sector' => 'gallery', 'order' => 20],
        ['id' => 511, 'name' => 'Sculpture', 'sector' => 'gallery,museum', 'order' => 30],
        ['id' => 512, 'name' => 'Installation', 'sector' => 'gallery', 'order' => 40],
        // DAM (Digital Asset Management)
        ['id' => 513, 'name' => 'Audio', 'sector' => 'dam', 'order' => 10],
        ['id' => 514, 'name' => 'Video', 'sector' => 'dam', 'order' => 20],
        ['id' => 515, 'name' => 'Image', 'sector' => 'dam', 'order' => 30],
        ['id' => 516, 'name' => '3D Model', 'sector' => 'dam', 'order' => 40],
        ['id' => 517, 'name' => 'Document', 'sector' => 'dam,library', 'order' => 50],
        ['id' => 518, 'name' => 'Dataset', 'sector' => 'dam', 'order' => 60],
    ];
    
    // MIME type to level mapping for auto-detection
    public const MIME_MAPPING = [
        'image/jpeg' => 509,      // Photograph
        'image/png' => 515,       // Image
        'image/gif' => 515,       // Image
        'image/tiff' => 509,      // Photograph
        'image/webp' => 515,      // Image
        'audio/mpeg' => 513,      // Audio
        'audio/wav' => 513,       // Audio
        'audio/ogg' => 513,       // Audio
        'audio/flac' => 513,      // Audio
        'video/mp4' => 514,       // Video
        'video/webm' => 514,      // Video
        'video/quicktime' => 514, // Video
        'video/x-msvideo' => 514, // Video
        'application/pdf' => 517, // Document
        'model/gltf+json' => 516, // 3D Model
        'model/gltf-binary' => 516, // 3D Model
        'model/obj' => 516,       // 3D Model
        'model/stl' => 516,       // 3D Model
    ];

    public static function up(): array
    {
        $results = ['created' => [], 'skipped' => [], 'errors' => []];
        
        // Create sector mapping table if not exists
        self::createSectorTable();
        
        foreach (self::LEVELS as $level) {
            try {
                // Check if term already exists
                $exists = DB::table('term')
                    ->where('id', $level['id'])
                    ->exists();
                
                if ($exists) {
                    $results['skipped'][] = $level['name'];
                    continue;
                }
                
                // Check if object exists
                $objectExists = DB::table('object')
                    ->where('id', $level['id'])
                    ->exists();
                
                if (!$objectExists) {
                    // Create object record first
                    DB::table('object')->insert([
                        'id' => $level['id'],
                        'class_name' => 'QubitTerm',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                
                // Create term
                DB::table('term')->insert([
                    'id' => $level['id'],
                    'taxonomy_id' => self::TAXONOMY_ID,
                    'source_culture' => 'en',
                ]);
                
                // Create term_i18n
                DB::table('term_i18n')->insert([
                    'id' => $level['id'],
                    'culture' => 'en',
                    'name' => $level['name'],
                ]);
                
                // Create slug
                $slug = strtolower(str_replace([' ', '_'], '-', $level['name']));
                DB::table('slug')->insert([
                    'object_id' => $level['id'],
                    'slug' => $slug,
                ]);
                
                // Add sector mapping
                $sectors = explode(',', $level['sector']);
                foreach ($sectors as $sector) {
                    DB::table('level_of_description_sector')->insert([
                        'term_id' => $level['id'],
                        'sector' => trim($sector),
                        'display_order' => $level['order'],
                    ]);
                }
                
                $results['created'][] = $level['name'];
                
            } catch (\Exception $e) {
                $results['errors'][] = $level['name'] . ': ' . $e->getMessage();
            }
        }
        
        // Add existing ISAD levels to archive sector
        self::mapExistingLevelsToSector();
        
        return $results;
    }
    
    private static function createSectorTable(): void
    {
        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");
        
        if (empty($tableExists)) {
            DB::statement("
                CREATE TABLE level_of_description_sector (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    term_id INT NOT NULL,
                    sector VARCHAR(50) NOT NULL,
                    display_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_term_sector (term_id, sector),
                    INDEX idx_sector (sector),
                    INDEX idx_term (term_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
    
    private static function mapExistingLevelsToSector(): void
    {
        // Map existing ISAD levels to archive sector
        $existingLevels = [
            220 => 10,  // Fonds
            221 => 20,  // Sub-fonds
            222 => 30,  // Collection
            223 => 40,  // Series
            224 => 50,  // Sub-series
            225 => 60,  // File
            226 => 70,  // Item
        ];
        
        foreach ($existingLevels as $termId => $order) {
            $exists = DB::table('level_of_description_sector')
                ->where('term_id', $termId)
                ->where('sector', 'archive')
                ->exists();
            
            if (!$exists) {
                // Check if term exists before adding
                $termExists = DB::table('term')->where('id', $termId)->exists();
                if ($termExists) {
                    DB::table('level_of_description_sector')->insert([
                        'term_id' => $termId,
                        'sector' => 'archive',
                        'display_order' => $order,
                    ]);
                }
            }
        }
    }
    
    public static function down(): array
    {
        $results = ['removed' => [], 'errors' => []];
        
        foreach (self::LEVELS as $level) {
            try {
                DB::table('level_of_description_sector')->where('term_id', $level['id'])->delete();
                DB::table('slug')->where('object_id', $level['id'])->delete();
                DB::table('term_i18n')->where('id', $level['id'])->delete();
                DB::table('term')->where('id', $level['id'])->delete();
                DB::table('object')->where('id', $level['id'])->delete();
                
                $results['removed'][] = $level['name'];
            } catch (\Exception $e) {
                $results['errors'][] = $level['name'] . ': ' . $e->getMessage();
            }
        }
        
        return $results;
    }
}
