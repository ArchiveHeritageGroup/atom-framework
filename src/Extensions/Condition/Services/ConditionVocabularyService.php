<?php

declare(strict_types=1);

namespace AtoM\Framework\Extensions\Condition\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Condition Vocabulary Service
 * 
 * Provides controlled vocabularies for condition reporting including
 * damage types, materials affected, severity levels, and treatment options.
 * Based on conservation standards including CIDOC CRM and CCO.
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ConditionVocabularyService
{
    private static ?Logger $logger = null;

    /**
     * Standard damage type vocabulary
     * Based on common conservation terminology
     */
    public const DAMAGE_TYPES = [
        'physical' => [
            'label' => 'Physical Damage',
            'terms' => [
                'crack' => ['label' => 'Crack', 'severity_default' => 'moderate', 'color' => '#FF4500'],
                'tear' => ['label' => 'Tear', 'severity_default' => 'moderate', 'color' => '#DC143C'],
                'loss' => ['label' => 'Loss/Lacuna', 'severity_default' => 'severe', 'color' => '#9400D3'],
                'abrasion' => ['label' => 'Abrasion', 'severity_default' => 'minor', 'color' => '#FF8C00'],
                'scratch' => ['label' => 'Scratch', 'severity_default' => 'minor', 'color' => '#FFD700'],
                'dent' => ['label' => 'Dent', 'severity_default' => 'minor', 'color' => '#CD853F'],
                'warp' => ['label' => 'Warping', 'severity_default' => 'moderate', 'color' => '#8B4513'],
                'break' => ['label' => 'Break/Fracture', 'severity_default' => 'severe', 'color' => '#B22222'],
                'chip' => ['label' => 'Chip', 'severity_default' => 'minor', 'color' => '#D2691E'],
                'delamination' => ['label' => 'Delamination', 'severity_default' => 'moderate', 'color' => '#A0522D'],
            ],
        ],
        'biological' => [
            'label' => 'Biological Damage',
            'terms' => [
                'mould' => ['label' => 'Mould/Mildew', 'severity_default' => 'severe', 'color' => '#8B0000'],
                'pest_damage' => ['label' => 'Insect Damage', 'severity_default' => 'severe', 'color' => '#006400'],
                'rodent_damage' => ['label' => 'Rodent Damage', 'severity_default' => 'severe', 'color' => '#556B2F'],
                'biofilm' => ['label' => 'Biofilm', 'severity_default' => 'moderate', 'color' => '#2E8B57'],
                'algae' => ['label' => 'Algae Growth', 'severity_default' => 'moderate', 'color' => '#228B22'],
            ],
        ],
        'chemical' => [
            'label' => 'Chemical Damage',
            'terms' => [
                'corrosion' => ['label' => 'Corrosion', 'severity_default' => 'moderate', 'color' => '#2F4F4F'],
                'oxidation' => ['label' => 'Oxidation', 'severity_default' => 'moderate', 'color' => '#708090'],
                'tarnish' => ['label' => 'Tarnish', 'severity_default' => 'minor', 'color' => '#778899'],
                'discoloration' => ['label' => 'Discoloration', 'severity_default' => 'minor', 'color' => '#DEB887'],
                'efflorescence' => ['label' => 'Efflorescence', 'severity_default' => 'moderate', 'color' => '#DCDCDC'],
                'acid_damage' => ['label' => 'Acid Damage', 'severity_default' => 'severe', 'color' => '#8B0000'],
            ],
        ],
        'environmental' => [
            'label' => 'Environmental Damage',
            'terms' => [
                'water_damage' => ['label' => 'Water Damage', 'severity_default' => 'severe', 'color' => '#1E90FF'],
                'tide_line' => ['label' => 'Tide Line', 'severity_default' => 'moderate', 'color' => '#87CEEB'],
                'fading' => ['label' => 'Fading/Light Damage', 'severity_default' => 'moderate', 'color' => '#F0E68C'],
                'yellowing' => ['label' => 'Yellowing', 'severity_default' => 'minor', 'color' => '#F5DEB3'],
                'foxing' => ['label' => 'Foxing', 'severity_default' => 'minor', 'color' => '#D2B48C'],
                'heat_damage' => ['label' => 'Heat Damage', 'severity_default' => 'moderate', 'color' => '#FF6347'],
                'dust_soiling' => ['label' => 'Dust/Soiling', 'severity_default' => 'minor', 'color' => '#A9A9A9'],
            ],
        ],
        'structural' => [
            'label' => 'Structural Issues',
            'terms' => [
                'structural_instability' => ['label' => 'Structural Instability', 'severity_default' => 'severe', 'color' => '#B22222'],
                'loose_element' => ['label' => 'Loose Element', 'severity_default' => 'moderate', 'color' => '#CD5C5C'],
                'missing_element' => ['label' => 'Missing Element', 'severity_default' => 'moderate', 'color' => '#8B0000'],
                'detachment' => ['label' => 'Detachment', 'severity_default' => 'moderate', 'color' => '#A52A2A'],
                'lifting' => ['label' => 'Lifting', 'severity_default' => 'moderate', 'color' => '#BC8F8F'],
            ],
        ],
        'surface' => [
            'label' => 'Surface Issues',
            'terms' => [
                'stain' => ['label' => 'Stain', 'severity_default' => 'minor', 'color' => '#DAA520'],
                'accretion' => ['label' => 'Accretion/Deposit', 'severity_default' => 'minor', 'color' => '#BDB76B'],
                'residue' => ['label' => 'Adhesive Residue', 'severity_default' => 'minor', 'color' => '#F0E68C'],
                'graffiti' => ['label' => 'Graffiti/Markings', 'severity_default' => 'moderate', 'color' => '#FF1493'],
                'paint_loss' => ['label' => 'Paint/Surface Loss', 'severity_default' => 'moderate', 'color' => '#BA55D3'],
            ],
        ],
    ];

    /**
     * Severity levels vocabulary
     */
    public const SEVERITY_LEVELS = [
        'critical' => [
            'label' => 'Critical',
            'description' => 'Immediate intervention required - risk of total loss',
            'color' => '#DC143C',
            'priority' => 100,
        ],
        'severe' => [
            'label' => 'Severe',
            'description' => 'Urgent treatment needed - significant deterioration',
            'color' => '#FF4500',
            'priority' => 80,
        ],
        'moderate' => [
            'label' => 'Moderate',
            'description' => 'Treatment recommended - ongoing deterioration',
            'color' => '#FFA500',
            'priority' => 50,
        ],
        'minor' => [
            'label' => 'Minor',
            'description' => 'Monitor - stable but notable condition issues',
            'color' => '#FFD700',
            'priority' => 20,
        ],
        'stable' => [
            'label' => 'Stable/Good',
            'description' => 'No immediate concern - cosmetic issues only',
            'color' => '#32CD32',
            'priority' => 5,
        ],
    ];

    /**
     * Material types vocabulary
     */
    public const MATERIAL_TYPES = [
        'organic' => [
            'label' => 'Organic Materials',
            'terms' => [
                'paper' => 'Paper',
                'parchment' => 'Parchment/Vellum',
                'leather' => 'Leather',
                'textile' => 'Textile/Fabric',
                'wood' => 'Wood',
                'ivory' => 'Ivory/Bone',
                'shell' => 'Shell',
                'feather' => 'Feather',
                'hair' => 'Hair/Fur',
                'plant_fiber' => 'Plant Fiber',
            ],
        ],
        'inorganic' => [
            'label' => 'Inorganic Materials',
            'terms' => [
                'metal_ferrous' => 'Metal (Ferrous)',
                'metal_copper' => 'Metal (Copper Alloy)',
                'metal_silver' => 'Metal (Silver)',
                'metal_gold' => 'Metal (Gold)',
                'metal_other' => 'Metal (Other)',
                'glass' => 'Glass',
                'ceramic' => 'Ceramic',
                'stone' => 'Stone',
                'mineral' => 'Mineral',
                'plaster' => 'Plaster',
            ],
        ],
        'composite' => [
            'label' => 'Composite/Mixed',
            'terms' => [
                'photographic' => 'Photographic Material',
                'paint_layer' => 'Paint Layer',
                'adhesive' => 'Adhesive',
                'coating' => 'Coating/Varnish',
                'plastic' => 'Plastic/Synthetic',
                'composite' => 'Composite',
            ],
        ],
    ];

    /**
     * Location zones for object mapping
     */
    public const LOCATION_ZONES = [
        'general' => [
            'front' => 'Front/Recto',
            'back' => 'Back/Verso',
            'top' => 'Top',
            'bottom' => 'Bottom',
            'left' => 'Left Side',
            'right' => 'Right Side',
            'edge' => 'Edge',
            'corner' => 'Corner',
            'center' => 'Center',
            'surface' => 'Surface',
            'interior' => 'Interior',
        ],
        'book' => [
            'cover_front' => 'Front Cover',
            'cover_back' => 'Back Cover',
            'spine' => 'Spine',
            'head' => 'Head',
            'tail' => 'Tail',
            'fore_edge' => 'Fore-edge',
            'gutter' => 'Gutter',
            'text_block' => 'Text Block',
            'endpaper' => 'Endpaper',
            'flyleaf' => 'Flyleaf',
        ],
        'painting' => [
            'paint_layer' => 'Paint Layer',
            'ground' => 'Ground Layer',
            'support' => 'Support',
            'frame' => 'Frame',
            'stretcher' => 'Stretcher',
            'varnish' => 'Varnish Layer',
        ],
    ];

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('condition_vocabulary');
            $logPath = '/var/log/atom/condition_vocabulary.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_writable($logDir)) {
                self::$logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
            }
        }
        return self::$logger;
    }

    /**
     * Get all damage types as flat list for dropdowns
     */
    public static function getDamageTypesList(): array
    {
        $list = [];

        foreach (self::DAMAGE_TYPES as $categoryKey => $category) {
            foreach ($category['terms'] as $termKey => $term) {
                $list[$termKey] = [
                    'key' => $termKey,
                    'label' => $term['label'],
                    'category' => $categoryKey,
                    'category_label' => $category['label'],
                    'severity_default' => $term['severity_default'],
                    'color' => $term['color'],
                ];
            }
        }

        return $list;
    }

    /**
     * Get damage types grouped by category
     */
    public static function getDamageTypesGrouped(): array
    {
        return self::DAMAGE_TYPES;
    }

    /**
     * Get severity levels
     */
    public static function getSeverityLevels(): array
    {
        return self::SEVERITY_LEVELS;
    }

    /**
     * Get material types as flat list
     */
    public static function getMaterialsList(): array
    {
        $list = [];

        foreach (self::MATERIAL_TYPES as $categoryKey => $category) {
            foreach ($category['terms'] as $termKey => $term) {
                $list[$termKey] = [
                    'key' => $termKey,
                    'label' => $term,
                    'category' => $categoryKey,
                    'category_label' => $category['label'],
                ];
            }
        }

        return $list;
    }

    /**
     * Get location zones
     */
    public static function getLocationZones(?string $objectType = 'general'): array
    {
        return self::LOCATION_ZONES[$objectType] ?? self::LOCATION_ZONES['general'];
    }

    /**
     * Get custom vocabulary terms from database
     */
    public static function getCustomTerms(string $vocabularyType): array
    {
        try {
            return DB::table('condition_vocabulary_term')
                ->where('vocabulary_type', $vocabularyType)
                ->where('active', 1)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->toArray();

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Add custom vocabulary term
     */
    public static function addCustomTerm(
        string $vocabularyType,
        string $key,
        string $label,
        ?string $category = null,
        ?array $metadata = null,
        int $createdBy = 0
    ): ?int {
        try {
            return DB::table('condition_vocabulary_term')->insertGetId([
                'vocabulary_type' => $vocabularyType,
                'term_key' => $key,
                'label' => $label,
                'category' => $category,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'active' => 1,
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to add custom term', [
                'vocabulary_type' => $vocabularyType,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get vocabulary for JSON export (for annotation tools)
     */
    public static function getVocabularyForJs(): array
    {
        return [
            'damageTypes' => self::getDamageTypesList(),
            'severityLevels' => self::getSeverityLevels(),
            'materials' => self::getMaterialsList(),
            'locationZones' => self::LOCATION_ZONES,
        ];
    }

    /**
     * Validate term against vocabulary
     */
    public static function validateTerm(string $vocabularyType, string $term): bool
    {
        switch ($vocabularyType) {
            case 'damage_type':
                $list = self::getDamageTypesList();
                return isset($list[$term]);

            case 'severity':
                return isset(self::SEVERITY_LEVELS[$term]);

            case 'material':
                $list = self::getMaterialsList();
                return isset($list[$term]);

            default:
                // Check custom terms
                return DB::table('condition_vocabulary_term')
                    ->where('vocabulary_type', $vocabularyType)
                    ->where('term_key', $term)
                    ->where('active', 1)
                    ->exists();
        }
    }

    /**
     * Get term details
     */
    public static function getTermDetails(string $vocabularyType, string $term): ?array
    {
        switch ($vocabularyType) {
            case 'damage_type':
                $list = self::getDamageTypesList();
                return $list[$term] ?? null;

            case 'severity':
                return self::SEVERITY_LEVELS[$term] ?? null;

            case 'material':
                $list = self::getMaterialsList();
                return $list[$term] ?? null;

            default:
                $custom = DB::table('condition_vocabulary_term')
                    ->where('vocabulary_type', $vocabularyType)
                    ->where('term_key', $term)
                    ->where('active', 1)
                    ->first();

                if ($custom) {
                    return [
                        'key' => $custom->term_key,
                        'label' => $custom->label,
                        'category' => $custom->category,
                        'metadata' => json_decode($custom->metadata ?? '{}', true),
                    ];
                }

                return null;
        }
    }
}
