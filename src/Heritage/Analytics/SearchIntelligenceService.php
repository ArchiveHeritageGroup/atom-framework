<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Analytics;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Search Intelligence Service.
 *
 * Analyzes search patterns and provides insights.
 */
class SearchIntelligenceService
{
    /**
     * Get popular search queries.
     */
    public function getPopularQueries(?int $institutionId = null, int $days = 30, int $limit = 20): Collection
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return DB::table('heritage_discovery_log')
            ->select(
                'query_text',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('AVG(result_count) as avg_results'),
                DB::raw('SUM(click_count) as total_clicks')
            )
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('query_text')
            ->where('query_text', '!=', '')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy('query_text')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get zero-result queries.
     */
    public function getZeroResultQueries(?int $institutionId = null, int $days = 30, int $limit = 20): Collection
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return DB::table('heritage_discovery_log')
            ->select(
                'query_text',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('MAX(created_at) as last_searched')
            )
            ->where('created_at', '>=', $startDate)
            ->where('result_count', 0)
            ->whereNotNull('query_text')
            ->where('query_text', '!=', '')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy('query_text')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending queries (increased search volume).
     */
    public function getTrendingQueries(?int $institutionId = null, int $limit = 10): Collection
    {
        $thisWeek = date('Y-m-d', strtotime('-7 days'));
        $lastWeek = date('Y-m-d', strtotime('-14 days'));

        // This week's queries
        $thisWeekQueries = DB::table('heritage_discovery_log')
            ->select('query_text', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $thisWeek)
            ->whereNotNull('query_text')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy('query_text')
            ->pluck('count', 'query_text');

        // Last week's queries
        $lastWeekQueries = DB::table('heritage_discovery_log')
            ->select('query_text', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $lastWeek)
            ->where('created_at', '<', $thisWeek)
            ->whereNotNull('query_text')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy('query_text')
            ->pluck('count', 'query_text');

        // Calculate growth
        $trending = [];
        foreach ($thisWeekQueries as $query => $thisCount) {
            $lastCount = $lastWeekQueries[$query] ?? 0;
            $growth = $lastCount > 0 ? (($thisCount - $lastCount) / $lastCount) * 100 : 100;

            if ($growth > 0 && $thisCount >= 3) {
                $trending[] = [
                    'query' => $query,
                    'this_week' => $thisCount,
                    'last_week' => $lastCount,
                    'growth_percent' => round($growth, 1),
                ];
            }
        }

        // Sort by growth and limit
        usort($trending, fn ($a, $b) => $b['growth_percent'] <=> $a['growth_percent']);

        return collect(array_slice($trending, 0, $limit));
    }

    /**
     * Get search-to-click conversion analysis.
     */
    public function getConversionAnalysis(?int $institutionId = null, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $stats = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->selectRaw('
                COUNT(*) as total_searches,
                SUM(CASE WHEN result_count > 0 THEN 1 ELSE 0 END) as with_results,
                SUM(CASE WHEN click_count > 0 THEN 1 ELSE 0 END) as with_clicks,
                AVG(click_count) as avg_clicks_per_search,
                AVG(CASE WHEN result_count > 0 THEN click_count / result_count ELSE 0 END) as avg_click_rate
            ')
            ->first();

        return [
            'total_searches' => (int) $stats->total_searches,
            'searches_with_results' => (int) $stats->with_results,
            'searches_with_clicks' => (int) $stats->with_clicks,
            'result_rate' => $stats->total_searches > 0
                ? round(($stats->with_results / $stats->total_searches) * 100, 1)
                : 0,
            'conversion_rate' => $stats->with_results > 0
                ? round(($stats->with_clicks / $stats->with_results) * 100, 1)
                : 0,
            'avg_clicks' => round($stats->avg_clicks_per_search ?? 0, 2),
        ];
    }

    /**
     * Get search patterns by time.
     */
    public function getSearchPatternsByTime(?int $institutionId = null, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // By hour of day
        $byHour = DB::table('heritage_discovery_log')
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // By day of week
        $byDayOfWeek = DB::table('heritage_discovery_log')
            ->select(DB::raw('DAYOFWEEK(created_at) as dow'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy(DB::raw('DAYOFWEEK(created_at)'))
            ->orderBy('dow')
            ->pluck('count', 'dow')
            ->toArray();

        $dayNames = [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'];
        $byDayLabeled = [];
        foreach ($byDayOfWeek as $dow => $count) {
            $byDayLabeled[$dayNames[$dow]] = $count;
        }

        return [
            'by_hour' => $byHour,
            'by_day_of_week' => $byDayLabeled,
        ];
    }

    /**
     * Update daily search analytics aggregates.
     */
    public function updateDailyAggregates(?string $date = null): void
    {
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));

        $patterns = DB::table('heritage_discovery_log')
            ->select(
                'institution_id',
                'query_text',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('SUM(click_count) as click_count'),
                DB::raw('SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) as zero_result_count'),
                DB::raw('AVG(result_count) as avg_results')
            )
            ->whereDate('created_at', $date)
            ->whereNotNull('query_text')
            ->groupBy('institution_id', 'query_text')
            ->get();

        foreach ($patterns as $pattern) {
            DB::table('heritage_analytics_search')->updateOrInsert(
                [
                    'institution_id' => $pattern->institution_id,
                    'date' => $date,
                    'query_pattern' => $pattern->query_text,
                ],
                [
                    'search_count' => $pattern->search_count,
                    'click_count' => $pattern->click_count,
                    'zero_result_count' => $pattern->zero_result_count,
                    'avg_results' => round($pattern->avg_results, 2),
                    'conversion_rate' => $pattern->search_count > 0
                        ? round($pattern->click_count / $pattern->search_count, 4)
                        : 0,
                ]
            );
        }
    }
}
