<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Discovery;

use AtomFramework\Heritage\Filters\FilterService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Search Orchestrator.
 *
 * Coordinates intelligent search execution across the heritage platform.
 * Integrates query understanding, multi-source search, result fusion, and learning.
 */
class SearchOrchestrator
{
    private FilterService $filterService;
    private QueryUnderstandingService $queryService;
    private ResultFusionService $fusionService;
    private LearningService $learningService;
    private ResultPresenter $presenter;
    private string $culture = 'en';

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->filterService = new FilterService($culture);
        $this->queryService = new QueryUnderstandingService($culture);
        $this->fusionService = new ResultFusionService();
        $this->learningService = new LearningService($culture);
        $this->presenter = new ResultPresenter();
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        $this->filterService->setCulture($culture);
        $this->queryService->setCulture($culture);
        $this->learningService->setCulture($culture);

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
     * Execute an intelligent discovery search.
     */
    public function search(array $params): array
    {
        $startTime = microtime(true);

        $rawQuery = $params['query'] ?? '';
        $userFilters = $params['filters'] ?? [];
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $institutionId = isset($params['institution_id']) ? (int) $params['institution_id'] : null;
        $culture = $params['culture'] ?? $this->culture;

        // Update culture if different from current
        if ($culture !== $this->culture) {
            $this->setCulture($culture);
        }

        // Step 1: Parse the natural language query
        $parsedQuery = $this->queryService->parse($rawQuery);

        // Step 2: Merge parsed filters with user-selected filters
        $mergedFilters = $this->mergeFilters($parsedQuery['filters'] ?? [], $userFilters);

        // Step 3: Execute multiple search strategies
        $resultSets = $this->executeSearchStrategies($parsedQuery, $mergedFilters, $institutionId, $culture);

        // Step 4: Fuse and rank results
        $allResults = collect();
        foreach ($resultSets as $results) {
            $allResults = $allResults->merge($results);
        }
        $rankedResults = $this->fusionService->fuse(['main' => $allResults], $parsedQuery, $institutionId);

        // Step 5: Deduplicate
        $uniqueResults = $this->fusionService->deduplicate($rankedResults);

        // Get total count
        $total = $uniqueResults->count();

        // Paginate
        $offset = ($page - 1) * $limit;
        $pagedResults = $uniqueResults->slice($offset, $limit);

        // Format results
        $formattedResults = $this->presenter->formatResults($pagedResults, $culture);

        // Build facets
        $facets = $this->buildFacets($rawQuery, $userFilters, $institutionId, $culture);

        // Calculate duration
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Log the search (async-friendly)
        $searchLogId = $this->learningService->logSearch(
            $rawQuery,
            $parsedQuery,
            $total,
            $durationMs,
            $institutionId
        );

        // Get suggestions if few results
        $suggestions = [];
        if ($total < 5 && !empty($rawQuery)) {
            $suggestions = $this->getSuggestions($rawQuery, $parsedQuery, $institutionId);
        }

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
            'results' => $formattedResults,
            'facets' => $facets,
            'suggestions' => $suggestions,
            'query' => $rawQuery,
            'parsed_query' => [
                'intent' => $parsedQuery['intent'],
                'entities' => $parsedQuery['entities'],
                'time_references' => $parsedQuery['time_references'],
            ],
            'filters_applied' => $userFilters,
            'duration_ms' => $durationMs,
            'search_id' => $searchLogId,
        ];
    }

    /**
     * Execute multiple search strategies and combine results.
     */
    private function executeSearchStrategies(
        array $parsedQuery,
        array $filters,
        ?int $institutionId,
        string $culture
    ): array {
        $results = [];

        // Strategy 1: Keyword search on main fields
        $results['keyword'] = $this->keywordSearch(
            $parsedQuery['keywords'],
            $parsedQuery['phrases'],
            $filters,
            $institutionId,
            $culture
        );

        // Strategy 2: Entity-based search (if entities detected)
        if (!empty($parsedQuery['entities'])) {
            $results['entity'] = $this->entitySearch($parsedQuery['entities'], $institutionId, $culture);
        }

        // Strategy 3: Date range search (if time references detected)
        if (!empty($parsedQuery['time_references'])) {
            $results['date'] = $this->dateRangeSearch($parsedQuery['time_references'], $institutionId, $culture);
        }

        // Strategy 4: Expanded terms search (synonyms)
        if (!empty($parsedQuery['expanded_terms'])) {
            $results['expanded'] = $this->expandedSearch($parsedQuery['expanded_terms'], $institutionId, $culture);
        }

        return $results;
    }

    /**
     * Keyword search on information objects.
     */
    private function keywordSearch(
        array $keywords,
        array $phrases,
        array $filters,
        ?int $institutionId,
        string $culture
    ): Collection {
        if (empty($keywords) && empty($phrases)) {
            // Browse mode - return recent items
            return $this->browseSearch($filters, $institutionId, $culture);
        }

        $query = $this->baseQuery($culture);

        // Apply keyword search
        $query->where(function ($q) use ($keywords, $phrases) {
            // Search for all keywords (AND)
            foreach ($keywords as $kw) {
                $term = '%' . addcslashes($kw, '%_') . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('ioi.title', 'LIKE', $term)
                        ->orWhere('ioi.scope_and_content', 'LIKE', $term)
                        ->orWhere('io.identifier', 'LIKE', $term)
                        ->orWhere('ioi.alternate_title', 'LIKE', $term)
                        ->orWhere('ioi.archival_history', 'LIKE', $term)
                        ->orWhere('ioi.arrangement', 'LIKE', $term);
                });
            }

            // Search for exact phrases (bonus)
            foreach ($phrases as $phrase) {
                $term = '%' . addcslashes($phrase, '%_') . '%';
                $q->orWhere('ioi.title', 'LIKE', $term)
                    ->orWhere('ioi.scope_and_content', 'LIKE', $term);
            }
        });

        // Apply filters
        $query = $this->applyFilters($query, $filters, $institutionId);

        // Apply institution filter
        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(500)->get();
    }

    /**
     * Browse search (no query, just filters).
     */
    private function browseSearch(array $filters, ?int $institutionId, string $culture): Collection
    {
        $query = $this->baseQuery($culture);

        // Apply filters
        $query = $this->applyFilters($query, $filters, $institutionId);

        // Apply institution filter
        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->orderByDesc('o.updated_at')->limit(500)->get();
    }

    /**
     * Entity-based search.
     */
    private function entitySearch(array $entities, ?int $institutionId, string $culture): Collection
    {
        $results = collect();

        foreach ($entities as $entity) {
            $entityResults = match ($entity['type']) {
                'person', 'organization' => $this->searchByCreator($entity, $institutionId, $culture),
                'place' => $this->searchByPlace($entity, $institutionId, $culture),
                'subject' => $this->searchBySubject($entity, $institutionId, $culture),
                'format' => $this->searchByFormat($entity, $institutionId, $culture),
                default => collect(),
            };

            $results = $results->merge($entityResults);
        }

        return $results;
    }

    /**
     * Search by creator/actor.
     */
    private function searchByCreator(array $entity, ?int $institutionId, string $culture): Collection
    {
        $query = $this->baseQuery($culture);

        if (isset($entity['id'])) {
            // Direct actor ID match
            $query->join('relation as rel', function ($join) use ($entity) {
                $join->on('io.id', '=', 'rel.subject_id')
                    ->where('rel.object_id', '=', $entity['id']);
            });
        } else {
            // Name search
            $query->join('relation as rel', 'io.id', '=', 'rel.subject_id')
                ->join('actor_i18n as ai', 'rel.object_id', '=', 'ai.id')
                ->where('ai.culture', $culture)
                ->where('ai.authorized_form_of_name', 'LIKE', '%' . $entity['value'] . '%');
        }

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(200)->get();
    }

    /**
     * Search by place.
     */
    private function searchByPlace(array $entity, ?int $institutionId, string $culture): Collection
    {
        $query = $this->baseQuery($culture);

        if (isset($entity['id'])) {
            $query->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
                ->where('otr.term_id', $entity['id']);
        } else {
            $query->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
                ->join('term_i18n as ti', 'otr.term_id', '=', 'ti.id')
                ->join('term as t', 'ti.id', '=', 't.id')
                ->where('t.taxonomy_id', 42) // Place access points
                ->where('ti.culture', $culture)
                ->where('ti.name', 'LIKE', '%' . $entity['value'] . '%');
        }

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(200)->get();
    }

    /**
     * Search by subject.
     */
    private function searchBySubject(array $entity, ?int $institutionId, string $culture): Collection
    {
        $query = $this->baseQuery($culture);

        if (isset($entity['id'])) {
            $query->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
                ->where('otr.term_id', $entity['id']);
        } else {
            $query->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
                ->join('term_i18n as ti', 'otr.term_id', '=', 'ti.id')
                ->join('term as t', 'ti.id', '=', 't.id')
                ->where('t.taxonomy_id', 35) // Subject access points
                ->where('ti.culture', $culture)
                ->where('ti.name', 'LIKE', '%' . $entity['value'] . '%');
        }

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(200)->get();
    }

    /**
     * Search by format/media type.
     */
    private function searchByFormat(array $entity, ?int $institutionId, string $culture): Collection
    {
        $query = $this->baseQuery($culture);

        // Media type is in taxonomy 52
        $query->join('object_term_relation as otr', 'io.id', '=', 'otr.object_id')
            ->join('term_i18n as ti', 'otr.term_id', '=', 'ti.id')
            ->join('term as t', 'ti.id', '=', 't.id')
            ->where('t.taxonomy_id', 52)
            ->where('ti.culture', $culture)
            ->where('ti.name', 'LIKE', '%' . $entity['value'] . '%');

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(200)->get();
    }

    /**
     * Date range search.
     */
    private function dateRangeSearch(array $timeRefs, ?int $institutionId, string $culture): Collection
    {
        $query = $this->baseQuery($culture)
            ->join('event as ev', 'io.id', '=', 'ev.object_id');

        $query->where(function ($q) use ($timeRefs) {
            foreach ($timeRefs as $ref) {
                $q->orWhere(function ($inner) use ($ref) {
                    $inner->where('ev.start_date', '>=', $ref['start'])
                        ->where('ev.start_date', '<=', $ref['end']);
                });
            }
        });

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(200)->get();
    }

    /**
     * Expanded terms search (synonyms).
     */
    private function expandedSearch(array $expandedTerms, ?int $institutionId, string $culture): Collection
    {
        $terms = array_column($expandedTerms, 'term');
        if (empty($terms)) {
            return collect();
        }

        $query = $this->baseQuery($culture);

        $query->where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $pattern = '%' . addcslashes($term, '%_') . '%';
                $q->orWhere('ioi.title', 'LIKE', $pattern)
                    ->orWhere('ioi.scope_and_content', 'LIKE', $pattern);
            }
        });

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->limit(100)->get();
    }

    /**
     * Base query builder with standard joins.
     */
    private function baseQuery(string $culture): \Illuminate\Database\Query\Builder
    {
        // In AtoM, publication status is stored in 'status' table
        // type_id=158 is PUBLICATION_STATUS, status_id=160 is PUBLISHED
        // Repository inherits from Actor, so name is in actor_i18n
        // Slug is stored in a separate 'slug' table
        return DB::table('information_object as io')
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
            ->leftJoin('actor_i18n as repo_ai', function ($join) use ($culture) {
                $join->on('io.repository_id', '=', 'repo_ai.id')
                    ->where('repo_ai.culture', '=', $culture);
            })
            ->where('pub_status.status_id', 160) // Published only
            ->whereNotNull('io.parent_id')
            ->select(
                'io.id',
                'sl.slug',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'pub_status.status_id as publication_status_id',
                'ioi.title',
                'ioi.scope_and_content',
                'ioi.extent_and_medium',
                'do.path as thumbnail_path',
                'do.name as thumbnail_name',
                'do.mime_type',
                'repo_ai.authorized_form_of_name as repository_name',
                'o.created_at',
                'o.updated_at'
            )
            ->groupBy('io.id');
    }

    /** @var int Counter for unique filter aliases */
    private int $filterAliasCounter = 0;

    /**
     * Apply user filters to query.
     */
    private function applyFilters(
        \Illuminate\Database\Query\Builder $query,
        array $filters,
        ?int $institutionId
    ): \Illuminate\Database\Query\Builder {
        $this->filterAliasCounter = 0; // Reset counter for each search
        $conditions = $this->filterService->buildFilterConditions($filters, $institutionId);

        foreach ($conditions as $condition) {
            $query = $this->applyCondition($query, $condition);
        }

        return $query;
    }

    /**
     * Apply a single filter condition.
     */
    private function applyCondition(
        \Illuminate\Database\Query\Builder $query,
        array $condition
    ): \Illuminate\Database\Query\Builder {
        // Generate unique alias for each condition
        $alias = 'filter_' . $this->filterAliasCounter++;

        switch ($condition['type']) {
            case 'taxonomy':
                if (!empty($condition['join'])) {
                    $query->join(
                        $condition['join']['table'] . ' as ' . $alias,
                        'io.id',
                        '=',
                        $alias . '.object_id'
                    );
                }
                if (!empty($condition['where'])) {
                    $where = $condition['where'];
                    if ($where['operator'] === 'IN') {
                        $query->whereIn($alias . '.term_id', $where['values']);
                    }
                }
                break;

            case 'field':
                if (!empty($condition['where'])) {
                    $where = $condition['where'];
                    if ($where['operator'] === 'IN') {
                        $query->whereIn($where['field'], $where['values']);
                    } else {
                        $query->where($where['field'], $where['operator'], $where['values']);
                    }
                }
                break;

            case 'date_range':
                if (!empty($condition['ranges'])) {
                    $query->join('event as ev_' . $alias, 'io.id', '=', 'ev_' . $alias . '.object_id');
                    $query->where(function ($q) use ($condition, $alias) {
                        foreach ($condition['ranges'] as $range) {
                            $q->orWhereBetween('ev_' . $alias . '.start_date', [$range['start'], $range['end']]);
                        }
                    });
                }
                break;
        }

        return $query;
    }

    /**
     * Merge parsed filters with user-selected filters.
     *
     * Note: Auto-detected filters from query parsing are NOT included here.
     * They are used for entity-based search strategies to find additional results,
     * not as mandatory filters that would exclude keyword matches.
     */
    private function mergeFilters(array $parsedFilters, array $userFilters): array
    {
        // Only use user-selected filters, not auto-detected ones
        // Auto-detected entities are handled via entitySearch for result boosting
        return $userFilters;
    }

    /**
     * Build facets for search refinement.
     */
    private function buildFacets(
        string $query,
        array $currentFilters,
        ?int $institutionId,
        string $culture
    ): array {
        $facets = [];
        $enabledFilters = $this->filterService->getEnabledFilters($institutionId);

        foreach ($enabledFilters as $filter) {
            if (!$filter->show_in_search) {
                continue;
            }

            $facets[$filter->code] = [
                'label' => $filter->display_name ?? $filter->type_name,
                'icon' => $filter->display_icon ?? $filter->type_icon,
                'values' => [], // Facet values calculated separately for performance
                'selected' => $currentFilters[$filter->code] ?? [],
            ];
        }

        return $facets;
    }

    /**
     * Get search suggestions.
     */
    private function getSuggestions(string $query, array $parsedQuery, ?int $institutionId): array
    {
        // Get from learning service
        $learned = $this->learningService->getQuerySuggestions($query, $institutionId, 3);

        // Also suggest based on detected entities
        $entitySuggestions = [];
        foreach ($parsedQuery['entities'] ?? [] as $entity) {
            if ($entity['confidence'] > 0.8) {
                $entitySuggestions[] = $entity['value'];
            }
        }

        return array_slice(array_unique(array_merge($learned, $entitySuggestions)), 0, 5);
    }

    /**
     * Get autocomplete suggestions (enhanced).
     */
    public function autocomplete(string $prefix, ?int $institutionId = null, int $limit = 10): array
    {
        // Use learning service for suggestions
        return $this->learningService->getQuerySuggestions($prefix, $institutionId, $limit);
    }

    /**
     * Log a click on a search result.
     */
    public function logClick(int $searchId, int $itemId, int $position, ?int $timeToClick = null): int
    {
        return $this->learningService->logClick($searchId, $itemId, $position, $timeToClick);
    }
}
