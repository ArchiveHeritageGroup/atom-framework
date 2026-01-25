<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Controllers\Api;

use AtomFramework\Heritage\Analytics\AlertService;
use AtomFramework\Heritage\Analytics\AnalyticsService;
use AtomFramework\Heritage\Analytics\SearchIntelligenceService;

/**
 * Analytics Controller.
 *
 * Handles analytics and learning API requests.
 */
class AnalyticsController
{
    private AnalyticsService $analyticsService;
    private SearchIntelligenceService $searchService;
    private AlertService $alertService;

    public function __construct()
    {
        $this->analyticsService = new AnalyticsService();
        $this->searchService = new SearchIntelligenceService();
        $this->alertService = new AlertService();
    }

    // ========================================================================
    // Dashboard
    // ========================================================================

    /**
     * Get analytics dashboard.
     */
    public function getDashboard(?int $institutionId = null, int $days = 30): array
    {
        try {
            $data = $this->analyticsService->getDashboard($institutionId, $days);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get specific metric history.
     */
    public function getMetricHistory(string $metricType, ?int $institutionId = null, int $days = 30): array
    {
        try {
            $data = $this->analyticsService->getMetricHistory($metricType, $institutionId, $days);

            return [
                'success' => true,
                'data' => $data->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Search Intelligence
    // ========================================================================

    /**
     * Get popular queries.
     */
    public function getPopularQueries(?int $institutionId = null, int $days = 30, int $limit = 20): array
    {
        try {
            $queries = $this->searchService->getPopularQueries($institutionId, $days, $limit);

            return [
                'success' => true,
                'data' => $queries->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get zero-result queries.
     */
    public function getZeroResultQueries(?int $institutionId = null, int $days = 30, int $limit = 20): array
    {
        try {
            $queries = $this->searchService->getZeroResultQueries($institutionId, $days, $limit);

            return [
                'success' => true,
                'data' => $queries->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get trending queries.
     */
    public function getTrendingQueries(?int $institutionId = null, int $limit = 10): array
    {
        try {
            $queries = $this->searchService->getTrendingQueries($institutionId, $limit);

            return [
                'success' => true,
                'data' => $queries->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get conversion analysis.
     */
    public function getConversionAnalysis(?int $institutionId = null, int $days = 30): array
    {
        try {
            $data = $this->searchService->getConversionAnalysis($institutionId, $days);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get search patterns by time.
     */
    public function getSearchPatterns(?int $institutionId = null, int $days = 30): array
    {
        try {
            $data = $this->searchService->getSearchPatternsByTime($institutionId, $days);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Alerts
    // ========================================================================

    /**
     * Get active alerts.
     */
    public function getAlerts(?int $institutionId = null, array $params = []): array
    {
        try {
            $result = $this->alertService->getActiveAlerts($institutionId, $params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get alert counts.
     */
    public function getAlertCounts(?int $institutionId = null): array
    {
        try {
            $counts = $this->alertService->getAlertCounts($institutionId);

            return [
                'success' => true,
                'data' => $counts,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Dismiss alert.
     */
    public function dismissAlert(int $id, ?int $userId = null): array
    {
        try {
            $success = $this->alertService->dismiss($id, $userId);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mark alert as read.
     */
    public function markAlertRead(int $id): array
    {
        try {
            $success = $this->alertService->markRead($id);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate system alerts.
     */
    public function generateAlerts(?int $institutionId = null): array
    {
        try {
            $count = $this->alertService->generateSystemAlerts($institutionId);

            return [
                'success' => true,
                'data' => ['alerts_created' => $count],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
