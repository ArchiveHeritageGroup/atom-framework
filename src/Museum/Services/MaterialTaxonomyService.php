<?php

namespace AtomFramework\Museum\Services;

use AtomFramework\Museum\Contracts\MaterialTaxonomyInterface;
use Psr\Log\LoggerInterface;

class MaterialTaxonomyService implements MaterialTaxonomyInterface
{
    private LoggerInterface $logger;
    private array $materials = [];
    private array $techniques = [];
    private array $materialCategories = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->initializeTaxonomies();
    }

    public function getAllMaterials(): array
    {
        return $this->materials;
    }

    public function getMaterialsByCategory(string $category): array
    {
        if (!isset($this->materialCategories[$category])) {
            $this->logger->warning('Unknown material category requested', [
                'category' => $category,
            ]);

            return [];
        }

        return $this->materialCategories[$category];
    }

    public function isValidMaterial(string $material): bool
    {
        $normalized = $this->normalizeTerm($material);

        return in_array($normalized, array_map([$this, 'normalizeTerm'], $this->materials));
    }

    public function getAllTechniques(): array
    {
        return $this->techniques;
    }

    public function isValidTechnique(string $technique): bool
    {
        $normalized = $this->normalizeTerm($technique);

        return in_array($normalized, array_map([$this, 'normalizeTerm'], $this->techniques));
    }

    /**
     * Get all material categories.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return array_keys($this->materialCategories);
    }

    /**
     * Find materials matching search term.
     *
     * @param string $search
     * @param int    $limit
     *
     * @return array
     */
    public function searchMaterials(string $search, int $limit = 10): array
    {
        $search = strtolower($search);
        $matches = [];

        foreach ($this->materials as $material) {
            if (strpos(strtolower($material), $search) !== false) {
                $matches[] = $material;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Find techniques matching search term.
     *
     * @param string $search
     * @param int    $limit
     *
     * @return array
     */
    public function searchTechniques(string $search, int $limit = 10): array
    {
        $search = strtolower($search);
        $matches = [];

        foreach ($this->techniques as $technique) {
            if (strpos(strtolower($technique), $search) !== false) {
                $matches[] = $technique;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Suggest category for a material.
     *
     * @param string $material
     *
     * @return string|null
     */
    public function suggestCategory(string $material): ?string
    {
        $normalized = $this->normalizeTerm($material);

        foreach ($this->materialCategories as $category => $materials) {
            $normalizedMaterials = array_map([$this, 'normalizeTerm'], $materials);
            if (in_array($normalized, $normalizedMaterials)) {
                return $category;
            }
        }

        return null;
    }

    private function normalizeTerm(string $term): string
    {
        return strtolower(trim($term));
    }

    private function initializeTaxonomies(): void
    {
        $this->initializeMaterials();
        $this->initializeTechniques();
    }

    private function initializeMaterials(): void
    {
        // Metals
        $this->materialCategories['metal'] = [
            'aluminum',
            'brass',
            'bronze',
            'copper',
            'gold',
            'iron',
            'lead',
            'pewter',
            'platinum',
            'silver',
            'steel',
            'tin',
            'zinc',
            'alloy',
            'metal leaf',
            'gilt',
        ];

        // Stone
        $this->materialCategories['stone'] = [
            'alabaster',
            'basalt',
            'granite',
            'limestone',
            'marble',
            'sandstone',
            'slate',
            'soapstone',
            'travertine',
            'jade',
            'lapis lazuli',
            'onyx',
            'quartz',
        ];

        // Wood
        $this->materialCategories['wood'] = [
            'oak',
            'pine',
            'mahogany',
            'walnut',
            'cedar',
            'birch',
            'maple',
            'teak',
            'ebony',
            'rosewood',
            'bamboo',
            'plywood',
            'veneer',
        ];

        // Ceramics & Glass
        $this->materialCategories['ceramic'] = [
            'ceramic',
            'porcelain',
            'stoneware',
            'earthenware',
            'terracotta',
            'faience',
            'majolica',
            'pottery',
            'clay',
            'fired clay',
        ];

        $this->materialCategories['glass'] = [
            'glass',
            'crystal',
            'stained glass',
            'fused glass',
            'blown glass',
            'pressed glass',
        ];

        // Textiles & Fibers
        $this->materialCategories['textile'] = [
            'cotton',
            'linen',
            'silk',
            'wool',
            'hemp',
            'jute',
            'canvas',
            'velvet',
            'satin',
            'brocade',
            'damask',
            'felt',
            'lace',
            'embroidery',
        ];

        $this->materialCategories['synthetic_fiber'] = [
            'nylon',
            'polyester',
            'acrylic',
            'rayon',
            'spandex',
        ];

        // Paper & Related
        $this->materialCategories['paper'] = [
            'paper',
            'cardboard',
            'parchment',
            'vellum',
            'papyrus',
            'rice paper',
            'handmade paper',
        ];

        // Plastics & Synthetics
        $this->materialCategories['plastic'] = [
            'plastic',
            'acrylic',
            'bakelite',
            'celluloid',
            'PVC',
            'polyethylene',
            'polystyrene',
            'resin',
            'fiberglass',
        ];

        // Natural Materials
        $this->materialCategories['organic'] = [
            'leather',
            'parchment',
            'ivory',
            'bone',
            'horn',
            'shell',
            'tortoiseshell',
            'feather',
            'fur',
            'hair',
            'skin',
            'hide',
        ];

        $this->materialCategories['plant'] = [
            'straw',
            'reed',
            'rattan',
            'wicker',
            'cane',
            'raffia',
            'grass',
            'leaves',
            'bark',
        ];

        // Pigments & Media
        $this->materialCategories['pigment'] = [
            'oil paint',
            'acrylic paint',
            'watercolor',
            'tempera',
            'gouache',
            'encaustic',
            'fresco',
            'ink',
            'charcoal',
            'graphite',
            'pastel',
            'crayon',
            'dye',
        ];

        // Adhesives & Binders
        $this->materialCategories['adhesive'] = [
            'glue',
            'adhesive',
            'gesso',
            'size',
            'varnish',
            'lacquer',
            'resin',
        ];

        // Precious Materials
        $this->materialCategories['precious'] = [
            'diamond',
            'ruby',
            'sapphire',
            'emerald',
            'pearl',
            'amber',
            'coral',
            'mother-of-pearl',
        ];

        // Flatten all materials into single array
        $this->materials = [];
        foreach ($this->materialCategories as $materials) {
            $this->materials = array_merge($this->materials, $materials);
        }
        $this->materials = array_unique($this->materials);
        sort($this->materials);
    }

    private function initializeTechniques(): void
    {
        $this->techniques = [
            // Sculpture & 3D Techniques
            'carved',
            'cast',
            'modeled',
            'molded',
            'assembled',
            'constructed',
            'welded',
            'forged',
            'hammered',
            'chased',
            'repoussé',
            'engraved',
            'etched',
            'incised',
            'relief',
            'intaglio',

            // Painting & Drawing Techniques
            'painted',
            'drawn',
            'sketched',
            'brushed',
            'stippled',
            'glazed',
            'impasto',
            'scumbled',
            'washed',
            'sgraffito',
            'underpainting',

            // Printmaking Techniques
            'printed',
            'lithograph',
            'etching',
            'engraving',
            'woodcut',
            'linocut',
            'screen print',
            'serigraph',
            'monotype',
            'collagraph',
            'aquatint',
            'mezzotint',
            'drypoint',

            // Photography & Digital
            'photographed',
            'digital',
            'collage',
            'montage',
            'photomontage',
            'cyanotype',
            'daguerreotype',
            'tintype',
            'gelatin silver print',

            // Textile Techniques
            'woven',
            'embroidered',
            'appliquéd',
            'quilted',
            'knitted',
            'crocheted',
            'felted',
            'dyed',
            'batik',
            'tie-dyed',
            'block printed',
            'screen printed',
            'needlepoint',
            'tapestry',

            // Ceramic Techniques
            'thrown',
            'hand-built',
            'coiled',
            'slab-built',
            'press-molded',
            'slip cast',
            'glazed',
            'fired',
            'raku',
            'reduction fired',
            'oxidation fired',

            // Glass Techniques
            'blown',
            'cast',
            'fused',
            'slumped',
            'cut',
            'engraved',
            'etched',
            'sandblasted',
            'lampworked',

            // Metalwork Techniques
            'soldered',
            'riveted',
            'enameled',
            'damascened',
            'niello',
            'gilded',
            'plated',
            'patinated',
            'oxidized',

            // Wood Techniques
            'joined',
            'dovetailed',
            'mortise and tenon',
            'turned',
            'bent',
            'laminated',
            'inlaid',
            'marquetry',
            'intarsia',
            'veneered',
            'carved',

            // Mixed & Contemporary
            'assembled',
            'collaged',
            'layered',
            'transferred',
            'mixed media',
            'installation',
            'performance',
            'video',
            'digital manipulation',
            '3D printed',
            'laser cut',
            'CNC routed',
        ];

        sort($this->techniques);
    }
}
