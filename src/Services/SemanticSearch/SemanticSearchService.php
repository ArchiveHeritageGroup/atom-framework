<?php

declare(strict_types=1);

namespace AtomFramework\Services\SemanticSearch;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Semantic Search Service
 *
 * Provides semantic search capabilities by expanding queries with synonyms
 * and integrating with Elasticsearch for improved search results.
 *
 * @package AtomFramework\Services\SemanticSearch
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class SemanticSearchService
{
    private Logger $logger;
    private ThesaurusService $thesaurus;
    private ?EmbeddingService $embedding = null;
    private array $config;

    public function __construct(?ThesaurusService $thesaurus = null, array $config = [])
    {
        $logDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_log_dir', '/var/log/atom')
            : '/var/log/atom';

        $this->config = array_merge([
            'log_path' => $logDir . '/semantic_search.log',
            'enabled' => true,
            'expansion_limit' => 5,
            'min_synonym_weight' => 0.6,
            'boost_synonyms' => 0.8,
            'boost_original' => 1.0,
            'use_fuzzy_matching' => true,
            'fuzzy_min_similarity' => 0.8,
            'log_searches' => true,
        ], $config);

        $this->logger = new Logger('semantic_search');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }

        $this->thesaurus = $thesaurus ?? new ThesaurusService();
    }

    // ========================================================================
    // Search Methods
    // ========================================================================

    /**
     * Perform a semantic search with query expansion
     */
    public function search(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        $options = array_merge([
            'expand' => true,
            'language' => 'en',
            'limit' => 20,
            'offset' => 0,
            'filters' => [],
        ], $options);

        // Expand the query if enabled
        $expansion = null;
        $expandedQuery = $query;

        if ($options['expand'] && $this->isEnabled()) {
            $expansion = $this->thesaurus->expandQuery($query, $options['language']);
            $expandedQuery = $expansion['expanded_query'];
        }

        // Build Elasticsearch query
        $esQuery = $this->buildElasticsearchQuery($query, $expansion, $options);

        // Log the search
        if ($this->config['log_searches']) {
            $this->logSearch($query, $expansion, microtime(true) - $startTime);
        }

        return [
            'original_query' => $query,
            'expanded_query' => $expandedQuery,
            'expansion' => $expansion,
            'elasticsearch_query' => $esQuery,
            'search_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    /**
     * Build Elasticsearch query with query expansion
     */
    public function buildElasticsearchQuery(string $query, ?array $expansion, array $options = []): array
    {
        $must = [];
        $should = [];

        // Original query with boost
        $must[] = [
            'multi_match' => [
                'query' => $query,
                'fields' => [
                    'i18n.*.title^3',
                    'i18n.*.scopeAndContent^2',
                    'i18n.*.extent',
                    'i18n.*.archivalHistory',
                    'creators.i18n.*.authorizedFormOfName^2',
                    'names.i18n.*.authorizedFormOfName',
                    'subjects.i18n.*.name',
                    'places.i18n.*.name',
                ],
                'type' => 'best_fields',
                'boost' => $this->config['boost_original'],
                'fuzziness' => $this->config['use_fuzzy_matching'] ? 'AUTO' : 0,
            ],
        ];

        // Add expanded synonyms as should clauses
        if ($expansion && !empty($expansion['expanded_terms'])) {
            foreach ($expansion['expanded_terms'] as $term => $synonyms) {
                foreach ($synonyms as $synonym) {
                    $should[] = [
                        'multi_match' => [
                            'query' => $synonym,
                            'fields' => [
                                'i18n.*.title^2',
                                'i18n.*.scopeAndContent',
                                'i18n.*.extent',
                                'creators.i18n.*.authorizedFormOfName',
                                'subjects.i18n.*.name',
                            ],
                            'type' => 'best_fields',
                            'boost' => $this->config['boost_synonyms'],
                        ],
                    ];
                }
            }
        }

        // Build the bool query
        $boolQuery = ['must' => $must];

        if (!empty($should)) {
            $boolQuery['should'] = $should;
            $boolQuery['minimum_should_match'] = 0;
        }

        // Add filters
        $filters = $options['filters'] ?? [];

        if (!empty($filters)) {
            $boolQuery['filter'] = $this->buildFilters($filters);
        }

        return [
            'query' => [
                'bool' => $boolQuery,
            ],
            'highlight' => [
                'fields' => [
                    'i18n.*.title' => new \stdClass(),
                    'i18n.*.scopeAndContent' => ['fragment_size' => 200],
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
            'from' => $options['offset'] ?? 0,
            'size' => $options['limit'] ?? 20,
        ];
    }

    /**
     * Build filter clauses for Elasticsearch
     */
    private function buildFilters(array $filters): array
    {
        $filterClauses = [];

        if (!empty($filters['repository'])) {
            $filterClauses[] = [
                'term' => ['repository.id' => $filters['repository']],
            ];
        }

        if (!empty($filters['level_of_description'])) {
            $filterClauses[] = [
                'term' => ['levelOfDescriptionId' => $filters['level_of_description']],
            ];
        }

        if (!empty($filters['date_start'])) {
            $filterClauses[] = [
                'range' => [
                    'dates.startDate' => ['gte' => $filters['date_start']],
                ],
            ];
        }

        if (!empty($filters['date_end'])) {
            $filterClauses[] = [
                'range' => [
                    'dates.endDate' => ['lte' => $filters['date_end']],
                ],
            ];
        }

        if (!empty($filters['media_type'])) {
            $filterClauses[] = [
                'term' => ['hasDigitalObject' => true],
            ];
        }

        return $filterClauses;
    }

    // ========================================================================
    // Query Expansion for Display
    // ========================================================================

    /**
     * Get expansion info for display in UI
     */
    public function getExpansionInfo(string $query, string $language = 'en'): array
    {
        if (!$this->isEnabled()) {
            return [
                'enabled' => false,
                'expanded' => false,
                'terms' => [],
            ];
        }

        $expansion = $this->thesaurus->expandQuery($query, $language);

        return [
            'enabled' => true,
            'expanded' => $expansion['expansion_count'] > 0,
            'original_query' => $query,
            'expansion_count' => $expansion['expansion_count'],
            'terms' => $expansion['expanded_terms'],
            'display_text' => $this->formatExpansionDisplay($expansion),
        ];
    }

    /**
     * Format expansion for display
     */
    private function formatExpansionDisplay(array $expansion): string
    {
        if ($expansion['expansion_count'] === 0) {
            return '';
        }

        $parts = [];
        foreach ($expansion['expanded_terms'] as $term => $synonyms) {
            $parts[] = $term . ' (' . implode(', ', $synonyms) . ')';
        }

        return 'Search expanded: ' . implode('; ', $parts);
    }

    // ========================================================================
    // Similar Terms / Suggestions
    // ========================================================================

    /**
     * Get similar terms for autocomplete/suggestions
     */
    public function getSuggestions(string $prefix, int $limit = 10): array
    {
        $suggestions = [];

        // Search thesaurus terms
        $terms = $this->thesaurus->searchTerms($prefix, $limit);

        foreach ($terms as $term) {
            $suggestions[] = [
                'term' => $term->term,
                'type' => 'thesaurus',
                'domain' => $term->domain,
            ];
        }

        return $suggestions;
    }

    /**
     * Get "Did you mean" suggestions for potential typos
     */
    public function getDidYouMean(string $query): array
    {
        $suggestions = [];

        // Use embedding service for semantic similarity if available
        if ($this->embedding && $this->embedding->isAvailable()) {
            $similar = $this->embedding->findSimilarTerms($query, 3);
            foreach ($similar as $term) {
                if ($term['term'] !== $query) {
                    $suggestions[] = [
                        'term' => $term['term'],
                        'score' => $term['similarity'],
                    ];
                }
            }
        }

        return $suggestions;
    }

    // ========================================================================
    // Logging
    // ========================================================================

    /**
     * Log a search query
     */
    private function logSearch(string $query, ?array $expansion, float $searchTime): void
    {
        try {
            DB::table('ahg_semantic_search_log')->insert([
                'original_query' => $query,
                'expanded_query' => $expansion ? $expansion['expanded_query'] : null,
                'expansion_terms' => $expansion ? json_encode($expansion['expanded_terms']) : null,
                'search_time_ms' => (int) ($searchTime * 1000),
                'user_id' => $this->getCurrentUserId(),
                'session_id' => session_id() ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to log search', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get current user ID if available
     */
    private function getCurrentUserId(): ?int
    {
        if (class_exists('sfContext') && \sfContext::hasInstance()) {
            $user = \sfContext::getInstance()->getUser();
            if ($user && method_exists($user, 'getUserId')) {
                return $user->getUserId();
            }
        }
        return null;
    }

    // ========================================================================
    // Configuration
    // ========================================================================

    /**
     * Check if semantic search is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] && $this->thesaurus->getSetting('semantic_search_enabled', true);
    }

    /**
     * Enable semantic search
     */
    public function enable(): void
    {
        $this->thesaurus->setSetting('semantic_search_enabled', true);
        $this->config['enabled'] = true;
    }

    /**
     * Disable semantic search
     */
    public function disable(): void
    {
        $this->thesaurus->setSetting('semantic_search_enabled', false);
        $this->config['enabled'] = false;
    }

    /**
     * Set the embedding service for semantic similarity
     */
    public function setEmbeddingService(EmbeddingService $embedding): void
    {
        $this->embedding = $embedding;
    }

    // ========================================================================
    // Analytics
    // ========================================================================

    /**
     * Get popular search terms
     */
    public function getPopularSearches(int $limit = 20, ?string $period = null): array
    {
        $query = DB::table('ahg_semantic_search_log')
            ->selectRaw('original_query, COUNT(*) as count')
            ->groupBy('original_query')
            ->orderBy('count', 'desc')
            ->limit($limit);

        if ($period) {
            $date = match ($period) {
                'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
                'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
                default => null,
            };

            if ($date) {
                $query->where('created_at', '>=', $date);
            }
        }

        return $query->get()->toArray();
    }

    /**
     * Get expansion statistics
     */
    public function getExpansionStats(): array
    {
        $total = DB::table('ahg_semantic_search_log')->count();
        $expanded = DB::table('ahg_semantic_search_log')
            ->whereNotNull('expansion_terms')
            ->count();

        return [
            'total_searches' => $total,
            'expanded_searches' => $expanded,
            'expansion_rate' => $total > 0 ? round(($expanded / $total) * 100, 2) : 0,
        ];
    }
}
