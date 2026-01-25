<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Controllers\Api;

use AtomFramework\Heritage\Discovery\LearningService;
use AtomFramework\Heritage\Discovery\SearchOrchestrator;

/**
 * Discover Controller.
 *
 * Handles API requests for the discovery/search functionality.
 * Called by Symfony actions in the plugin.
 */
class DiscoverController
{
    private SearchOrchestrator $searchOrchestrator;
    private LearningService $learningService;
    private string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->searchOrchestrator = new SearchOrchestrator($culture);
        $this->learningService = new LearningService($culture);
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        $this->searchOrchestrator->setCulture($culture);
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
     * POST /heritage/api/discover
     *
     * Main search endpoint.
     *
     * @param array $params Search parameters:
     *                      - query: string - Natural language query
     *                      - filters: array - Applied filters
     *                      - page: int - Page number (default 1)
     *                      - limit: int - Results per page (default 20)
     *                      - institution_id: int|null - Institution filter
     *                      - culture: string - Language (default 'en')
     */
    public function search(array $params): array
    {
        try {
            $results = $this->searchOrchestrator->search($params);

            return [
                'success' => true,
                'data' => $results,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/api/autocomplete
     *
     * Autocomplete suggestions.
     */
    public function autocomplete(string $query, ?int $institutionId = null, int $limit = 10): array
    {
        try {
            $suggestions = $this->searchOrchestrator->autocomplete($query, $institutionId, $limit);

            return [
                'success' => true,
                'data' => $suggestions,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate search parameters.
     */
    public function validateParams(array $params): array
    {
        $errors = [];

        if (isset($params['page']) && (!is_numeric($params['page']) || $params['page'] < 1)) {
            $errors['page'] = 'Page must be a positive integer';
        }

        if (isset($params['limit'])) {
            if (!is_numeric($params['limit']) || $params['limit'] < 1 || $params['limit'] > 100) {
                $errors['limit'] = 'Limit must be between 1 and 100';
            }
        }

        if (isset($params['filters']) && !is_array($params['filters'])) {
            $errors['filters'] = 'Filters must be an array';
        }

        return $errors;
    }

    /**
     * POST /heritage/api/click
     *
     * Log a click on a search result for learning.
     *
     * @param array $params Click parameters:
     *                      - search_id: int - Search log ID
     *                      - item_id: int - Clicked item ID
     *                      - position: int - Position in results (1-indexed)
     *                      - time_to_click: int|null - Time to click in ms
     */
    public function logClick(array $params): array
    {
        try {
            $searchId = (int) ($params['search_id'] ?? 0);
            $itemId = (int) ($params['item_id'] ?? 0);
            $position = (int) ($params['position'] ?? 0);
            $timeToClick = isset($params['time_to_click']) ? (int) $params['time_to_click'] : null;

            if ($searchId <= 0 || $itemId <= 0 || $position <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid parameters: search_id, item_id, and position are required',
                ];
            }

            $clickId = $this->searchOrchestrator->logClick($searchId, $itemId, $position, $timeToClick);

            return [
                'success' => true,
                'data' => ['click_id' => $clickId],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/api/dwell
     *
     * Update dwell time for a click.
     *
     * @param array $params:
     *              - click_id: int - Click log ID
     *              - dwell_time: int - Dwell time in seconds
     */
    public function updateDwellTime(array $params): array
    {
        try {
            $clickId = (int) ($params['click_id'] ?? 0);
            $dwellTime = (int) ($params['dwell_time'] ?? 0);

            if ($clickId <= 0 || $dwellTime <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid parameters: click_id and dwell_time are required',
                ];
            }

            $this->learningService->updateDwellTime($clickId, $dwellTime);

            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/api/analytics
     *
     * Get search analytics summary.
     *
     * @param int|null $institutionId Institution ID
     * @param int $days Number of days to analyze
     */
    public function analytics(?int $institutionId = null, int $days = 30): array
    {
        try {
            $data = $this->learningService->getAnalytics($institutionId, $days);

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
}
