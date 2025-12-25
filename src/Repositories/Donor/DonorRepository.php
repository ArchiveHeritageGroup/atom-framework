<?php

declare(strict_types=1);

namespace AtomFramework\Repositories\Donor;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class DonorRepository
{
    protected string $table = 'donor';

    /**
     * Get paginated donors with filters
     */
    public function browse(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = DB::table('donor')
            ->join('actor', 'donor.id', '=', 'actor.id')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->leftJoin('contact_information', function ($join) {
                $join->on('actor.id', '=', 'contact_information.actor_id')
                    ->where('contact_information.primary_contact', '=', 1);
            })
            ->leftJoin('repository', 'donor.repository_id', '=', 'repository.id')
            ->leftJoin('actor as repo_actor', 'repository.id', '=', 'repo_actor.id')
            ->leftJoin('actor_i18n as repo_i18n', function ($join) {
                $join->on('repo_actor.id', '=', 'repo_i18n.id')
                    ->where('repo_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select([
                'donor.id',
                'actor.entity_type_id',
                'actor.created_at',
                'actor.updated_at',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.history',
                'contact_information.email',
                'contact_information.telephone',
                'contact_information.city',
                'contact_information.country_code',
                'repo_i18n.authorized_form_of_name as repository_name',
                DB::raw('(SELECT COUNT(*) FROM donor_agreement WHERE donor_agreement.donor_id = donor.id) as agreement_count'),
                DB::raw('(SELECT COUNT(*) FROM accession WHERE accession.donor_id = donor.id) as accession_count'),
            ])
            ->where('actor_i18n.culture', CultureHelper::getCulture());

        // Apply filters
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('actor_i18n.authorized_form_of_name', 'LIKE', $search)
                    ->orWhere('contact_information.email', 'LIKE', $search)
                    ->orWhere('contact_information.city', 'LIKE', $search);
            });
        }

        if (!empty($filters['repository_id'])) {
            $query->where('donor.repository_id', $filters['repository_id']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('actor.entity_type_id', $filters['entity_type']);
        }

        // Sorting
        $sortColumn = $filters['sort'] ?? 'name';
        $sortDir = $filters['dir'] ?? 'ASC';
        
        $sortMap = [
            'name' => 'actor_i18n.authorized_form_of_name',
            'created' => 'actor.created_at',
            'updated' => 'actor.updated_at',
            'repository' => 'repo_i18n.authorized_form_of_name',
        ];

        if (isset($sortMap[$sortColumn])) {
            $query->orderBy($sortMap[$sortColumn], $sortDir);
        }

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $donors = $query->offset($offset)->limit($perPage)->get();

        return [
            'donors' => $donors,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get donor by ID with full details
     */
    public function find(int $id): ?object
    {
        $donor = DB::table('donor')
            ->join('actor', 'donor.id', '=', 'actor.id')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->leftJoin('term', 'actor.entity_type_id', '=', 'term.id')
            ->leftJoin('term_i18n', function ($join) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select([
                'donor.*',
                'actor.entity_type_id',
                'actor.description_status_id',
                'actor.description_detail_id',
                'actor.source_culture',
                'actor.created_at',
                'actor.updated_at',
                'actor_i18n.authorized_form_of_name as name',
                'actor_i18n.dates_of_existence',
                'actor_i18n.history',
                'actor_i18n.places',
                'actor_i18n.legal_status',
                'actor_i18n.functions',
                'actor_i18n.mandates',
                'actor_i18n.internal_structures',
                'actor_i18n.general_context',
                'actor_i18n.institution_responsible_identifier',
                'actor_i18n.rules',
                'actor_i18n.sources',
                'actor_i18n.revision_history',
                'term_i18n.name as entity_type_name',
            ])
            ->where('donor.id', $id)
            ->where('actor_i18n.culture', CultureHelper::getCulture())
            ->first();

        if (!$donor) {
            return null;
        }

        // Get contact information
        $donor->contacts = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($join) {
                $join->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->where('contact_information.actor_id', $id)
            ->get();

        // Get agreements
        $donor->agreements = DB::table('donor_agreement')
            ->leftJoin('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id')
            ->select([
                'donor_agreement.*',
                'agreement_type.name as type_name',
            ])
            ->where('donor_agreement.donor_id', $id)
            ->orderBy('donor_agreement.agreement_date', 'DESC')
            ->get();

        // Get accessions
        $donor->accessions = DB::table('accession')
            ->leftJoin('accession_i18n', function ($join) {
                $join->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select([
                'accession.id',
                'accession.identifier',
                'accession.date',
                'accession_i18n.title',
            ])
            ->where('accession.donor_id', $id)
            ->orderBy('accession.date', 'DESC')
            ->get();

        // Get other names
        $donor->other_names = DB::table('other_name')
            ->join('other_name_i18n', function ($join) {
                $join->on('other_name.id', '=', 'other_name_i18n.id')
                    ->where('other_name_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('term', 'other_name.type_id', '=', 'term.id')
            ->leftJoin('term_i18n as type_i18n', function ($join) {
                $join->on('term.id', '=', 'type_i18n.id')
                    ->where('type_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select([
                'other_name_i18n.name',
                'other_name_i18n.note',
                'other_name_i18n.dates',
                'type_i18n.name as type_name',
            ])
            ->where('other_name.object_id', $id)
            ->get();

        // Get notes
        $donor->notes = DB::table('note')
            ->join('note_i18n', function ($join) {
                $join->on('note.id', '=', 'note_i18n.id')
                    ->where('note_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('term', 'note.type_id', '=', 'term.id')
            ->leftJoin('term_i18n as type_i18n', function ($join) {
                $join->on('term.id', '=', 'type_i18n.id')
                    ->where('type_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select([
                'note_i18n.content',
                'type_i18n.name as type_name',
            ])
            ->where('note.object_id', $id)
            ->get();

        return $donor;
    }

    /**
     * Get entity types for filter dropdown
     */
    public function getEntityTypes(): Collection
    {
        return DB::table('term')
            ->join('term_i18n', function ($join) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->where('term.taxonomy_id', 32) // ACTOR_ENTITY_TYPE_ID
            ->select(['term.id', 'term_i18n.name'])
            ->orderBy('term_i18n.name')
            ->get();
    }

    /**
     * Search donors for autocomplete
     */
    public function autocomplete(string $term, int $limit = 10): Collection
    {
        return DB::table('donor')
            ->join('actor', 'donor.id', '=', 'actor.id')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->select([
                'donor.id',
                'actor_i18n.authorized_form_of_name as name',
            ])
            ->where('actor_i18n.culture', CultureHelper::getCulture())
            ->where('actor_i18n.authorized_form_of_name', 'LIKE', "%{$term}%")
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->limit($limit)
            ->get();
    }
}
