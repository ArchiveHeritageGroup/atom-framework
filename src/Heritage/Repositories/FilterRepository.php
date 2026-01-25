<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Filter Repository.
 *
 * Provides database access for heritage filter tables:
 * - heritage_filter_type
 * - heritage_institution_filter
 * - heritage_filter_value
 */
class FilterRepository
{
    // ========================================================================
    // Filter Types
    // ========================================================================

    /**
     * Get all filter types.
     */
    public function getAllFilterTypes(): Collection
    {
        return DB::table('heritage_filter_type')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get filter type by code.
     */
    public function getFilterTypeByCode(string $code): ?object
    {
        return DB::table('heritage_filter_type')
            ->where('code', $code)
            ->first();
    }

    /**
     * Get filter type by ID.
     */
    public function getFilterTypeById(int $id): ?object
    {
        return DB::table('heritage_filter_type')
            ->where('id', $id)
            ->first();
    }

    /**
     * Create filter type.
     */
    public function createFilterType(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('heritage_filter_type')->insertGetId($data);
    }

    /**
     * Update filter type.
     */
    public function updateFilterType(int $id, array $data): bool
    {
        return DB::table('heritage_filter_type')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete filter type (only non-system).
     */
    public function deleteFilterType(int $id): bool
    {
        return DB::table('heritage_filter_type')
            ->where('id', $id)
            ->where('is_system', 0)
            ->delete() > 0;
    }

    // ========================================================================
    // Institution Filters
    // ========================================================================

    /**
     * Get enabled filters for institution with type info.
     */
    public function getEnabledFilters(?int $institutionId = null, bool $landingOnly = false): Collection
    {
        $query = DB::table('heritage_institution_filter as hif')
            ->join('heritage_filter_type as hft', 'hif.filter_type_id', '=', 'hft.id')
            ->where('hif.is_enabled', 1);

        if ($institutionId !== null) {
            $query->where('hif.institution_id', $institutionId);
        } else {
            $query->whereNull('hif.institution_id');
        }

        if ($landingOnly) {
            $query->where('hif.show_on_landing', 1);
        }

        return $query->select(
            'hif.id',
            'hif.institution_id',
            'hif.filter_type_id',
            'hif.is_enabled',
            'hif.display_name',
            'hif.display_icon',
            'hif.display_order',
            'hif.show_on_landing',
            'hif.show_in_search',
            'hif.max_items_landing',
            'hif.is_hierarchical',
            'hif.allow_multiple',
            'hft.code',
            'hft.name as type_name',
            'hft.icon as type_icon',
            'hft.source_type',
            'hft.source_reference'
        )
            ->orderBy('hif.display_order')
            ->get();
    }

    /**
     * Get institution filter by ID.
     */
    public function getInstitutionFilterById(int $id): ?object
    {
        return DB::table('heritage_institution_filter as hif')
            ->join('heritage_filter_type as hft', 'hif.filter_type_id', '=', 'hft.id')
            ->where('hif.id', $id)
            ->select(
                'hif.*',
                'hft.code',
                'hft.name as type_name',
                'hft.icon as type_icon',
                'hft.source_type',
                'hft.source_reference'
            )
            ->first();
    }

    /**
     * Create institution filter.
     */
    public function createInstitutionFilter(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('heritage_institution_filter')->insertGetId($data);
    }

    /**
     * Update institution filter.
     */
    public function updateInstitutionFilter(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_institution_filter')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete institution filter.
     */
    public function deleteInstitutionFilter(int $id): bool
    {
        return DB::table('heritage_institution_filter')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Reorder institution filters.
     */
    public function reorderFilters(array $filterOrders): void
    {
        foreach ($filterOrders as $id => $order) {
            DB::table('heritage_institution_filter')
                ->where('id', $id)
                ->update([
                    'display_order' => $order,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    // ========================================================================
    // Filter Values
    // ========================================================================

    /**
     * Get filter values for an institution filter.
     */
    public function getFilterValues(int $institutionFilterId, bool $enabledOnly = true): Collection
    {
        $query = DB::table('heritage_filter_value')
            ->where('institution_filter_id', $institutionFilterId);

        if ($enabledOnly) {
            $query->where('is_enabled', 1);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get hierarchical filter values.
     */
    public function getHierarchicalFilterValues(int $institutionFilterId, ?int $parentId = null): Collection
    {
        $query = DB::table('heritage_filter_value')
            ->where('institution_filter_id', $institutionFilterId)
            ->where('is_enabled', 1);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get filter value by ID.
     */
    public function getFilterValueById(int $id): ?object
    {
        $result = DB::table('heritage_filter_value')
            ->where('id', $id)
            ->first();

        if ($result && $result->filter_query) {
            $result->filter_query = json_decode($result->filter_query, true);
        }

        return $result;
    }

    /**
     * Create filter value.
     */
    public function createFilterValue(array $data): int
    {
        if (isset($data['filter_query']) && is_array($data['filter_query'])) {
            $data['filter_query'] = json_encode($data['filter_query']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('heritage_filter_value')->insertGetId($data);
    }

    /**
     * Update filter value.
     */
    public function updateFilterValue(int $id, array $data): bool
    {
        if (isset($data['filter_query']) && is_array($data['filter_query'])) {
            $data['filter_query'] = json_encode($data['filter_query']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_filter_value')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete filter value.
     */
    public function deleteFilterValue(int $id): bool
    {
        return DB::table('heritage_filter_value')
            ->where('id', $id)
            ->delete() > 0;
    }
}
