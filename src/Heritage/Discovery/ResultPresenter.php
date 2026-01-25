<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Discovery;

use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Result Presenter.
 *
 * Formats search results for API and frontend display.
 * Handles thumbnail generation, snippet creation, and metadata formatting.
 */
class ResultPresenter
{
    private const SNIPPET_LENGTH = 200;
    private const TITLE_MAX_LENGTH = 100;

    /**
     * Format search results for API response.
     */
    public function formatResults(Collection $results, string $culture = 'en'): array
    {
        return $results->map(function ($row) use ($culture) {
            return $this->formatResult($row, $culture);
        })->toArray();
    }

    /**
     * Format a single result.
     */
    public function formatResult(object $row, string $culture = 'en'): array
    {
        return [
            'id' => $row->id,
            'slug' => $row->slug,
            'identifier' => $row->identifier,
            'title' => $this->formatTitle($row->title, $row->identifier),
            'snippet' => $this->createSnippet($row->scope_and_content),
            'thumbnail' => $this->getThumbnailUrl($row),
            'type' => $this->getTypeName($row->level_of_description_id, $culture),
            'media_type' => $this->getMediaType($row->mime_type),
            'date' => $this->getDateDisplay($row->id),
            'collection' => $row->repository_name,
            'extent' => $row->extent_and_medium,
            'url' => $this->getItemUrl($row->slug),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Format title with fallback.
     */
    private function formatTitle(?string $title, ?string $identifier): string
    {
        $displayTitle = $title ?? $identifier ?? 'Untitled';

        if (strlen($displayTitle) > self::TITLE_MAX_LENGTH) {
            return substr($displayTitle, 0, self::TITLE_MAX_LENGTH - 3) . '...';
        }

        return $displayTitle;
    }

    /**
     * Create snippet from content.
     */
    private function createSnippet(?string $content): ?string
    {
        if (!$content) {
            return null;
        }

        // Strip HTML tags
        $text = strip_tags($content);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) <= self::SNIPPET_LENGTH) {
            return $text;
        }

        // Cut at word boundary
        $snippet = substr($text, 0, self::SNIPPET_LENGTH);
        $lastSpace = strrpos($snippet, ' ');

        if ($lastSpace !== false && $lastSpace > self::SNIPPET_LENGTH - 50) {
            $snippet = substr($snippet, 0, $lastSpace);
        }

        return $snippet . '...';
    }

    /**
     * Get thumbnail URL.
     */
    private function getThumbnailUrl(object $row): ?string
    {
        $path = $row->thumbnail_path ?? null;
        $name = $row->thumbnail_name ?? null;

        if (!$path || !$name) {
            return null;
        }

        // Path already includes /uploads/ prefix from database
        $path = rtrim($path, '/');

        // AtoM stores thumbnails with _142 suffix (142px thumbnail)
        // Original: filename.jpg -> Thumbnail: filename_142.jpg
        $basename = pathinfo($name, PATHINFO_FILENAME);

        // Build thumbnail filename (always jpg for thumbnails)
        $thumbnailName = $basename . '_142.jpg';

        return $path . '/' . $thumbnailName;
    }

    /**
     * Get level of description name.
     */
    private function getTypeName(?int $levelId, string $culture): ?string
    {
        if (!$levelId) {
            return null;
        }

        static $cache = [];

        $key = $levelId . '_' . $culture;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $term = DB::table('term_i18n')
            ->where('id', $levelId)
            ->where('culture', $culture)
            ->first();

        $cache[$key] = $term->name ?? null;

        return $cache[$key];
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
     * Get date display string.
     */
    private function getDateDisplay(int $informationObjectId): ?string
    {
        $event = DB::table('event')
            ->where('object_id', $informationObjectId)
            ->whereNotNull('start_date')
            ->orderBy('id')
            ->first();

        if (!$event) {
            return null;
        }

        $startDate = $event->start_date;
        $endDate = $event->end_date ?? null;

        // Format as year or year range
        $startYear = date('Y', strtotime($startDate));

        if ($endDate && $endDate !== $startDate) {
            $endYear = date('Y', strtotime($endDate));
            if ($endYear !== $startYear) {
                return $startYear . '-' . $endYear;
            }
        }

        return $startYear;
    }

    /**
     * Get item URL.
     */
    private function getItemUrl(string $slug): string
    {
        return '/' . $slug;
    }

    /**
     * Format results for grid/card display.
     */
    public function formatForGrid(Collection $results, string $culture = 'en'): array
    {
        return $results->map(function ($row) use ($culture) {
            return [
                'id' => $row->id,
                'slug' => $row->slug,
                'title' => $this->formatTitle($row->title, $row->identifier),
                'thumbnail' => $this->getThumbnailUrl($row),
                'type' => $this->getTypeName($row->level_of_description_id, $culture),
                'media_type' => $this->getMediaType($row->mime_type),
                'url' => $this->getItemUrl($row->slug),
            ];
        })->toArray();
    }

    /**
     * Format results for list display.
     */
    public function formatForList(Collection $results, string $culture = 'en'): array
    {
        return $results->map(function ($row) use ($culture) {
            return [
                'id' => $row->id,
                'slug' => $row->slug,
                'identifier' => $row->identifier,
                'title' => $this->formatTitle($row->title, $row->identifier),
                'snippet' => $this->createSnippet($row->scope_and_content),
                'type' => $this->getTypeName($row->level_of_description_id, $culture),
                'date' => $this->getDateDisplay($row->id),
                'collection' => $row->repository_name,
                'url' => $this->getItemUrl($row->slug),
            ];
        })->toArray();
    }

    /**
     * Highlight search terms in text.
     */
    public function highlightTerms(string $text, string $query): string
    {
        if (empty($query)) {
            return $text;
        }

        $terms = preg_split('/\s+/', $query);

        foreach ($terms as $term) {
            if (strlen($term) < 2) {
                continue;
            }

            $term = preg_quote($term, '/');
            $text = preg_replace(
                '/(' . $term . ')/i',
                '<mark>$1</mark>',
                $text
            );
        }

        return $text;
    }

    /**
     * Format facet counts for display.
     */
    public function formatFacets(array $facets): array
    {
        $formatted = [];

        foreach ($facets as $code => $facet) {
            $formatted[$code] = [
                'label' => $facet['label'],
                'icon' => $facet['icon'],
                'values' => array_map(function ($value) {
                    return [
                        'value' => $value['value'],
                        'label' => $value['label'],
                        'count' => number_format($value['count']),
                        'count_raw' => $value['count'],
                    ];
                }, $facet['values']),
                'selected' => $facet['selected'],
                'has_more' => count($facet['values']) >= 10,
            ];
        }

        return $formatted;
    }

    /**
     * Format statistics for display.
     */
    public function formatStats(array $stats): array
    {
        return array_map(function ($stat) {
            return [
                'value' => number_format($stat['value']),
                'value_raw' => $stat['value'],
                'label' => $stat['label'],
            ];
        }, $stats);
    }
}
