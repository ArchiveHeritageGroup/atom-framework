<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Config;

use AtomFramework\Heritage\Repositories\LandingConfigRepository;
use AtomFramework\Heritage\Repositories\HeroImageRepository;
use AtomFramework\Heritage\Repositories\StoryRepository;
use AtomFramework\Heritage\Repositories\FilterRepository;
use AtomFramework\Heritage\Filters\FilterValueResolver;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Landing Config Service.
 *
 * Business logic for heritage landing page configuration.
 * Aggregates data from multiple sources for the landing page API.
 */
class LandingConfigService
{
    private LandingConfigRepository $configRepo;
    private HeroImageRepository $heroRepo;
    private StoryRepository $storyRepo;
    private FilterRepository $filterRepo;
    private FilterValueResolver $valueResolver;
    private string $culture = 'en';

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->configRepo = new LandingConfigRepository();
        $this->heroRepo = new HeroImageRepository();
        $this->storyRepo = new StoryRepository();
        $this->filterRepo = new FilterRepository();
        $this->valueResolver = new FilterValueResolver($culture);
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        $this->valueResolver->setCulture($culture);

        return $this;
    }

    /**
     * Get current culture.
     */
    public function getCulture(): string
    {
        return $this->culture;
    }

    /**
     * Get complete landing page data for public API.
     */
    public function getLandingPageData(?int $institutionId = null): array
    {
        $config = $this->configRepo->getConfig($institutionId);

        if (!$config) {
            // Return defaults if no config exists
            $config = $this->getDefaultConfig();
        }

        $data = [
            'config' => $this->formatConfig($config),
            'hero_images' => [],
            'filters' => [],
            'stories' => [],
            'recent_activity' => [],
            'recent_additions' => [],
            'stats' => [],
        ];

        // Get hero images (from heritage_hero_slide table)
        $data['hero_images'] = $this->heroRepo->getEnabledImages($institutionId)
            ->map(fn ($img) => [
                'id' => $img->id,
                'image_path' => $img->image_path,
                'title' => $img->title ?? null,
                'subtitle' => $img->subtitle ?? null,
                'description' => $img->description ?? null,
                'image_alt' => $img->image_alt ?? null,
                // Map old field names for backward compatibility
                'caption' => $img->title ?? $img->subtitle ?? null,
                'collection_name' => $img->source_collection ?? null,
                'link_url' => $img->cta_url ?? null,
                // New slide-specific fields
                'overlay_type' => $img->overlay_type ?? 'gradient',
                'overlay_color' => $img->overlay_color ?? '#000000',
                'overlay_opacity' => $img->overlay_opacity ?? 0.5,
                'text_position' => $img->text_position ?? 'left',
                'ken_burns' => $img->ken_burns ?? 1,
                'cta_text' => $img->cta_text ?? null,
                'cta_url' => $img->cta_url ?? null,
                'cta_style' => $img->cta_style ?? 'primary',
                'source_collection' => $img->source_collection ?? null,
                'photographer_credit' => $img->photographer_credit ?? null,
                'display_duration' => $img->display_duration ?? 8,
            ])
            ->toArray();

        // Get filters with values (if enabled)
        if ($config->show_filters ?? true) {
            $data['filters'] = $this->getFiltersWithValues($institutionId);
        }

        // Get featured stories (if enabled)
        if ($config->show_curated_stories ?? true) {
            $data['stories'] = $this->storyRepo->getFeaturedStories($institutionId, 3)
                ->map(fn ($story) => [
                    'id' => $story->id,
                    'title' => $story->title,
                    'subtitle' => $story->subtitle,
                    'description' => $story->description,
                    'cover_image' => $story->cover_image,
                    'story_type' => $story->story_type,
                    'link_type' => $story->link_type,
                    'link_reference' => $story->link_reference,
                    'item_count' => $story->item_count,
                ])
                ->toArray();
        }

        // Get community activity (if enabled)
        if ($config->show_community_activity ?? true) {
            $data['recent_activity'] = $this->getRecentActivity($institutionId);
        }

        // Get recent additions (if enabled)
        if ($config->show_recent_additions ?? true) {
            $data['recent_additions'] = $this->getRecentAdditions($institutionId);
        }

        // Get stats (if enabled)
        if ($config->show_stats ?? true) {
            $data['stats'] = $this->getStats($institutionId, $config->stats_config ?? []);
        }

        return $data;
    }

    /**
     * Get filters with resolved values for landing page.
     */
    public function getFiltersWithValues(?int $institutionId = null): array
    {
        $filters = $this->filterRepo->getEnabledFilters($institutionId, true);

        return $filters->map(function ($filter) use ($institutionId) {
            $label = $filter->display_name ?? $filter->type_name;
            $icon = $filter->display_icon ?? $filter->type_icon;

            $values = $this->valueResolver->resolveValues(
                $filter->source_type,
                $filter->source_reference,
                $filter->id,
                $institutionId,
                $filter->max_items_landing ?? 6
            );

            return [
                'code' => $filter->code,
                'label' => $label,
                'icon' => $icon,
                'is_hierarchical' => (bool) $filter->is_hierarchical,
                'allow_multiple' => (bool) $filter->allow_multiple,
                'values' => $values,
            ];
        })->toArray();
    }

    /**
     * Get recent activity (audit trail).
     */
    private function getRecentActivity(?int $institutionId = null, int $limit = 5): array
    {
        // Try to get from audit trail if ahgAuditTrailPlugin is installed
        // The plugin's audit_log has: action, record_id, table_name, username columns
        // Core AtoM's audit_log (if present) has a different schema - skip it
        try {
            $schema = DB::getSchemaBuilder();
            if (!$schema->hasTable('audit_log') || !$schema->hasColumn('audit_log', 'action')) {
                return [];
            }

            $culture = $this->culture;
            $query = DB::table('audit_log as al')
                ->leftJoin('user as u', 'al.user_id', '=', 'u.id')
                ->leftJoin('information_object_i18n as io', function ($join) use ($culture) {
                    $join->on('al.record_id', '=', 'io.id')
                        ->where('io.culture', '=', $culture);
                })
                ->where('al.table_name', 'information_object')
                ->whereIn('al.action', ['create', 'update'])
                ->whereNotNull('al.record_id')
                ->orderByDesc('al.created_at')
                ->limit($limit);

            return $query->select(
                'al.id',
                'al.action',
                'al.record_id',
                'al.created_at',
                DB::raw("COALESCE(u.username, al.username, 'System') as user_name"),
                'io.title as object_title'
            )
                ->get()
                ->map(fn ($row) => [
                    'user' => $row->user_name ?? 'Anonymous',
                    'action' => $row->action,
                    'item_title' => $row->object_title ?? 'Item #' . $row->record_id,
                    'item_id' => $row->record_id,
                    'time_ago' => $this->timeAgo($row->created_at),
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recently added items.
     */
    private function getRecentAdditions(?int $institutionId = null, int $limit = 10): array
    {
        // In AtoM, publication status is stored in 'status' table
        // type_id=158 is PUBLICATION_STATUS, status_id=160 is PUBLISHED
        // Slug is stored in a separate 'slug' table
        $culture = $this->culture;
        $query = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('slug as sl', 'io.id', '=', 'sl.object_id')
            ->join('status as pub_status', function ($join) {
                $join->on('io.id', '=', 'pub_status.object_id')
                    ->where('pub_status.type_id', '=', 158);
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
            ->where('pub_status.status_id', 160) // Published
            ->whereNotNull('io.parent_id')
            ->orderByDesc('o.created_at')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->select(
            'io.id',
            'sl.slug',
            'ioi.title',
            'do.path as thumbnail_path',
            'do.name as thumbnail_name',
            'do.mime_type',
            'o.created_at'
        )
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'slug' => $row->slug,
                'title' => $this->truncate($row->title ?? 'Untitled', 50),
                'thumbnail' => $this->buildThumbnailUrl($row->thumbnail_path, $row->thumbnail_name),
                'media_type' => $this->getMediaType($row->mime_type),
            ])
            ->toArray();
    }

    /**
     * Get collection statistics.
     */
    private function getStats(?int $institutionId = null, ?array $statsConfig = null): array
    {
        $config = $statsConfig ?? ['show_items' => true, 'show_collections' => true];
        $stats = [];

        // Total items
        if ($config['show_items'] ?? true) {
            $count = DB::table('information_object as io')
                ->join('status as pub_status', function ($join) {
                    $join->on('io.id', '=', 'pub_status.object_id')
                        ->where('pub_status.type_id', '=', 158);
                })
                ->where('pub_status.status_id', 160)
                ->whereNotNull('io.parent_id')
                ->when($institutionId, fn ($q) => $q->where('io.repository_id', $institutionId))
                ->count();

            $stats['total_items'] = [
                'value' => $count,
                'label' => 'Items',
            ];
        }

        // Total collections
        if ($config['show_collections'] ?? true) {
            $count = DB::table('repository')
                ->when($institutionId, fn ($q) => $q->where('id', $institutionId))
                ->count();

            $stats['total_collections'] = [
                'value' => $count,
                'label' => 'Collections',
            ];
        }

        // Digital objects
        if ($config['show_digital_objects'] ?? false) {
            $count = DB::table('digital_object')
                ->when($institutionId, function ($q) use ($institutionId) {
                    return $q->whereIn('object_id', function ($sub) use ($institutionId) {
                        $sub->select('id')
                            ->from('information_object')
                            ->where('repository_id', $institutionId);
                    });
                })
                ->count();

            $stats['total_digital_objects'] = [
                'value' => $count,
                'label' => 'Digital Objects',
            ];
        }

        // Contributors (users who have created content)
        if ($config['show_contributors'] ?? false) {
            try {
                $schema = DB::getSchemaBuilder();
                if ($schema->hasTable('audit_log') && $schema->hasColumn('audit_log', 'action')) {
                    $count = DB::table('audit_log')
                        ->whereIn('action', ['create'])
                        ->distinct('user_id')
                        ->count('user_id');

                    $stats['total_contributors'] = [
                        'value' => $count,
                        'label' => 'Contributors',
                    ];
                }
            } catch (\Exception $e) {
                // audit_log schema incompatible - skip
            }
        }

        return $stats;
    }

    /**
     * Format config for API response.
     */
    private function formatConfig(object $config): array
    {
        return [
            'hero_tagline' => $config->hero_tagline ?? 'Discover our collections',
            'hero_subtext' => $config->hero_subtext,
            'hero_search_placeholder' => $config->hero_search_placeholder ?? 'What are you looking for?',
            'suggested_searches' => $config->suggested_searches ?? [],
            'hero_rotation_seconds' => $config->hero_rotation_seconds ?? 8,
            'hero_effect' => $config->hero_effect ?? 'kenburns',
            'show_curated_stories' => (bool) ($config->show_curated_stories ?? true),
            'show_community_activity' => (bool) ($config->show_community_activity ?? true),
            'show_filters' => (bool) ($config->show_filters ?? true),
            'show_stats' => (bool) ($config->show_stats ?? true),
            'show_recent_additions' => (bool) ($config->show_recent_additions ?? true),
            'primary_color' => $config->primary_color ?? '#0d6efd',
            'secondary_color' => $config->secondary_color,
        ];
    }

    /**
     * Get default config when none exists.
     */
    private function getDefaultConfig(): object
    {
        return (object) [
            'hero_tagline' => 'Discover Our Heritage',
            'hero_subtext' => 'Explore collections spanning centuries of history, culture, and human achievement',
            'hero_search_placeholder' => 'What are you looking for?',
            'suggested_searches' => ['photographs', 'maps', 'letters', 'newspapers'],
            'hero_rotation_seconds' => 8,
            'hero_effect' => 'kenburns',
            'show_curated_stories' => true,
            'show_community_activity' => true,
            'show_filters' => true,
            'show_stats' => true,
            'show_recent_additions' => true,
            'primary_color' => '#0d6efd',
            'secondary_color' => null,
            'stats_config' => ['show_items' => true, 'show_collections' => true],
        ];
    }

    /**
     * Convert timestamp to human-readable "time ago".
     */
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $mins = floor($diff / 60);

            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);

            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = floor($diff / 86400);

            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        return date('M j, Y', $time);
    }

    /**
     * Truncate string.
     */
    private function truncate(?string $string, int $length): string
    {
        if ($string === null) {
            return '';
        }
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length - 3) . '...';
    }

    /**
     * Build thumbnail URL from path and filename.
     */
    private function buildThumbnailUrl(?string $path, ?string $name): ?string
    {
        if (!$path || !$name) {
            return null;
        }

        // Path already includes /uploads/ prefix from database
        $path = rtrim($path, '/');

        // AtoM stores thumbnails with _142 suffix (142px thumbnail)
        $basename = pathinfo($name, PATHINFO_FILENAME);

        // Build thumbnail filename (always jpg for thumbnails)
        $thumbnailName = $basename . '_142.jpg';

        return $path . '/' . $thumbnailName;
    }

    /**
     * Get media type from MIME type.
     */
    private function getMediaType(?string $mimeType): ?string
    {
        if (!$mimeType) {
            return null;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        if (str_contains($mimeType, 'pdf')) {
            return 'document';
        }
        if (str_contains($mimeType, 'text')) {
            return 'text';
        }

        return 'other';
    }

    /**
     * Save landing config.
     */
    public function saveConfig(array $data, ?int $institutionId = null): int
    {
        return $this->configRepo->save($data, $institutionId);
    }

    /**
     * Update landing config.
     */
    public function updateConfig(int $id, array $data): bool
    {
        return $this->configRepo->update($id, $data);
    }
}
