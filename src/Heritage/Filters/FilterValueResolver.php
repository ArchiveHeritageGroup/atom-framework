<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Filters;

use AtomFramework\Heritage\Repositories\FilterRepository;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Filter Value Resolver.
 *
 * Resolves filter values from various sources:
 * - Taxonomies (terms)
 * - Authorities (actors, places)
 * - Fields (dates, repositories)
 * - Custom values (from heritage_filter_value)
 */
class FilterValueResolver
{
    private FilterRepository $filterRepo;
    private string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->filterRepo = new FilterRepository();
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
     * Resolve values for a filter.
     */
    public function resolveValues(
        string $sourceType,
        ?string $sourceReference,
        int $institutionFilterId,
        ?int $institutionId = null,
        int $limit = 10
    ): array {
        return match ($sourceType) {
            'taxonomy' => $this->resolveTaxonomyValues($sourceReference, $institutionId, $limit),
            'authority' => $this->resolveAuthorityValues($sourceReference, $institutionId, $limit),
            'field' => $this->resolveFieldValues($sourceReference, $institutionId, $limit),
            'custom' => $this->resolveCustomValues($institutionFilterId, $limit),
            default => [],
        };
    }

    /**
     * Resolve values from taxonomy.
     */
    private function resolveTaxonomyValues(?string $taxonomyCode, ?int $institutionId, int $limit): array
    {
        if (!$taxonomyCode) {
            return [];
        }

        // Map friendly codes to taxonomy IDs
        $taxonomyMap = [
            'contentType' => 52,    // Media type taxonomy
            'subject' => 35,        // Subject taxonomy
            'language' => 6,        // Language taxonomy
            'glamSector' => 550,    // GLAM sector taxonomy (if exists)
            'levelOfDescription' => 34,
        ];

        $taxonomyId = $taxonomyMap[$taxonomyCode] ?? null;

        if (!$taxonomyId) {
            // Try to find by name
            $taxonomy = DB::table('taxonomy_i18n')
                ->where('name', 'LIKE', '%' . $taxonomyCode . '%')
                ->where('culture', $this->culture)
                ->first();

            if ($taxonomy) {
                $taxonomyId = $taxonomy->id;
            }
        }

        if (!$taxonomyId) {
            return [];
        }

        // Get terms with usage counts
        // Note: In AtoM, publication status is stored in 'status' table
        // type_id=158 is PUBLICATION_STATUS, status_id=160 is PUBLISHED
        $culture = $this->culture;
        $query = DB::table('term as t')
            ->join('term_i18n as ti', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->leftJoin('object_term_relation as otr', 't.id', '=', 'otr.term_id')
            ->leftJoin('information_object as io', 'otr.object_id', '=', 'io.id')
            ->leftJoin('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('t.taxonomy_id', $taxonomyId)
            ->where('st.status_id', 160) // Published only
            ->groupBy('t.id', 'ti.name')
            ->havingRaw('COUNT(DISTINCT io.id) > 0')
            ->orderByRaw('COUNT(DISTINCT io.id) DESC')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->select(
            't.id as value',
            'ti.name as label',
            DB::raw('COUNT(DISTINCT io.id) as count')
        )
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'label' => $row->label,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Resolve values from authority records.
     */
    private function resolveAuthorityValues(?string $authorityType, ?int $institutionId, int $limit): array
    {
        if (!$authorityType) {
            return [];
        }

        if ($authorityType === 'actor') {
            return $this->resolveActorValues($institutionId, $limit);
        }

        if ($authorityType === 'place') {
            return $this->resolvePlaceValues($institutionId, $limit);
        }

        return [];
    }

    /**
     * Resolve actor (creator) values.
     */
    private function resolveActorValues(?int $institutionId, int $limit): array
    {
        $culture = $this->culture;
        $query = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->join('relation as r', function ($join) {
                $join->on('a.id', '=', 'r.object_id')
                    ->orOn('a.id', '=', 'r.subject_id');
            })
            ->join('information_object as io', function ($join) {
                $join->on('r.subject_id', '=', 'io.id')
                    ->orOn('r.object_id', '=', 'io.id');
            })
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', 160)
            ->whereNotNull('ai.authorized_form_of_name')
            ->groupBy('a.id', 'ai.authorized_form_of_name')
            ->havingRaw('COUNT(DISTINCT io.id) > 0')
            ->orderByRaw('COUNT(DISTINCT io.id) DESC')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->select(
            'a.id as value',
            'ai.authorized_form_of_name as label',
            DB::raw('COUNT(DISTINCT io.id) as count')
        )
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'label' => $row->label,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Resolve place values.
     */
    private function resolvePlaceValues(?int $institutionId, int $limit): array
    {
        // Places are typically stored as terms in a places taxonomy
        // or as access points
        $culture = $this->culture;
        $query = DB::table('term as t')
            ->join('term_i18n as ti', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->join('object_term_relation as otr', 't.id', '=', 'otr.term_id')
            ->join('information_object as io', 'otr.object_id', '=', 'io.id')
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('t.taxonomy_id', 42) // Place access points taxonomy
            ->where('st.status_id', 160)
            ->groupBy('t.id', 'ti.name')
            ->havingRaw('COUNT(DISTINCT io.id) > 0')
            ->orderByRaw('COUNT(DISTINCT io.id) DESC')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->select(
            't.id as value',
            'ti.name as label',
            DB::raw('COUNT(DISTINCT io.id) as count')
        )
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'label' => $row->label,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Resolve values from fields.
     */
    private function resolveFieldValues(?string $fieldName, ?int $institutionId, int $limit): array
    {
        if (!$fieldName) {
            return [];
        }

        return match ($fieldName) {
            'date' => $this->resolveDateValues($institutionId, $limit),
            'repository' => $this->resolveRepositoryValues($institutionId, $limit),
            default => [],
        };
    }

    /**
     * Resolve date/time period values.
     */
    private function resolveDateValues(?int $institutionId, int $limit): array
    {
        // Group dates into decades for meaningful browsing
        $query = DB::table('information_object as io')
            ->join('event as e', 'io.id', '=', 'e.object_id')
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', 160)
            ->whereNotNull('e.start_date')
            ->where('e.start_date', '!=', '')
            ->groupBy(DB::raw('FLOOR(YEAR(e.start_date) / 10) * 10'))
            ->orderByRaw('FLOOR(YEAR(e.start_date) / 10) * 10 DESC')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('io.repository_id', $institutionId);
        }

        return $query->select(
            DB::raw('CONCAT(FLOOR(YEAR(e.start_date) / 10) * 10, "s") as value'),
            DB::raw('CONCAT(FLOOR(YEAR(e.start_date) / 10) * 10, "-", FLOOR(YEAR(e.start_date) / 10) * 10 + 9) as label'),
            DB::raw('COUNT(DISTINCT io.id) as count')
        )
            ->get()
            ->map(fn ($row) => [
                'value' => $row->value,
                'label' => $row->label,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Resolve repository (collection) values.
     */
    private function resolveRepositoryValues(?int $institutionId, int $limit): array
    {
        // In AtoM, repository inherits from Actor, so name is in actor_i18n
        $culture = $this->culture;
        $query = DB::table('repository as r')
            ->join('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('r.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->join('information_object as io', 'r.id', '=', 'io.repository_id')
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', 160)
            ->groupBy('r.id', 'ai.authorized_form_of_name')
            ->orderByRaw('COUNT(io.id) DESC')
            ->limit($limit);

        if ($institutionId !== null) {
            $query->where('r.id', $institutionId);
        }

        return $query->select(
            'r.id as value',
            'ai.authorized_form_of_name as label',
            DB::raw('COUNT(io.id) as count')
        )
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->value,
                'label' => $row->label ?: 'Unknown',
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Resolve custom filter values.
     */
    private function resolveCustomValues(int $institutionFilterId, int $limit): array
    {
        $values = $this->filterRepo->getFilterValues($institutionFilterId, true);

        return $values->take($limit)->map(function ($row) {
            $filterQuery = $row->filter_query ? json_decode($row->filter_query, true) : null;

            // If filter_query exists, calculate count
            $count = 0;
            if ($filterQuery) {
                $count = $this->calculateCustomValueCount($filterQuery);
            }

            return [
                'value' => $row->value_code,
                'label' => $row->display_label,
                'count' => $count,
            ];
        })->toArray();
    }

    /**
     * Calculate count for custom filter query.
     */
    private function calculateCustomValueCount(array $filterQuery): int
    {
        // filterQuery structure: { "field": "level_of_description_id", "operator": "=", "value": 123 }
        if (empty($filterQuery['field'])) {
            return 0;
        }

        $query = DB::table('information_object as io')
            ->join('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158);
            })
            ->where('st.status_id', 160);

        $operator = $filterQuery['operator'] ?? '=';
        $field = 'io.' . $filterQuery['field'];
        $value = $filterQuery['value'] ?? null;

        if ($value !== null) {
            $query->where($field, $operator, $value);
        }

        return $query->count();
    }

    /**
     * Get all values for a filter (for search sidebar).
     */
    public function getAllValues(
        string $sourceType,
        ?string $sourceReference,
        int $institutionFilterId,
        ?int $institutionId = null
    ): array {
        // Use a higher limit for search sidebar
        return $this->resolveValues($sourceType, $sourceReference, $institutionFilterId, $institutionId, 100);
    }
}
