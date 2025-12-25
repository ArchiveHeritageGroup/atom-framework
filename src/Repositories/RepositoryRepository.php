<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Repository Repository.
 *
 * Provides access to archival repositories.
 * Replaces QubitRepository with Laravel Query Builder.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class RepositoryRepository
{
    /**
     * Search repositories with filters.
     */
    public function search(array $filters = [], string $culture = 'en'): Collection
    {
        $query = DB::table('repository as r')
            ->join('actor as a', 'r.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->leftJoin('repository_i18n as ri18n', function ($join) use ($culture) {
                $join->on('r.id', '=', 'ri18n.id')
                     ->where('ri18n.culture', $culture);
            })
            ->where('o.class_name', 'QubitRepository')
            ->whereNotNull('a.parent_id')
            ->select(
                'r.id',
                'r.identifier',
                'r.desc_status_id as descStatusId',
                'r.desc_detail_id as descDetailId',
                'r.desc_identifier as descIdentifier',
                'i18n.authorized_form_of_name as name',
                'ri18n.geocultural_context as geoculturalContext',
                'ri18n.collecting_policies as collectingPolicies',
                'ri18n.buildings',
                'ri18n.holdings',
                'ri18n.finding_aids as findingAids',
                'ri18n.opening_times as openingTimes',
                'ri18n.access_conditions as accessConditions',
                'ri18n.disabled_access as disabledAccess',
                'ri18n.research_services as researchServices',
                'ri18n.reproduction_services as reproductionServices',
                'ri18n.public_facilities as publicFacilities',
                'ri18n.desc_institution_identifier as descInstitutionIdentifier',
                'ri18n.desc_rules as descRules',
                'ri18n.desc_sources as descSources',
                'ri18n.desc_revision_history as descRevisionHistory',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            );

        return $query->get()->map(fn ($item) => (object) $item);
    }

    /**
     * Count total repositories.
     */
    public function count(): int
    {
        return DB::table('repository as r')
            ->join('actor as a', 'r.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->where('o.class_name', 'QubitRepository')
            ->whereNotNull('a.parent_id')
            ->count();
    }
}
