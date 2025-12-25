<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InformationObjectSelectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Controller for searchable record select AJAX requests.
 */
class RecordSelectController extends Controller
{
    public function __construct(
        private InformationObjectSelectService $selectService
    ) {}

    /**
     * Search records by title or identifier.
     * GET /api/records/search?q=term&culture=en&limit=50
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');
        $culture = $request->get('culture', 'en');
        $limit = min((int) $request->get('limit', 50), 100);

        if (strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Search term must be at least 2 characters',
            ]);
        }

        $records = $this->selectService->searchRecords($search, $culture, $limit);

        return response()->json([
            'success' => true,
            'data' => $records,
            'count' => $records->count(),
        ]);
    }

    /**
     * Get all records for select dropdown.
     * GET /api/records/options?culture=en&repository_id=123
     */
    public function options(Request $request): JsonResponse
    {
        $culture = $request->get('culture', 'en');
        $repositoryId = $request->get('repository_id') ? (int) $request->get('repository_id') : null;

        $records = $this->selectService->getSelectOptions($culture, $repositoryId);

        return response()->json([
            'success' => true,
            'data' => $records,
            'count' => $records->count(),
        ]);
    }

    /**
     * Get child records for hierarchical selection.
     * GET /api/records/{parentId}/children?culture=en
     */
    public function children(Request $request, int $parentId): JsonResponse
    {
        $culture = $request->get('culture', 'en');

        $records = $this->selectService->getChildRecords($parentId, $culture);

        return response()->json([
            'success' => true,
            'data' => $records,
            'count' => $records->count(),
        ]);
    }
}
