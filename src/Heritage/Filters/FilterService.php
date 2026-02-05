<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Filters;

use AtomFramework\Heritage\Repositories\FilterRepository;
use Illuminate\Support\Collection;

/**
 * Filter Service.
 *
 * Business logic for heritage filter operations.
 * Manages filter types, institution filters, and filter values.
 */
class FilterService
{
    private FilterRepository $repository;
    private FilterValueResolver $valueResolver;
    private string $culture = 'en';

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->repository = new FilterRepository();
        $this->valueResolver = new FilterValueResolver($culture);
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        $this->valueResolver->setCulture($culture);

        return $this;
    }

    /**
     * Get current culture.
     */
    public function getCulture(): string
    {
        return $this->culture;
    }

    // ========================================================================
    // Filter Types
    // ========================================================================

    /**
     * Get all filter types.
     */
    public function getAllFilterTypes(): Collection
    {
        return $this->repository->getAllFilterTypes();
    }

    /**
     * Get filter type by code.
     */
    public function getFilterTypeByCode(string $code): ?object
    {
        return $this->repository->getFilterTypeByCode($code);
    }

    /**
     * Create custom filter type.
     */
    public function createFilterType(array $data): array
    {
        $errors = FilterTypeRegistry::validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $id = FilterTypeRegistry::register($data);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete custom filter type.
     */
    public function deleteFilterType(string $code): bool
    {
        return FilterTypeRegistry::unregister($code);
    }

    // ========================================================================
    // Institution Filters
    // ========================================================================

    /**
     * Get enabled filters for institution.
     */
    public function getEnabledFilters(?int $institutionId = null, bool $landingOnly = false): Collection
    {
        return $this->repository->getEnabledFilters($institutionId, $landingOnly);
    }

    /**
     * Get filters with resolved values.
     */
    public function getFiltersWithValues(?int $institutionId = null, bool $landingOnly = false): array
    {
        $filters = $this->repository->getEnabledFilters($institutionId, $landingOnly);

        return $filters->map(function ($filter) use ($institutionId) {
            $limit = $filter->max_items_landing ?? 6;

            $values = $this->valueResolver->resolveValues(
                $filter->source_type,
                $filter->source_reference,
                $filter->id,
                $institutionId,
                $limit
            );

            return [
                'id' => $filter->id,
                'code' => $filter->code,
                'label' => $filter->display_name ?? $filter->type_name,
                'icon' => $filter->display_icon ?? $filter->type_icon,
                'source_type' => $filter->source_type,
                'is_hierarchical' => (bool) $filter->is_hierarchical,
                'allow_multiple' => (bool) $filter->allow_multiple,
                'show_on_landing' => (bool) $filter->show_on_landing,
                'show_in_search' => (bool) $filter->show_in_search,
                'values' => $values,
            ];
        })->toArray();
    }

    /**
     * Get filter by ID with values.
     */
    public function getFilterById(int $id, ?int $institutionId = null): ?array
    {
        $filter = $this->repository->getInstitutionFilterById($id);
        if (!$filter) {
            return null;
        }

        $values = $this->valueResolver->getAllValues(
            $filter->source_type,
            $filter->source_reference,
            $filter->id,
            $institutionId
        );

        return [
            'id' => $filter->id,
            'code' => $filter->code,
            'label' => $filter->display_name ?? $filter->type_name,
            'icon' => $filter->display_icon ?? $filter->type_icon,
            'source_type' => $filter->source_type,
            'is_hierarchical' => (bool) $filter->is_hierarchical,
            'allow_multiple' => (bool) $filter->allow_multiple,
            'show_on_landing' => (bool) $filter->show_on_landing,
            'show_in_search' => (bool) $filter->show_in_search,
            'max_items_landing' => $filter->max_items_landing,
            'display_order' => $filter->display_order,
            'values' => $values,
        ];
    }

    /**
     * Create institution filter.
     */
    public function createInstitutionFilter(array $data): int
    {
        return $this->repository->createInstitutionFilter($data);
    }

    /**
     * Update institution filter.
     */
    public function updateInstitutionFilter(int $id, array $data): bool
    {
        return $this->repository->updateInstitutionFilter($id, $data);
    }

    /**
     * Delete institution filter.
     */
    public function deleteInstitutionFilter(int $id): bool
    {
        return $this->repository->deleteInstitutionFilter($id);
    }

    /**
     * Reorder institution filters.
     */
    public function reorderFilters(array $filterOrders): void
    {
        $this->repository->reorderFilters($filterOrders);
    }

    /**
     * Enable/disable filter.
     */
    public function toggleFilter(int $id, bool $enabled): bool
    {
        return $this->repository->updateInstitutionFilter($id, ['is_enabled' => $enabled ? 1 : 0]);
    }

    // ========================================================================
    // Filter Values (Custom)
    // ========================================================================

    /**
     * Get custom filter values.
     */
    public function getCustomValues(int $institutionFilterId): Collection
    {
        return $this->repository->getFilterValues($institutionFilterId, false);
    }

    /**
     * Create custom filter value.
     */
    public function createFilterValue(array $data): int
    {
        return $this->repository->createFilterValue($data);
    }

    /**
     * Update custom filter value.
     */
    public function updateFilterValue(int $id, array $data): bool
    {
        return $this->repository->updateFilterValue($id, $data);
    }

    /**
     * Delete custom filter value.
     */
    public function deleteFilterValue(int $id): bool
    {
        return $this->repository->deleteFilterValue($id);
    }

    // ========================================================================
    // Search Integration
    // ========================================================================

    /**
     * Build filter query conditions for search.
     */
    public function buildFilterConditions(array $appliedFilters, ?int $institutionId = null): array
    {
        $conditions = [];
        $filters = $this->getEnabledFilters($institutionId);

        foreach ($appliedFilters as $code => $values) {
            $filter = $filters->firstWhere('code', $code);
            if (!$filter) {
                continue;
            }

            $condition = $this->buildConditionForFilter($filter, (array) $values);
            if ($condition) {
                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    /**
     * Build condition for a single filter.
     */
    private function buildConditionForFilter(object $filter, array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        return match ($filter->source_type) {
            'taxonomy' => $this->buildTaxonomyCondition($filter, $values),
            'authority' => $this->buildAuthorityCondition($filter, $values),
            'field' => $this->buildFieldCondition($filter, $values),
            'custom' => $this->buildCustomCondition($filter, $values),
            'entity_cache' => $this->buildEntityCacheCondition($filter, $values),
            default => null,
        };
    }

    /**
     * Build taxonomy filter condition.
     */
    private function buildTaxonomyCondition(object $filter, array $values): array
    {
        return [
            'type' => 'taxonomy',
            'join' => [
                'table' => 'object_term_relation',
                'on' => ['object_term_relation.object_id', '=', 'information_object.id'],
            ],
            'where' => [
                'field' => 'object_term_relation.term_id',
                'operator' => 'IN',
                'values' => $values,
            ],
        ];
    }

    /**
     * Build authority filter condition.
     */
    private function buildAuthorityCondition(object $filter, array $values): array
    {
        return [
            'type' => 'authority',
            'source_reference' => $filter->source_reference,
            'values' => $values,
        ];
    }

    /**
     * Build field filter condition.
     */
    private function buildFieldCondition(object $filter, array $values): array
    {
        $fieldMap = [
            'date' => 'event.start_date',
            'repository' => 'information_object.repository_id',
        ];

        $field = $fieldMap[$filter->source_reference] ?? null;
        if (!$field) {
            return ['type' => 'noop'];
        }

        // Handle date ranges (e.g., "1950s" -> 1950-1959)
        if ($filter->source_reference === 'date') {
            $dateConditions = [];
            foreach ($values as $value) {
                // Parse decade format "1950s" or range "1950-1959"
                if (preg_match('/^(\d{4})s$/', $value, $matches)) {
                    $startYear = (int) $matches[1];
                    $dateConditions[] = [
                        'start' => "{$startYear}-01-01",
                        'end' => ($startYear + 9) . '-12-31',
                    ];
                }
            }

            return [
                'type' => 'date_range',
                'ranges' => $dateConditions,
            ];
        }

        return [
            'type' => 'field',
            'where' => [
                'field' => $field,
                'operator' => count($values) > 1 ? 'IN' : '=',
                'values' => count($values) > 1 ? $values : $values[0],
            ],
        ];
    }

    /**
     * Build custom filter condition.
     */
    private function buildCustomCondition(object $filter, array $values): array
    {
        // Get filter value definitions
        $filterValues = $this->repository->getFilterValues($filter->id, true);

        $conditions = [];
        foreach ($values as $valueCode) {
            $filterValue = $filterValues->firstWhere('value_code', $valueCode);
            if ($filterValue && $filterValue->filter_query) {
                $query = json_decode($filterValue->filter_query, true);
                if ($query) {
                    $conditions[] = $query;
                }
            }
        }

        return [
            'type' => 'custom',
            'conditions' => $conditions,
        ];
    }

    /**
     * Build entity cache filter condition.
     *
     * Used for NER-extracted entity filters (ner_person, ner_organization, ner_place).
     *
     * @param object $filter Filter configuration object
     * @param array  $values Selected normalized entity values
     *
     * @return array Condition specification for search
     */
    private function buildEntityCacheCondition(object $filter, array $values): array
    {
        // Parse source_reference: "entity_cache:person" or just "person"
        $entityType = $filter->source_reference ?? 'person';
        if (str_contains($entityType, ':')) {
            $parts = explode(':', $entityType);
            $entityType = $parts[1] ?? 'person';
        }

        // Generate unique alias for join
        $alias = 'ec_' . preg_replace('/[^a-z]/', '', $entityType);

        return [
            'type' => 'entity_cache',
            'entity_type' => $entityType,
            'join' => [
                'table' => 'heritage_entity_cache',
                'alias' => $alias,
                'on' => "io.id = {$alias}.object_id",
                'conditions' => [
                    ['column' => "{$alias}.entity_type", 'operator' => '=', 'value' => $entityType],
                    ['column' => "{$alias}.confidence_score", 'operator' => '>=', 'value' => 0.70],
                ],
            ],
            'where' => [
                'column' => "{$alias}.normalized_value",
                'operator' => 'IN',
                'values' => $values,
            ],
        ];
    }
}
