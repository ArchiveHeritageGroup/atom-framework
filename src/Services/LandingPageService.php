<?php

namespace AtomExtensions\Services;

use AtomExtensions\Repositories\LandingPageRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Landing Page Service
 * 
 * Business logic for landing page builder
 */
class LandingPageService
{
    protected LandingPageRepository $repository;

    public function __construct()
    {
        $this->repository = new LandingPageRepository();
    }

    // =========================================================================
    // PAGE MANAGEMENT
    // =========================================================================

    /**
     * Get landing page for display
     */
    public function getLandingPageForDisplay(?string $slug = null): ?array
    {
        $page = $slug 
            ? $this->repository->getPageBySlug($slug)
            : $this->repository->getDefaultPage();

        if (!$page) {
            return null;
        }

        $blocks = $this->repository->getPageBlocks($page->id, true);
        
        // Enrich blocks with dynamic data
        $enrichedBlocks = $blocks->map(function ($block) {
            return $this->enrichBlockData($block);
        });

        return [
            'page' => $page,
            'blocks' => $enrichedBlocks
        ];
    }

    /**
     * Get page for editing
     */
    public function getPageForEditor(int $pageId): ?array
    {
        $page = $this->repository->getPageById($pageId);
        if (!$page) {
            return null;
        }

        return [
            'page' => $page,
            'blocks' => $this->repository->getPageBlocks($pageId),
            'blockTypes' => $this->repository->getAllBlockTypes(),
            'versions' => $this->repository->getPageVersions($pageId, 5)
        ];
    }

    /**
     * Create new page
     */
    public function createPage(array $data, ?int $userId = null): array
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        // Check slug uniqueness
        if ($this->repository->slugExists($data['slug'])) {
            return ['success' => false, 'error' => 'Slug already exists'];
        }

        $data['user_id'] = $userId;
        
