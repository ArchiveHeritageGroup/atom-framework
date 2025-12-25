<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Database\Repository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Repository for actor (authority record) table.
 * Used by Authority Record reports.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class ActorRepository extends Repository
{
    protected string $table = 'actor';

    /**
     * Get base query with standard joins for authority record reports.
     */
    public function reportQuery(): Builder
    {
        return DB::table('actor as a')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->where('a.parent_id', '!=', null)  // Exclude root
            ->where('o.class_name', 'QubitActor');
    }

    /**
     * Find actors by entity type (Person, Corporate body, Family).
     */
    public function findByEntityType(int $entityTypeId): Collection
    {
        return collect($this->query()
            ->where('entity_type_id', $entityTypeId)
            ->get())
            ->map(fn ($item) => (array) $item);
    }

    /**
     * Find actors with i18n data.
     */
    public function findWithI18n(int $id, string $culture = 'en'): ?array
    {
        $result = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->where('a.id', $id)
            ->select('a.*', 'i18n.authorized_form_of_name', 'i18n.history')
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * Search actors by authorized form of name.
     */
    public function searchByName(string $searchTerm, int $limit = 50): Collection
    {
        return collect(DB::table('actor as a')
            ->join('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->where('i18n.authorized_form_of_name', 'LIKE', "%{$searchTerm}%")
            ->where('i18n.culture', CultureHelper::getCulture())
            ->limit($limit)
            ->select('a.*', 'i18n.authorized_form_of_name')
            ->get())
            ->map(fn ($item) => (array) $item);
    }

    /**
     * Get all actors of a specific type (for dropdowns).
     */
    public function getAllByType(int $entityTypeId): Collection
    {
        return collect(DB::table('actor as a')
            ->join('actor_i18n as i18n', function ($join) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->where('a.entity_type_id', $entityTypeId)
            ->select('a.id', 'i18n.authorized_form_of_name as name')
            ->orderBy('i18n.authorized_form_of_name')
            ->get())
            ->map(fn ($item) => (array) $item);
    }

    /**
     * Count actors by entity type.
     */
    public function countByEntityType(): array
    {
        $results = DB::table('actor')
            ->select('entity_type_id', DB::raw('count(*) as count'))
            ->whereNotNull('entity_type_id')
            ->groupBy('entity_type_id')
            ->get();

        $counts = [];

        foreach ($results as $result) {
            $counts[(int) $result->entity_type_id] = (int) $result->count;
        }

        return $counts;
    }

    /**
     * Find actors related to specific information objects.
     */
    public function findByInformationObject(int $objectId): Collection
    {
        return collect(DB::table('actor as a')
            ->join('event as e', 'a.id', '=', 'e.actor_id')
            ->where('e.object_id', $objectId)
            ->select('a.*', 'e.type_id as event_type')
            ->get())
            ->map(fn ($item) => (array) $item);
    }

    /**
     * Get actors that are repositories.
     */
    public function getRepositories(): Collection
    {
        return collect(DB::table('actor as a')
            ->join('actor_i18n as i18n', function ($join) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->join('repository', 'a.id', '=', 'repository.id')
            ->select('a.id', 'i18n.authorized_form_of_name as name')
            ->orderBy('i18n.authorized_form_of_name')
            ->get())
            ->map(fn ($item) => (array) $item);
    }
}

    /**
     * Get contact information for an actor.
     */
    public function getContacts(int $actorId): Collection
    {
        return DB::table('contact_information')
            ->where('actor_id', $actorId)
            ->orderBy('primary_contact', 'desc')
            ->get();
    }

    /**
     * Get primary contact for an actor.
     */
    public function getPrimaryContact(int $actorId): ?object
    {
        return DB::table('contact_information')
            ->where('actor_id', $actorId)
            ->where('primary_contact', 1)
            ->first();
    }

    /**
     * Check if actor has contacts.
     */
    public function hasContacts(int $actorId): bool
    {
        return DB::table('contact_information')
            ->where('actor_id', $actorId)
            ->exists();
    }

    /**
     * Get actor with contacts.
     */
    public function findWithContacts(int $id, string $culture = 'en'): ?array
    {
        $actor = $this->findWithI18n($id, $culture);
        if ($actor) {
            $actor['contacts'] = $this->getContacts($id)->toArray();
        }
        return $actor;
    }
}
