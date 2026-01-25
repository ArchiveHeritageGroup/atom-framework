<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Discovery Log Repository.
 *
 * Provides database access for heritage_discovery_log table.
 * Tracks search queries and analytics.
 */
class DiscoveryLogRepository
{
    /**
     * Log a search query.
     */
    public function log(array $data): int
    {
        if (isset($data['filters_applied']) && is_array($data['filters_applied'])) {
            $data['filters_applied'] = json_encode($data['filters_applied']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('heritage_discovery_log')->insertGetId($data);
    }

    /**
     * Get recent searches.
     */
    public function getRecentSearches(?int $institutionId = null, int $limit = 100): Collection
    {
        $query = DB::table('heritage_discovery_log');

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        }

        return $query->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                if ($row->filters_applied) {
                    $row->filters_applied = json_decode($row->filters_applied, true);
                }

                return $row;
            });
    }

    /**
     * Get popular searches (aggregated).
     */
    public function getPopularSearches(?int $institutionId = null, int $days = 30, int $limit = 20): Collection
    {
        $query = DB::table('heritage_discovery_log')
            ->select(
                'query_text',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('AVG(result_count) as avg_results')
            )
            ->whereNotNull('query_text')
            ->where('query_text', '!=', '')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->groupBy('query_text')
            ->orderByDesc('search_count')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        }

        return $query->get();
    }

    /**
     * Get search statistics.
     */
    public function getStats(?int $institutionId = null, int $days = 30): object
    {
        $query = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")));

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_searches,
            COUNT(DISTINCT session_id) as unique_sessions,
            AVG(result_count) as avg_results,
            AVG(search_duration_ms) as avg_duration_ms,
            SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) as zero_result_searches
        ')->first();

        return $stats ?? (object) [
            'total_searches' => 0,
            'unique_sessions' => 0,
            'avg_results' => 0,
            'avg_duration_ms' => 0,
            'zero_result_searches' => 0,
        ];
    }

    /**
     * Get searches by date (for charts).
     */
    public function getSearchesByDate(?int $institutionId = null, int $days = 30): Collection
    {
        $query = DB::table('heritage_discovery_log')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as search_count')
            )
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date');

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        }

        return $query->get();
    }

    /**
     * Cleanup old logs.
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        return DB::table('heritage_discovery_log')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days")))
            ->delete();
    }
}
