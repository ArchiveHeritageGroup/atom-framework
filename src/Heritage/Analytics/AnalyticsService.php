<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Analytics;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Analytics Service.
 *
 * Main analytics dashboard and metrics.
 */
class AnalyticsService
{
    /**
     * Get dashboard summary.
     */
    public function getDashboard(?int $institutionId = null, int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return [
            'overview' => $this->getOverviewMetrics($institutionId, $startDate),
            'search' => $this->getSearchMetrics($institutionId, $startDate),
            'content' => $this->getContentMetrics($institutionId, $startDate),
            'access' => $this->getAccessMetrics($institutionId, $startDate),
            'trends' => $this->getTrends($institutionId, $days),
        ];
    }

    /**
     * Get overview metrics.
     */
    private function getOverviewMetrics(?int $institutionId, string $startDate): array
    {
        // Total views
        $totalViews = DB::table('heritage_analytics_daily')
            ->where('date', '>=', $startDate)
            ->where('metric_type', 'page_views')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->sum('metric_value');

        // Total searches
        $totalSearches = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->count();

        // Total downloads
        $totalDownloads = DB::table('heritage_analytics_daily')
            ->where('date', '>=', $startDate)
            ->where('metric_type', 'downloads')
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->sum('metric_value');

        // Unique visitors (estimate from sessions)
        $uniqueVisitors = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->distinct('session_id')
            ->count('session_id');

        return [
            'total_views' => (int) $totalViews,
            'total_searches' => $totalSearches,
            'total_downloads' => (int) $totalDownloads,
            'unique_visitors' => $uniqueVisitors,
        ];
    }

    /**
     * Get search metrics.
     */
    private function getSearchMetrics(?int $institutionId, string $startDate): array
    {
        // Average results per search
        $avgResults = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->avg('result_count');

        // Zero result searches
        $zeroResults = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->where('result_count', 0)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->count();

        // Click-through rate
        $totalSearches = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->count();

        $searchesWithClicks = DB::table('heritage_discovery_log')
            ->where('created_at', '>=', $startDate)
            ->where('click_count', '>', 0)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->count();

        $ctr = $totalSearches > 0 ? round(($searchesWithClicks / $totalSearches) * 100, 1) : 0;

        return [
            'avg_results' => round($avgResults ?? 0, 1),
            'zero_result_rate' => $totalSearches > 0 ? round(($zeroResults / $totalSearches) * 100, 1) : 0,
            'click_through_rate' => $ctr,
        ];
    }

    /**
     * Get content metrics.
     */
    private function getContentMetrics(?int $institutionId, string $startDate): array
    {
        // Most viewed items
        $topViewed = DB::table('heritage_analytics_content')
            ->leftJoin('information_object', 'heritage_analytics_content.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('heritage_analytics_content.period_start', '>=', $startDate)
            ->select([
                'heritage_analytics_content.object_id',
                'information_object.slug',
                'information_object_i18n.title',
                DB::raw('SUM(heritage_analytics_content.view_count) as total_views'),
            ])
            ->groupBy('heritage_analytics_content.object_id', 'information_object.slug', 'information_object_i18n.title')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get();

        // Content with low engagement
        $lowEngagement = DB::table('heritage_content_quality')
            ->leftJoin('information_object', 'heritage_content_quality.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('heritage_content_quality.engagement_score', '<', 25)
            ->select([
                'heritage_content_quality.*',
                'information_object.slug',
                'information_object_i18n.title',
            ])
            ->orderBy('heritage_content_quality.engagement_score')
            ->limit(10)
            ->get();

        return [
            'top_viewed' => $topViewed,
            'low_engagement' => $lowEngagement,
        ];
    }

    /**
     * Get access metrics.
     */
    private function getAccessMetrics(?int $institutionId, string $startDate): array
    {
        // Access requests
        $pendingRequests = DB::table('heritage_access_request')
            ->where('status', 'pending')
            ->count();

        $approvalRate = 0;
        $decided = DB::table('heritage_access_request')
            ->where('created_at', '>=', $startDate)
            ->whereIn('status', ['approved', 'denied'])
            ->count();
        if ($decided > 0) {
            $approved = DB::table('heritage_access_request')
                ->where('created_at', '>=', $startDate)
                ->where('status', 'approved')
                ->count();
            $approvalRate = round(($approved / $decided) * 100, 1);
        }

        // POPIA flags
        $unresolvedPopia = DB::table('heritage_popia_flag')
            ->where('is_resolved', 0)
            ->count();

        // Active embargoes
        $activeEmbargoes = DB::table('heritage_embargo')
            ->where(function ($q) {
                $today = date('Y-m-d');
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->count();

        return [
            'pending_requests' => $pendingRequests,
            'approval_rate' => $approvalRate,
            'unresolved_popia' => $unresolvedPopia,
            'active_embargoes' => $activeEmbargoes,
        ];
    }

    /**
     * Get trends over time.
     */
    private function getTrends(?int $institutionId, int $days): array
    {
        $searches = DB::table('heritage_discovery_log')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $clicks = DB::table('heritage_discovery_click')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return [
            'searches' => $searches,
            'clicks' => $clicks,
        ];
    }

    /**
     * Record daily metric.
     */
    public function recordDailyMetric(
        string $metricType,
        float $value,
        ?int $institutionId = null,
        ?string $date = null
    ): void {
        $date = $date ?? date('Y-m-d');

        $existing = DB::table('heritage_analytics_daily')
            ->where('date', $date)
            ->where('metric_type', $metricType)
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId), fn ($q) => $q->whereNull('institution_id'))
            ->first();

        if ($existing) {
            DB::table('heritage_analytics_daily')
                ->where('id', $existing->id)
                ->update([
                    'previous_value' => $existing->metric_value,
                    'metric_value' => $value,
                    'change_percent' => $existing->metric_value > 0
                        ? round((($value - $existing->metric_value) / $existing->metric_value) * 100, 2)
                        : null,
                ]);
        } else {
            DB::table('heritage_analytics_daily')->insert([
                'institution_id' => $institutionId,
                'date' => $date,
                'metric_type' => $metricType,
                'metric_value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Get metric history.
     */
    public function getMetricHistory(
        string $metricType,
        ?int $institutionId = null,
        int $days = 30
    ): Collection {
        return DB::table('heritage_analytics_daily')
            ->where('metric_type', $metricType)
            ->where('date', '>=', date('Y-m-d', strtotime("-{$days} days")))
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId), fn ($q) => $q->whereNull('institution_id'))
            ->orderBy('date')
            ->get();
    }
}
