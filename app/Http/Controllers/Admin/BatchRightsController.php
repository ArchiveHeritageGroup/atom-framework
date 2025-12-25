<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InformationObjectSelectService;
use App\Services\RightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchRightsController extends Controller
{
    public function __construct(
        private InformationObjectSelectService $selectService,
        private RightsService $rightsService
    ) {}

    /**
     * Show batch rights form.
     */
    public function index(Request $request): View
    {
        $culture = $request->get('culture', 'en');

        return view('admin.rights.batch', [
            'records' => $this->selectService->getSelectOptions($culture),
            'rightsBasis' => $this->rightsService->getRightsBasisOptions($culture),
            'copyrightStatus' => $this->rightsService->getCopyrightStatusOptions($culture),
            'rightsHolders' => $this->rightsService->getRightsHolders($culture),
            'culture' => $culture,
        ]);
    }

    /**
     * Process batch rights application.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'information_object_id' => 'nullable|integer',
            'object_ids' => 'nullable|string',
            'scope' => 'required|in:selected,children',
            'include_parent' => 'nullable',
            'rights_basis_id' => 'nullable|integer',
            'copyright_status_id' => 'nullable|integer',
            'rights_holder_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'rights_note' => 'nullable|string|max:65535',
            'copyright_note' => 'nullable|string|max:65535',
            'restriction' => 'nullable|integer',
        ]);

        // Resolve target object IDs
        $targetIds = $this->resolveTargetIds($validated);

        if (empty($targetIds)) {
            return back()
                ->withInput()
                ->withErrors(['record' => 'Please select at least one record.']);
        }

        // Prepare rights data
        $rightsData = [
            'rights_basis_id' => $validated['rights_basis_id'] ?? null,
            'copyright_status_id' => $validated['copyright_status_id'] ?? null,
            'rights_holder_id' => $validated['rights_holder_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'rights_note' => $validated['rights_note'] ?? null,
            'copyright_note' => $validated['copyright_note'] ?? null,
            'restriction' => $validated['restriction'] ?? 1,
            'culture' => $request->get('culture', 'en'),
        ];

        // Apply rights
        $result = $this->rightsService->applyBatchRights($targetIds, $rightsData);

        // Build response message
        $message = "Batch complete: {$result['success']} successful, {$result['failed']} failed";

        if ($result['failed'] > 0) {
            return back()
                ->with('warning', $message)
                ->with('errors_list', $result['errors']);
        }

        return back()->with('success', $message);
    }

    /**
     * Resolve which object IDs to apply rights to.
     */
    private function resolveTargetIds(array $validated): array
    {
        $ids = [];

        // Option A: Selected from dropdown
        if (!empty($validated['information_object_id'])) {
            $parentId = (int) $validated['information_object_id'];

            if ($validated['scope'] === 'selected' || !empty($validated['include_parent'])) {
                $ids[] = $parentId;
            }

            if ($validated['scope'] === 'children') {
                $children = $this->selectService->getChildRecords($parentId);
                $ids = array_merge($ids, $children->pluck('id')->toArray());
            }
        }

        // Option B: Manual IDs
        if (!empty($validated['object_ids'])) {
            $manualIds = array_filter(
                array_map('intval', explode(',', $validated['object_ids']))
            );
            $ids = array_merge($ids, $manualIds);
        }

        return array_unique($ids);
    }
}
