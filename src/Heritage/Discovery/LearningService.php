<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Discovery;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Learning Service.
 *
 * Learns from user behavior to improve search quality.
 * Manages search logging, click tracking, and suggestion building.
 */
class LearningService
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

    /**
     * Get current culture.
     */
    public function getCulture(): string
    {
        return $this->culture;
    }

    /**
     * Log a search query and its parsed result.
     *
     * @param string $query Original query
     * @param array $parsedQuery Parsed query from QueryUnderstandingService
     * @param int $resultCount Number of results returned
     * @param int $durationMs Search duration in milliseconds
     * @param int|null $institutionId Institution ID
     * @return int Log ID
     */
    public function logSearch(
        string $query,
        array $parsedQuery,
        int $resultCount,
        int $durationMs,
        ?int $institutionId = null
    ): int {
        return (int) DB::table('heritage_discovery_log')->insertGetId([
            'institution_id' => $institutionId,
            'query_text' => $query ?: null,
            'detected_language' => $parsedQuery['language'] ?? 'en',
            'query_intent' => $parsedQuery['intent'] ?? null,
            'parsed_entities' => !empty($parsedQuery['entities']) ? json_encode($parsedQuery['entities']) : null,
            'expanded_terms' => !empty($parsedQuery['expanded_terms']) ? json_encode($parsedQuery['expanded_terms']) : null,
            'filters_applied' => !empty($parsedQuery['filters']) ? json_encode($parsedQuery['filters']) : null,
            'result_count' => $resultCount,
            'search_duration_ms' => $durationMs,
            'session_id' => session_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log a click on a search result.
     *
     * @param int $searchLogId ID from heritage_discovery_log
     * @param int $itemId ID of clicked item
     * @param int $position Position in results (1-indexed)
     * @param int|null $timeToClickMs Time from search to click
     * @return int Click log ID
     */
    public function logClick(
        int $searchLogId,
        int $itemId,
        int $position,
        ?int $timeToClickMs = null
    ): int {
        $clickId = (int) DB::table('heritage_discovery_click')->insertGetId([
            'search_log_id' => $searchLogId,
            'item_id' => $itemId,
            'position' => $position,
            'time_to_click_ms' => $timeToClickMs,
            'session_id' => session_id() ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update search log with click info
        $log = DB::table('heritage_discovery_log')->where('id', $searchLogId)->first();
        if ($log) {
            $updates = ['click_count' => ($log->click_count ?? 0) + 1];
            if ($log->first_click_position === null) {
                $updates['first_click_position'] = $position;
            }
            DB::table('heritage_discovery_log')
                ->where('id', $searchLogId)
                ->update($updates);
        }

        // Update suggestion stats if this was a successful search
        if ($log && !empty($log->query_text)) {
            $this->updateSuggestionFromClick($log->query_text, $log->institution_id);
        }

        return $clickId;
    }

    /**
     * Update dwell time for a click.
     *
     * @param int $clickId Click log ID
     * @param int $dwellTimeSeconds Time spent on item page
     */
    public function updateDwellTime(int $clickId, int $dwellTimeSeconds): void
    {
        DB::table('heritage_discovery_click')
            ->where('id', $clickId)
            ->update(['dwell_time_seconds' => $dwellTimeSeconds]);
    }

    /**
     * Get autocomplete suggestions.
     *
     * @param string $prefix Search prefix
     * @param int|null $institutionId Institution ID
     * @param int $limit Max suggestions
     * @return array Suggestions
     */
    public function getQuerySuggestions(string $prefix, ?int $institutionId = null, int $limit = 10): array
    {
        if (strlen($prefix) < 2) {
            return [];
        }

        $prefix = strtolower(trim($prefix));

        // Get from suggestion table
        $suggestions = DB::table('heritage_search_suggestion')
            ->where('is_enabled', 1)
            ->where('suggestion_text', 'LIKE', $prefix . '%')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->when(!$institutionId, fn ($q) => $q->whereNull('institution_id'))
            ->where('avg_results', '>', 0)
            ->orderByDesc('is_curated')
            ->orderByDesc('success_rate')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('suggestion_text')
            ->toArray();

        // If not enough suggestions, also search titles
        if (count($suggestions) < $limit) {
            $needed = $limit - count($suggestions);
            $titleSuggestions = DB::table('information_object_i18n')
                ->where('title', 'LIKE', $prefix . '%')
                ->where('culture', $this->culture)
                ->whereNotIn('title', $suggestions)
                ->distinct()
                ->limit($needed)
                ->pluck('title')
                ->toArray();

            $suggestions = array_merge($suggestions, $titleSuggestions);
        }

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Update suggestions from successful searches.
     *
     * Runs periodically to rebuild suggestion index.
     *
     * @param int|null $institutionId Institution ID
     * @param int $minSearches Minimum searches to qualify
     * @param int $minResults Minimum average results
     */
    public function updateSuggestions(?int $institutionId = null, int $minSearches = 3, int $minResults = 1): void
    {
        // Get popular successful searches
        $popular = DB::table('heritage_discovery_log')
            ->select(
                'query_text',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('AVG(result_count) as avg_results'),
                DB::raw('SUM(click_count) as total_clicks')
            )
            ->whereNotNull('query_text')
            ->where('query_text', '!=', '')
            ->where('created_at', '>=', date('Y-m-d', strtotime('-90 days')))
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy('query_text')
            ->having('search_count', '>=', $minSearches)
            ->having('avg_results', '>=', $minResults)
            ->orderByDesc('search_count')
            ->limit(1000)
            ->get();

        foreach ($popular as $row) {
            $successRate = $row->total_clicks > 0 ? min(1.0, $row->total_clicks / $row->search_count) : 0;

            DB::table('heritage_search_suggestion')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'suggestion_text' => strtolower($row->query_text),
                    'suggestion_type' => 'query',
                ],
                [
                    'search_count' => $row->search_count,
                    'click_count' => $row->total_clicks,
                    'success_rate' => $successRate,
                    'avg_results' => (int) $row->avg_results,
                    'last_searched_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    /**
     * Learn synonyms from user behavior.
     *
     * Identifies terms that lead to clicks on the same items.
     *
     * @param int|null $institutionId Institution ID
     * @param float $minConfidence Minimum confidence to store
     */
    public function learnSynonyms(?int $institutionId = null, float $minConfidence = 0.6): void
    {
        // Find queries that resulted in clicks on the same items
        $clickPairs = DB::table('heritage_discovery_log as l1')
            ->join('heritage_discovery_click as c1', 'l1.id', '=', 'c1.search_log_id')
            ->join('heritage_discovery_click as c2', 'c1.item_id', '=', 'c2.item_id')
            ->join('heritage_discovery_log as l2', 'c2.search_log_id', '=', 'l2.id')
            ->whereNotNull('l1.query_text')
            ->whereNotNull('l2.query_text')
            ->where('l1.query_text', '!=', 'l2.query_text')
            ->where('l1.id', '<', 'l2.id') // Avoid duplicates
            ->select(
                'l1.query_text as term1',
                'l2.query_text as term2',
                DB::raw('COUNT(*) as co_occurrence')
            )
            ->groupBy('l1.query_text', 'l2.query_text')
            ->having('co_occurrence', '>=', 3)
            ->orderByDesc('co_occurrence')
            ->limit(500)
            ->get();

        foreach ($clickPairs as $pair) {
            // Calculate confidence based on co-occurrence
            $confidence = min(0.95, 0.5 + ($pair->co_occurrence * 0.05));

            if ($confidence >= $minConfidence) {
                // Store bidirectional relationship
                $this->storeLearnedTerm(
                    strtolower($pair->term1),
                    strtolower($pair->term2),
                    'related',
                    $confidence,
                    $institutionId
                );
            }
        }
    }

    /**
     * Store a learned term relationship.
     */
    private function storeLearnedTerm(
        string $term,
        string $relatedTerm,
        string $relationship,
        float $confidence,
        ?int $institutionId
    ): void {
        $existing = DB::table('heritage_learned_term')
            ->where('term', $term)
            ->where('related_term', $relatedTerm)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->when(!$institutionId, fn ($q) => $q->whereNull('institution_id'))
            ->first();

        if ($existing) {
            DB::table('heritage_learned_term')
                ->where('id', $existing->id)
                ->update([
                    'confidence_score' => max($existing->confidence_score, $confidence),
                    'usage_count' => $existing->usage_count + 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            DB::table('heritage_learned_term')->insert([
                'institution_id' => $institutionId,
                'term' => $term,
                'related_term' => $relatedTerm,
                'relationship_type' => $relationship,
                'confidence_score' => $confidence,
                'usage_count' => 1,
                'source' => 'user_behavior',
                'is_verified' => 0,
                'is_enabled' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Update suggestion stats from a click.
     */
    private function updateSuggestionFromClick(string $queryText, ?int $institutionId): void
    {
        DB::table('heritage_search_suggestion')
            ->where('suggestion_text', strtolower($queryText))
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->when(!$institutionId, fn ($q) => $q->whereNull('institution_id'))
            ->update([
                'click_count' => DB::raw('click_count + 1'),
                'success_rate' => DB::raw('LEAST(1.0, (click_count + 1) / GREATEST(1, search_count))'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get search analytics summary.
     *
     * @param int|null $institutionId Institution ID
     * @param int $days Number of days to analyze
     * @return array Analytics data
     */
    public function getAnalytics(?int $institutionId = null, int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $baseQuery = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $since)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId));

        // Overall stats
        $stats = (clone $baseQuery)->selectRaw('
            COUNT(*) as total_searches,
            COUNT(DISTINCT session_id) as unique_sessions,
            AVG(result_count) as avg_results,
            AVG(search_duration_ms) as avg_duration,
            SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) as zero_result_searches,
            SUM(click_count) as total_clicks
        ')->first();

        // Top queries
        $topQueries = (clone $baseQuery)
            ->select('query_text', DB::raw('COUNT(*) as count'), DB::raw('AVG(result_count) as avg_results'))
            ->whereNotNull('query_text')
            ->groupBy('query_text')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Zero result queries (problems to solve)
        $zeroResults = (clone $baseQuery)
            ->select('query_text', DB::raw('COUNT(*) as count'))
            ->whereNotNull('query_text')
            ->where('result_count', 0)
            ->groupBy('query_text')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // Search by intent
        $byIntent = (clone $baseQuery)
            ->select('query_intent', DB::raw('COUNT(*) as count'))
            ->whereNotNull('query_intent')
            ->groupBy('query_intent')
            ->get()
            ->pluck('count', 'query_intent')
            ->toArray();

        // Searches by day
        $byDay = (clone $baseQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return [
            'period_days' => $days,
            'total_searches' => (int) ($stats->total_searches ?? 0),
            'unique_sessions' => (int) ($stats->unique_sessions ?? 0),
            'avg_results' => round($stats->avg_results ?? 0, 1),
            'avg_duration_ms' => round($stats->avg_duration ?? 0),
            'zero_result_rate' => $stats->total_searches > 0
                ? round(($stats->zero_result_searches / $stats->total_searches) * 100, 1)
                : 0,
            'click_through_rate' => $stats->total_searches > 0
                ? round(($stats->total_clicks / $stats->total_searches) * 100, 1)
                : 0,
            'top_queries' => $topQueries->toArray(),
            'zero_result_queries' => $zeroResults->toArray(),
            'by_intent' => $byIntent,
            'by_day' => $byDay->toArray(),
        ];
    }

    /**
     * Cleanup old data.
     *
     * @param int $keepDays Days to keep
     * @return array Counts of deleted records
     */
    public function cleanup(int $keepDays = 90): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$keepDays} days"));

        $clicksDeleted = DB::table('heritage_discovery_click')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $logsDeleted = DB::table('heritage_discovery_log')
            ->where('created_at', '<', $cutoff)
            ->delete();

        return [
            'clicks_deleted' => $clicksDeleted,
            'logs_deleted' => $logsDeleted,
        ];
    }
}
