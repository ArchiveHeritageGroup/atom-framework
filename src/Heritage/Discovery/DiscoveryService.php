<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Discovery;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Discovery Service.
 *
 * Provides data for the enhanced heritage landing page including
 * explore categories, timeline navigation, hero carousel, and
 * featured collections.
 */
class DiscoveryService
{
    private string $culture = 'en';

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;

        return $this;
    }

    // =========================================================================
    // HERO SLIDES
    // =========================================================================

    /**
     * Get active hero slides for carousel.
     */
    public function getHeroSlides(?int $institutionId = null): array
    {
        $today = date('Y-m-d');

        return DB::table('heritage_hero_slide')
            ->where('is_enabled', 1)
            ->where(function ($q) use ($institutionId) {
                $q->whereNull('institution_id')
                    ->orWhere('institution_id', $institutionId);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->orderBy('display_order')
            ->get()
            ->map(fn ($slide) => [
                'id' => $slide->id,
                'title' => $slide->title,
                'subtitle' => $slide->subtitle,
                'description' => $slide->description,
                'image_path' => $slide->image_path,
                'image_alt' => $slide->image_alt ?? $slide->title,
                'video_url' => $slide->video_url,
                'media_type' => $slide->media_type ?? 'image',
                'overlay_type' => $slide->overlay_type ?? 'gradient',
                'overlay_color' => $slide->overlay_color ?? '#000000',
                'overlay_opacity' => (float) ($slide->overlay_opacity ?? 0.5),
                'text_position' => $slide->text_position ?? 'left',
                'ken_burns' => (bool) ($slide->ken_burns ?? true),
                'cta_text' => $slide->cta_text,
                'cta_url' => $slide->cta_url,
                'cta_style' => $slide->cta_style ?? 'primary',
                'source_collection' => $slide->source_collection,
                'photographer_credit' => $slide->photographer_credit,
                'display_duration' => $slide->display_duration ?? 8,
            ])
            ->toArray();
    }

    // =========================================================================
    // FEATURED COLLECTIONS
    // =========================================================================

    /**
     * Get featured collections for landing page.
     */
    public function getFeaturedCollections(?int $institutionId = null, int $limit = 6): array
    {
        $today = date('Y-m-d');

        return DB::table('heritage_featured_collection')
            ->where('is_enabled', 1)
            ->where('show_on_landing', 1)
            ->where(function ($q) use ($institutionId) {
                $q->whereNull('institution_id')
                    ->orWhere('institution_id', $institutionId);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'subtitle' => $c->subtitle,
                'description' => $c->description,
                'curator_note' => $c->curator_note,
                'cover_image' => $c->cover_image,
                'thumbnail_image' => $c->thumbnail_image,
                'background_color' => $c->background_color,
                'text_color' => $c->text_color ?? '#ffffff',
                'link_type' => $c->link_type,
                'link_reference' => $c->link_reference,
                'item_count' => $c->item_count ?? 0,
                'image_count' => $c->image_count ?? 0,
                'display_size' => $c->display_size ?? 'medium',
                'is_featured' => (bool) $c->is_featured,
            ])
            ->toArray();
    }

    /**
     * Get a single featured collection by ID.
     */
    public function getFeaturedCollection(int $id): ?array
    {
        $collection = DB::table('heritage_featured_collection')
            ->where('id', $id)
            ->where('is_enabled', 1)
            ->first();

        if (!$collection) {
            return null;
        }

        return [
            'id' => $collection->id,
            'title' => $collection->title,
            'subtitle' => $collection->subtitle,
            'description' => $collection->description,
            'curator_note' => $collection->curator_note,
            'cover_image' => $collection->cover_image,
            'link_type' => $collection->link_type,
            'link_reference' => $collection->link_reference,
            'search_query' => json_decode($collection->search_query ?? '{}', true),
            'item_count' => $collection->item_count ?? 0,
        ];
    }

    // =========================================================================
    // EXPLORE CATEGORIES
    // =========================================================================

    /**
     * Get explore categories for landing page.
     */
    public function getExploreCategories(?int $institutionId = null): array
    {
        return DB::table('heritage_explore_category')
            ->where('is_enabled', 1)
            ->where('show_on_landing', 1)
            ->where(function ($q) use ($institutionId) {
                $q->whereNull('institution_id')
                    ->orWhere('institution_id', $institutionId);
            })
            ->orderBy('display_order')
            ->get()
            ->map(fn ($cat) => [
                'code' => $cat->code,
                'name' => $cat->name,
                'description' => $cat->description,
                'tagline' => $cat->tagline,
                'icon' => $cat->icon ?? 'bi-grid',
                'cover_image' => $cat->cover_image,
                'background_color' => $cat->background_color ?? '#0d6efd',
                'text_color' => $cat->text_color ?? '#ffffff',
                'display_style' => $cat->display_style ?? 'grid',
                'landing_items' => $cat->landing_items ?? 6,
            ])
            ->toArray();
    }

    /**
     * Get a single explore category by code.
     */
    public function getExploreCategory(string $code, ?int $institutionId = null): ?array
    {
        $cat = DB::table('heritage_explore_category')
            ->where('code', $code)
            ->where('is_enabled', 1)
            ->where(function ($q) use ($institutionId) {
                $q->whereNull('institution_id')
                    ->orWhere('institution_id', $institutionId);
            })
            ->first();

        if (!$cat) {
            return null;
        }

        return [
            'code' => $cat->code,
            'name' => $cat->name,
            'description' => $cat->description,
            'tagline' => $cat->tagline,
            'icon' => $cat->icon,
            'cover_image' => $cat->cover_image,
            'source_type' => $cat->source_type,
            'source_reference' => $cat->source_reference,
            'display_style' => $cat->display_style,
            'items_per_page' => $cat->items_per_page ?? 24,
            'show_counts' => (bool) $cat->show_counts,
            'show_thumbnails' => (bool) $cat->show_thumbnails,
        ];
    }

    /**
     * Get items for an explore category.
     */
    public function getExploreCategoryItems(string $code, ?int $institutionId = null, int $limit = 24, int $offset = 0): array
    {
        $category = $this->getExploreCategory($code, $institutionId);

        if (!$category) {
            return ['items' => [], 'total' => 0];
        }

        switch ($category['source_type']) {
            case 'taxonomy':
                return $this->getTaxonomyItems($category['source_reference'], $limit, $offset);

            case 'authority':
                return $this->getAuthorityItems($category['source_reference'], $limit, $offset);

            case 'custom':
                if ($code === 'trending') {
                    return $this->getTrendingItems($institutionId, $limit);
                }

                return ['items' => [], 'total' => 0];

            default:
                return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Get taxonomy-based items (subjects, content types, etc.).
     */
    private function getTaxonomyItems(string $taxonomyCode, int $limit, int $offset): array
    {
        // Map taxonomy code to AtoM taxonomy ID
        $taxonomyIds = [
            'subject' => 35,
            'contentType' => 79,
            'glamSector' => 450,
        ];

        $taxonomyId = $taxonomyIds[$taxonomyCode] ?? null;

        if (!$taxonomyId) {
            return ['items' => [], 'total' => 0];
        }

        $culture = $this->culture;

        // Get terms with item counts
        $query = DB::table('term as t')
            ->join('term_i18n as ti', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->leftJoin('object_term_relation as otr', 't.id', '=', 'otr.term_id')
            ->where('t.taxonomy_id', $taxonomyId)
            ->groupBy('t.id', 'ti.name')
            ->select(
                't.id',
                'ti.name',
                DB::raw('COUNT(DISTINCT otr.object_id) as item_count')
            )
            ->having('item_count', '>', 0)
            ->orderByDesc('item_count');

        $total = $query->count();

        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($term) => [
                'id' => $term->id,
                'name' => $term->name,
                'count' => $term->item_count,
                'slug' => $this->slugify($term->name),
            ])
            ->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get authority-based items (creators, places).
     */
    private function getAuthorityItems(string $authorityType, int $limit, int $offset): array
    {
        $culture = $this->culture;

        // Places are stored in taxonomy ID 42 in AtoM, not in actor table
        if ($authorityType === 'place') {
            return $this->getPlaceItems($limit, $offset);
        }

        // For actor-based authorities (creators, people, organizations)
        $query = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->join('slug as s', 'a.id', '=', 's.object_id')
            ->leftJoin('event as e', 'a.id', '=', 'e.actor_id')
            ->whereNotNull('ai.authorized_form_of_name')
            ->groupBy('a.id', 'ai.authorized_form_of_name', 's.slug')
            ->select(
                'a.id',
                'ai.authorized_form_of_name as name',
                's.slug',
                DB::raw('COUNT(DISTINCT e.object_id) as item_count')
            )
            ->having('item_count', '>', 0)
            ->orderByDesc('item_count');

        $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($actor) => [
                'id' => $actor->id,
                'name' => $actor->name,
                'slug' => $actor->slug,
                'count' => $actor->item_count,
            ])
            ->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get place items from Places taxonomy (ID 42) and place access points.
     */
    private function getPlaceItems(int $limit, int $offset): array
    {
        $culture = $this->culture;
        $placesTaxonomyId = 42; // AtoM Places taxonomy

        // Query places from taxonomy terms linked via object_term_relation
        $query = DB::table('term as t')
            ->join('term_i18n as ti', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->leftJoin('object_term_relation as otr', 't.id', '=', 'otr.term_id')
            ->where('t.taxonomy_id', $placesTaxonomyId)
            ->whereNotNull('ti.name')
            ->where('ti.name', '!=', '')
            ->groupBy('t.id', 'ti.name')
            ->select(
                't.id',
                'ti.name',
                DB::raw('COUNT(DISTINCT otr.object_id) as item_count')
            )
            ->having('item_count', '>', 0)
            ->orderByDesc('item_count');

        // Get total count - select only t.id to avoid duplicate column issue
        $countQuery = DB::table('term as t')
            ->join('term_i18n as ti', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->leftJoin('object_term_relation as otr', 't.id', '=', 'otr.term_id')
            ->where('t.taxonomy_id', $placesTaxonomyId)
            ->whereNotNull('ti.name')
            ->where('ti.name', '!=', '')
            ->select('t.id')
            ->groupBy('t.id')
            ->havingRaw('COUNT(DISTINCT otr.object_id) > 0');

        $total = DB::table(DB::raw("({$countQuery->toSql()}) as sub"))
            ->mergeBindings($countQuery)
            ->count();

        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($place) => [
                'id' => $place->id,
                'name' => $place->name,
                'count' => $place->item_count,
                'slug' => $this->slugify($place->name),
            ])
            ->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get trending/popular items.
     */
    private function getTrendingItems(?int $institutionId, int $limit): array
    {
        $culture = $this->culture;
        $oneWeekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        // Try to use discovery log for view counts
        if (DB::getSchemaBuilder()->hasTable('heritage_discovery_click')) {
            $query = DB::table('heritage_discovery_click as hdc')
                ->join('information_object as io', 'hdc.item_id', '=', 'io.id')
                ->join('information_object_i18n as ioi', function ($join) use ($culture) {
                    $join->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $culture);
                })
                ->join('slug as s', 'io.id', '=', 's.object_id')
                ->where('hdc.created_at', '>=', $oneWeekAgo)
                ->groupBy('io.id', 'ioi.title', 's.slug')
                ->select(
                    'io.id',
                    'ioi.title',
                    's.slug',
                    DB::raw('COUNT(*) as view_count')
                )
                ->orderByDesc('view_count')
                ->limit($limit);

            $items = $query->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title ?? 'Untitled',
                    'slug' => $item->slug,
                    'view_count' => $item->view_count,
                    'thumbnail' => $this->getItemThumbnail($item->id),
                ])
                ->toArray();

            return ['items' => $items, 'total' => count($items)];
        }

        // Fallback to recent additions
        return $this->getRecentItems($institutionId, $limit);
    }

    /**
     * Get recent items as fallback for trending.
     */
    private function getRecentItems(?int $institutionId, int $limit): array
    {
        $culture = $this->culture;

        $items = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->join('status as pub_status', function ($join) {
                $join->on('io.id', '=', 'pub_status.object_id')
                    ->where('pub_status.type_id', '=', 158);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->where('pub_status.status_id', 160)
            ->whereNotNull('io.parent_id')
            ->when($institutionId, fn ($q) => $q->where('io.repository_id', $institutionId))
            ->orderByDesc('o.created_at')
            ->limit($limit)
            ->select('io.id', 'ioi.title', 's.slug')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title ?? 'Untitled',
                'slug' => $item->slug,
                'thumbnail' => $this->getItemThumbnail($item->id),
            ])
            ->toArray();

        return ['items' => $items, 'total' => count($items)];
    }

    // =========================================================================
    // TIMELINE
    // =========================================================================

    /**
     * Get timeline periods for navigation.
     */
    public function getTimelinePeriods(?int $institutionId = null): array
    {
        return DB::table('heritage_timeline_period')
            ->where('is_enabled', 1)
            ->where(function ($q) use ($institutionId) {
                $q->whereNull('institution_id')
                    ->orWhere('institution_id', $institutionId);
            })
            ->orderBy('display_order')
            ->orderBy('start_year')
            ->get()
            ->map(fn ($period) => [
                'id' => $period->id,
                'name' => $period->name,
                'short_name' => $period->short_name ?? $period->name,
                'description' => $period->description,
                'start_year' => $period->start_year,
                'end_year' => $period->end_year,
                'circa' => (bool) $period->circa,
                'cover_image' => $period->cover_image,
                'thumbnail_image' => $period->thumbnail_image,
                'background_color' => $period->background_color,
                'item_count' => $period->item_count ?? 0,
                'year_label' => $this->formatYearRange($period->start_year, $period->end_year, (bool) $period->circa),
            ])
            ->toArray();
    }

    /**
     * Get items for a timeline period.
     */
    public function getTimelinePeriodItems(int $periodId, ?int $institutionId = null, int $limit = 24, int $offset = 0): array
    {
        $period = DB::table('heritage_timeline_period')
            ->where('id', $periodId)
            ->where('is_enabled', 1)
            ->first();

        if (!$period) {
            return ['items' => [], 'total' => 0, 'period' => null];
        }

        $culture = $this->culture;

        // Build date range search
        // AtoM stores dates in various formats, so we search for years
        $startYear = $period->start_year;
        $endYear = $period->end_year ?? date('Y');

        $query = DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->join('status as pub_status', function ($join) {
                $join->on('io.id', '=', 'pub_status.object_id')
                    ->where('pub_status.type_id', '=', 158);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('event as e', function ($join) {
                $join->on('io.id', '=', 'e.object_id')
                    ->whereIn('e.type_id', [111, 112, 113, 114]); // Creation, Accumulation, etc.
            })
            ->where('pub_status.status_id', 160)
            ->whereNotNull('io.parent_id')
            ->where(function ($q) use ($startYear, $endYear) {
                // Match start_date or end_date year within period
                $q->whereRaw('YEAR(e.start_date) BETWEEN ? AND ?', [$startYear, $endYear])
                    ->orWhereRaw('YEAR(e.end_date) BETWEEN ? AND ?', [$startYear, $endYear]);
            })
            ->when($institutionId, fn ($q) => $q->where('io.repository_id', $institutionId))
            ->groupBy('io.id', 'ioi.title', 's.slug')
            ->select('io.id', 'ioi.title', 's.slug');

        $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title ?? 'Untitled',
                'slug' => $item->slug,
                'thumbnail' => $this->getItemThumbnail($item->id),
            ])
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'description' => $period->description,
                'year_label' => $this->formatYearRange($period->start_year, $period->end_year, (bool) $period->circa),
            ],
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get thumbnail URL for an item.
     */
    private function getItemThumbnail(int $objectId): ?string
    {
        $do = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->select('path', 'name')
            ->first();

        if (!$do || !$do->path || !$do->name) {
            return null;
        }

        $path = rtrim($do->path, '/');
        $basename = pathinfo($do->name, PATHINFO_FILENAME);

        return $path . '/' . $basename . '_142.jpg';
    }

    /**
     * Format year range for display.
     */
    private function formatYearRange(int $start, ?int $end, bool $circa): string
    {
        $prefix = $circa ? 'c. ' : '';

        if ($start < 0) {
            $startStr = abs($start) . ' BCE';
        } else {
            $startStr = (string) $start;
        }

        if ($end === null) {
            return $prefix . $startStr . ' - Present';
        }

        if ($end < 0) {
            $endStr = abs($end) . ' BCE';
        } else {
            $endStr = (string) $end;
        }

        return $prefix . $startStr . ' - ' . $endStr;
    }

    /**
     * Create URL-friendly slug from text.
     */
    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);

        return trim($text, '-');
    }

    // =========================================================================
    // ADMIN METHODS
    // =========================================================================

    /**
     * Save a hero slide.
     */
    public function saveHeroSlide(array $data): int
    {
        return DB::table('heritage_hero_slide')->insertGetId([
            'institution_id' => $data['institution_id'] ?? null,
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'image_path' => $data['image_path'],
            'image_alt' => $data['image_alt'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'media_type' => $data['media_type'] ?? 'image',
            'overlay_type' => $data['overlay_type'] ?? 'gradient',
            'overlay_color' => $data['overlay_color'] ?? '#000000',
            'overlay_opacity' => $data['overlay_opacity'] ?? 0.5,
            'text_position' => $data['text_position'] ?? 'left',
            'ken_burns' => $data['ken_burns'] ?? true,
            'cta_text' => $data['cta_text'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
            'cta_style' => $data['cta_style'] ?? 'primary',
            'source_item_id' => $data['source_item_id'] ?? null,
            'source_collection' => $data['source_collection'] ?? null,
            'photographer_credit' => $data['photographer_credit'] ?? null,
            'display_order' => $data['display_order'] ?? 100,
            'display_duration' => $data['display_duration'] ?? 8,
            'is_enabled' => $data['is_enabled'] ?? true,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update a hero slide.
     */
    public function updateHeroSlide(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_hero_slide')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete a hero slide.
     */
    public function deleteHeroSlide(int $id): bool
    {
        return DB::table('heritage_hero_slide')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Save a featured collection.
     */
    public function saveFeaturedCollection(array $data): int
    {
        return DB::table('heritage_featured_collection')->insertGetId([
            'institution_id' => $data['institution_id'] ?? null,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'curator_note' => $data['curator_note'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'thumbnail_image' => $data['thumbnail_image'] ?? null,
            'background_color' => $data['background_color'] ?? null,
            'text_color' => $data['text_color'] ?? '#ffffff',
            'link_type' => $data['link_type'] ?? 'search',
            'link_reference' => $data['link_reference'] ?? null,
            'collection_id' => $data['collection_id'] ?? null,
            'repository_id' => $data['repository_id'] ?? null,
            'search_query' => isset($data['search_query']) ? json_encode($data['search_query']) : null,
            'item_count' => $data['item_count'] ?? 0,
            'image_count' => $data['image_count'] ?? 0,
            'display_size' => $data['display_size'] ?? 'medium',
            'display_order' => $data['display_order'] ?? 100,
            'show_on_landing' => $data['show_on_landing'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
            'is_enabled' => $data['is_enabled'] ?? true,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update a featured collection.
     */
    public function updateFeaturedCollection(int $id, array $data): bool
    {
        if (isset($data['search_query']) && is_array($data['search_query'])) {
            $data['search_query'] = json_encode($data['search_query']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_featured_collection')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete a featured collection.
     */
    public function deleteFeaturedCollection(int $id): bool
    {
        return DB::table('heritage_featured_collection')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Update item counts for a featured collection.
     */
    public function updateCollectionCounts(int $id): bool
    {
        $collection = DB::table('heritage_featured_collection')
            ->where('id', $id)
            ->first();

        if (!$collection) {
            return false;
        }

        $counts = ['item_count' => 0, 'image_count' => 0];

        if ($collection->link_type === 'collection' && $collection->collection_id) {
            // Count items in collection
            $counts['item_count'] = DB::table('information_object')
                ->where('parent_id', $collection->collection_id)
                ->count();

            $counts['image_count'] = DB::table('digital_object as do')
                ->join('information_object as io', 'do.object_id', '=', 'io.id')
                ->where('io.parent_id', $collection->collection_id)
                ->where('do.mime_type', 'LIKE', 'image/%')
                ->count();
        }

        return $this->updateFeaturedCollection($id, $counts);
    }
}