        try {
            $pageId = $this->repository->createPage($data);
            $this->repository->logAudit('page_created', $pageId, null, $data, $userId);
            
            return ['success' => true, 'page_id' => $pageId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update page
     */
    public function updatePage(int $pageId, array $data, ?int $userId = null): array
    {
        // Check slug uniqueness if changing
        if (!empty($data['slug']) && $this->repository->slugExists($data['slug'], $pageId)) {
            return ['success' => false, 'error' => 'Slug already exists'];
        }

        $data['user_id'] = $userId;

        try {
            $this->repository->updatePage($pageId, $data);
            $this->repository->logAudit('page_updated', $pageId, null, $data, $userId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete page
     */
    public function deletePage(int $pageId, ?int $userId = null): array
    {
        $page = $this->repository->getPageById($pageId);
        if (!$page) {
            return ['success' => false, 'error' => 'Page not found'];
        }

        if ($page->is_default) {
            return ['success' => false, 'error' => 'Cannot delete default page'];
        }

        try {
            $this->repository->logAudit('page_deleted', $pageId, null, ['name' => $page->name], $userId);
            $this->repository->deletePage($pageId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // BLOCK MANAGEMENT
    // =========================================================================

    /**
     * Add block to page
     */
    public function addBlock(int $pageId, int $blockTypeId, ?array $config = null, ?int $userId = null, array $options = []): array
    {
        $blockType = $this->repository->getBlockTypeById($blockTypeId);
        if (!$blockType) {
            return ['success' => false, 'error' => 'Invalid block type'];
        }

        // Merge with default config
        $finalConfig = array_merge($blockType->default_config, $config ?? []);

        $blockData = [
            'page_id' => $pageId,
            'block_type_id' => $blockTypeId,
            'config' => $finalConfig
        ];

        // Add parent block info for nested blocks
        if (!empty($options['parent_block_id'])) {
            $blockData['parent_block_id'] = $options['parent_block_id'];
            $blockData['column_slot'] = $options['column_slot'] ?? null;
        }

        try {
            $blockId = $this->repository->createBlock($blockData);

            $this->repository->logAudit('block_added', $pageId, $blockId, 
                ['type' => $blockType->machine_name], $userId);

            return ['success' => true, 'block_id' => $blockId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update block configuration
     */
    public function updateBlock(int $blockId, array $data, ?int $userId = null): array
    {
        $block = $this->repository->getBlockById($blockId);
        if (!$block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        try {
            $this->repository->updateBlock($blockId, $data);
            $this->repository->logAudit('block_updated', $block->page_id, $blockId, $data, $userId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete block
     */
    public function deleteBlock(int $blockId, ?int $userId = null): array
    {
        $block = $this->repository->getBlockById($blockId);
        if (!$block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        try {
            $this->repository->logAudit('block_deleted', $block->page_id, $blockId, 
                ['type' => $block->machine_name], $userId);
            $this->repository->deleteBlock($blockId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reorder blocks
     */
    public function reorderBlocks(int $pageId, array $blockOrder, ?int $userId = null): array
    {
        try {
            $this->repository->reorderBlocks($pageId, $blockOrder);
            $this->repository->logAudit('blocks_reordered', $pageId, null, 
                ['order' => $blockOrder], $userId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Duplicate block
     */
    public function duplicateBlock(int $blockId, ?int $userId = null): array
    {
        $block = $this->repository->getBlockById($blockId);
        if (!$block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        try {
            $newBlockId = $this->repository->duplicateBlock($blockId);
            $this->repository->logAudit('block_duplicated', $block->page_id, $newBlockId, 
                ['source_block' => $blockId], $userId);
            
            return ['success' => true, 'block_id' => $newBlockId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Toggle block visibility
     */
    public function toggleBlockVisibility(int $blockId, ?int $userId = null): array
    {
        $block = $this->repository->getBlockById($blockId);
        if (!$block) {
            return ['success' => false, 'error' => 'Block not found'];
        }

        $newVisibility = $block->is_visible ? 0 : 1;

        try {
            $this->repository->updateBlock($blockId, ['is_visible' => $newVisibility]);
            $this->repository->logAudit('block_visibility_toggled', $block->page_id, $blockId, 
                ['visible' => $newVisibility], $userId);
            
            return ['success' => true, 'is_visible' => $newVisibility];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // VERSION MANAGEMENT
    // =========================================================================

    /**
     * Save draft
     */
    public function saveDraft(int $pageId, ?int $userId = null, ?string $notes = null): array
    {
        try {
            $versionId = $this->repository->createVersion($pageId, 'draft', $userId, $notes);
            return ['success' => true, 'version_id' => $versionId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Publish page
     */
    public function publish(int $pageId, ?int $userId = null): array
    {
        try {
            $versionId = $this->repository->createVersion($pageId, 'published', $userId);
            $this->repository->logAudit('page_published', $pageId, null, 
                ['version_id' => $versionId], $userId);
            
            return ['success' => true, 'version_id' => $versionId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Restore version
     */
    public function restoreVersion(int $versionId, ?int $userId = null): array
    {
        try {
            $this->repository->restoreVersion($versionId);
            
            $version = DB::table('atom_landing_page_version')
                ->where('id', $versionId)
                ->first();
            
            $this->repository->logAudit('version_restored', $version->page_id, null, 
                ['version_id' => $versionId, 'version_number' => $version->version_number], $userId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // BLOCK DATA ENRICHMENT
    // =========================================================================

    /**
     * Enrich block with dynamic data
     */
    protected function enrichBlockData(object $block): object
    {
        error_log("ENRICH DEBUG: block=" . $block->machine_name . " config=" . json_encode($block->config ?? []));
        switch ($block->machine_name) {
            case 'statistics':
                $block->computed_data = $this->getStatisticsData($block->config);
                break;

            case 'recent_items':
                $block->computed_data = $this->getRecentItemsData($block->config);
                break;

            case 'browse_panels':
                $block->computed_data = $this->getBrowsePanelsData($block->config);
                break;

            case 'holdings_list':
                error_log('ENRICH holdings_list - calling getHoldingsData with config: ' . json_encode($block->config));
                $block->computed_data = $this->getHoldingsData($block->config);
                break;

            case 'featured_items':
                $block->computed_data = $this->getFeaturedItemsData($block->config);
                break;

            case 'repository_spotlight':
                $block->computed_data = $this->getRepositorySpotlightData($block->config);
                break;

            case 'map_block':
                $block->computed_data = $this->getMapData($block->config);
                break;

            default:
                $block->computed_data = null;
        }

        // Load child blocks for column layouts
        $block = $this->loadChildBlocks($block);
        return $block;
    }

    /**
     * Get statistics data
     */
    protected function getStatisticsData(array $config): array
    {
        $stats = [];
        
        foreach ($config['stats'] ?? [] as $stat) {
            $count = $this->getEntityCount($stat['entity'] ?? '');
            $stats[] = [
                'label' => $stat['label'] ?? '',
                'icon' => $stat['icon'] ?? 'bi-archive',
                'count' => $count
            ];
        }

        return $stats;
    }

    /**
     * Get entity count
     */
    protected function getEntityCount(string $entity): int
    {
        switch ($entity) {
            case 'informationobject':
                return DB::table('information_object')
                    ->whereNull('parent_id')
                    ->count();

            case 'repository':
                return DB::table('repository')->count();

            case 'actor':
                return DB::table('actor')->count();

            case 'digitalobject':
                return DB::table('digital_object')->count();

            case 'accession':
                return DB::table('accession')->count();

            case 'function':
                return DB::table('function_object')->count();

            case 'term':
            case 'term_subjects':
                return DB::table('term')
                    ->join('taxonomy', 'term.taxonomy_id', '=', 'taxonomy.id')
                    ->where('taxonomy.id', 35) // Subjects taxonomy
                    ->count();

            case 'term_places':
                return DB::table('term')
                    ->join('taxonomy', 'term.taxonomy_id', '=', 'taxonomy.id')
                    ->where('taxonomy.id', 42) // Places taxonomy
                    ->count();

            default:
                return 0;
        }
    }

    /**
     * Get recent items data
     */
    protected function getRecentItemsData(array $config): array
    {
        $entityType = $config['entity_type'] ?? 'informationobject';
        $limit = min($config['limit'] ?? 6, 20);
        $culture = \sfContext::getInstance()->getUser()->getCulture() ?? 'en';

        switch ($entityType) {
            case 'informationobject':
                return DB::table('information_object as io')
                    ->join('object as obj', 'io.id', '=', 'obj.id')
                    ->join('information_object_i18n as i18n', function($join) use ($culture) {
                        $join->on('io.id', '=', 'i18n.id')
                             ->where('i18n.culture', '=', $culture);
                    })
                    ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
                    ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                    ->select([
                        'io.id',
                        'i18n.title',
                        'slug.slug',
                        'obj.created_at',
                        'do.id as has_digital_object'
                    ])
                    ->orderBy('obj.created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->toArray();

            case 'repository':
                return DB::table('repository as r')
                    ->join('object as obj', 'r.id', '=', 'obj.id')
                    ->join('actor_i18n as i18n', function($join) use ($culture) {
                        $join->on('r.id', '=', 'i18n.id')
                             ->where('i18n.culture', '=', $culture);
                    })
                    ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
                    ->select([
                        'r.id',
                        'i18n.authorized_form_of_name as title',
                        'slug.slug',
                        'obj.created_at'
                    ])
                    ->orderBy('obj.created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->toArray();

            default:
                return [];
        }
    }

    /**
     * Get browse panels data with counts
     */
    protected function getBrowsePanelsData(array $config): array
    {
        $panels = [];
        
        foreach ($config['panels'] ?? [] as $panel) {
            $count = $config['show_counts'] ?? true 
                ? $this->getEntityCount($panel['count_entity'] ?? '') 
                : null;
            
            $panels[] = [
                'title' => $panel['title'] ?? '',
                'icon' => $panel['icon'] ?? 'bi-archive',
                'url' => $panel['url'] ?? '#',
                'count' => $count
            ];
        }

        return $panels;
    }

    /**
     * Get holdings data
     */
    protected function getHoldingsData(array $config): array
    {
        $limit = min($config['limit'] ?? 10, 50);
        $culture = \sfContext::getInstance()->getUser()->getCulture() ?? 'en';
        $sort = $config['sort'] ?? 'title';

        // Popular this week - based on access_log hits
        if ($sort === 'hits') {
            return $this->getPopularThisWeek($limit, $culture);
        }

        // Standard holdings list
        $query = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('term_i18n as level', function($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'level.id')
                     ->where('level.culture', '=', $culture);
            })
            ->whereNull('io.parent_id')
            ->select([
                'io.id',
                'i18n.title',
                'slug.slug',
                'level.name as level_of_description',
                'i18n.extent_and_medium as extent'
            ])
            ->orderBy('i18n.title', 'asc')
            ->limit($limit);

        if (!empty($config['repository_id'])) {
            $query->where('io.repository_id', $config['repository_id']);
        }

        return $query->get()->toArray();
    }

    /**
     * Get popular items this week (based on access_log)
     */
    protected function getPopularThisWeek(int $limit, string $culture): array
    {
        $now = date('Y-m-d H:i:s');
        $draftStatusId = \QubitTerm::PUBLICATION_STATUS_DRAFT_ID;

        // Get popular object IDs with hit counts from access_log
        $popular = DB::table('access_log')
            ->leftJoin('status', 'access_log.object_id', '=', 'status.object_id')
            ->select('access_log.object_id', DB::raw('COUNT(access_log.object_id) as hits'))
            ->whereRaw('access_date BETWEEN DATE_SUB(?, INTERVAL 1 WEEK) AND ?', [$now, $now])
            ->where(function($query) use ($draftStatusId) {
                $query->where('status.status_id', '!=', $draftStatusId)
                      ->orWhereNull('status.status_id');
            })
            ->groupBy('access_log.object_id')
            ->orderBy('hits', 'desc')
            ->limit($limit)
            ->get();

        if ($popular->isEmpty()) {
            return [];
        }

        // Get object details for the popular items
        $objectIds = $popular->pluck('object_id')->toArray();
        $hitCounts = $popular->pluck('hits', 'object_id')->toArray();

        $items = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->whereIn('io.id', $objectIds)
            ->select([
                'io.id',
                'i18n.title',
                'slug.slug'
            ])
            ->get();

        // Add hit counts and sort by popularity
        $result = [];
        foreach ($items as $item) {
            $item->hits = $hitCounts[$item->id] ?? 0;
            $result[] = $item;
        }

        // Sort by hits descending
        usort($result, function($a, $b) {
            return $b->hits - $a->hits;
        });

        return $result;
    }

    /**
     * Get featured items data
     */
    protected function getFeaturedItemsData(array $config): array
    {
        $items = $config['items'] ?? [];
        if (empty($items)) {
            return [];
        }

        $culture = \sfContext::getInstance()->getUser()->getCulture() ?? 'en';
        
        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
            ->whereIn('io.id', $items)
            ->select([
                'io.id',
                'i18n.title',
                'i18n.scope_and_content as description',
                'slug.slug',
                'do.id as digital_object_id'
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get repository spotlight data
     */
    protected function getRepositorySpotlightData(array $config): ?array
    {
        if (empty($config['repository_id'])) {
            return null;
        }

        $culture = \sfContext::getInstance()->getUser()->getCulture() ?? 'en';

        $repository = DB::table('repository as r')
            ->join('actor_i18n as i18n', function($join) use ($culture) {
                $join->on('r.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->leftJoin('contact_information as ci', 'r.id', '=', 'ci.actor_id')
            ->where('r.id', $config['repository_id'])
            ->select([
                'r.id',
                'i18n.authorized_form_of_name as name',
                'i18n.history as description',
                'slug.slug',
                'ci.city',
                'ci.region'
            ])
            ->first();

        if (!$repository) {
            return null;
        }

        // Get holdings count
        $holdingsCount = DB::table('information_object')
            ->where('repository_id', $config['repository_id'])
            ->whereNull('parent_id')
            ->count();

        $repository->holdings_count = $holdingsCount;

        // Get sample holdings if configured
        if ($config['max_holdings'] ?? 0 > 0) {
            $repository->sample_holdings = DB::table('information_object as io')
                ->join('information_object_i18n as i18n', function($join) use ($culture) {
                    $join->on('io.id', '=', 'i18n.id')
                         ->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->where('io.repository_id', $config['repository_id'])
                ->whereNull('io.parent_id')
                ->select(['io.id', 'i18n.title', 'slug.slug'])
                ->limit($config['max_holdings'])
                ->get()
                ->toArray();
        }

        return (array)$repository;
    }

    /**
     * Get map data
     */
    protected function getMapData(array $config): array
    {
        $query = DB::table('repository as r')
            ->join('actor_i18n as i18n', function($join) {
                $join->on('r.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->join('contact_information as ci', 'r.id', '=', 'ci.actor_id')
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->whereNotNull('ci.latitude')
            ->whereNotNull('ci.longitude')
            ->select([
                'r.id',
                'i18n.authorized_form_of_name as name',
                'slug.slug',
                'ci.latitude',
                'ci.longitude',
                'ci.street_address',
                'ci.city'
            ]);

        if (!($config['show_all_repositories'] ?? true) && !empty($config['repository_ids'])) {
            $query->whereIn('r.id', $config['repository_ids']);
        }

        return $query->get()->toArray();
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    /**
     * Generate URL-safe slug
     */
    protected function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;
        while ($this->repository->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get all block types for palette
     */
    public function getBlockTypes(): Collection
    {
        return $this->repository->getAllBlockTypes();
    }

    /**
     * Get all pages for listing
     */
    public function getAllPages(bool $activeOnly = false): Collection
    {
        return $this->repository->getAllPages($activeOnly);
    }

    /**
     * Load child blocks for column layouts
     */
    protected function loadChildBlocks(object $block): object
    {
        if (in_array($block->machine_name, ['row_2_col', 'row_3_col'])) {
            $block->child_blocks = $this->repository->getChildBlocks($block->id);
            
            // Enrich child blocks with data
            $block->child_blocks = $block->child_blocks->map(function ($child) {
                return $this->enrichBlockData($child);
            });
        }
        return $block;
    }
}