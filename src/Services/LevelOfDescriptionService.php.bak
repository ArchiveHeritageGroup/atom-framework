<?php
declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;
use AtomExtensions\Migrations\ExtendedLevelsOfDescription;
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
    
    public const ALL_SECTORS = [
        self::SECTOR_ARCHIVE,
        self::SECTOR_MUSEUM,
        self::SECTOR_LIBRARY,
        self::SECTOR_GALLERY,
        self::SECTOR_DAM,
    ];
    
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
     * Get levels by sector.
     */
    public static function getBySector(string $sector, ?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();
        
        // Check if sector table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'level_of_description_sector'");
        
        if (empty($tableExists)) {
            // Fallback to all levels
            return self::getAll($culture);
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
     * Detect level from MIME type.
     */
    public static function detectFromMimeType(string $mimeType): ?int
    {
        // Direct mapping
        if (isset(ExtendedLevelsOfDescription::MIME_MAPPING[$mimeType])) {
            return ExtendedLevelsOfDescription::MIME_MAPPING[$mimeType];
        }
        
        // Pattern matching
        $patterns = [
            '/^image\//' => 515,    // Image
            '/^audio\//' => 513,    // Audio
            '/^video\//' => 514,    // Video
            '/^model\//' => 516,    // 3D Model
        ];
        
        foreach ($patterns as $pattern => $levelId) {
            if (preg_match($pattern, $mimeType)) {
                return $levelId;
            }
        }
        
        return null;
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
     * Run migration.
     */
    public static function migrate(): array
    {
        return ExtendedLevelsOfDescription::up();
    }
    
    /**
     * Rollback migration.
     */
    public static function rollback(): array
    {
        return ExtendedLevelsOfDescription::down();
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
        // Try to get from settings
        $template = null;
        
        try {
            if (class_exists('sfConfig')) {
                $template = \sfConfig::get('app_default_template_informationobject');
            }
            
            if (!$template) {
                $setting = \Illuminate\Database\Capsule\Manager::table('setting as s')
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
	
}
