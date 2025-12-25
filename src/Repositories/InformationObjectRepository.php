<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use AtomExtensions\Config\DigitalObjectConfig;

/**
 * Information Object Repository.
 *
 * Provides access to archival descriptions (information objects).
 * Uses Laravel Query Builder for database operations.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class InformationObjectRepository
{
    /**
     * Find information object by ID.
     */
    public function findById(int $id, string $culture = 'en'): ?object
    {
        $result = DB::table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('i.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('i.id', $id)
            ->select(
                'i.*',
                'i18n.title',
                'i18n.alternate_title as alternateTitle',
                'i18n.extent_and_medium as extentAndMedium',
                'i18n.archival_history as archivalHistory',
                'i18n.acquisition',
                'i18n.scope_and_content as scopeAndContent',
                'i18n.appraisal',
                'i18n.accruals',
                'i18n.arrangement',
                'i18n.access_conditions as accessConditions',
                'i18n.reproduction_conditions as reproductionConditions',
                'i18n.physical_characteristics as physicalCharacteristics',
                'i18n.finding_aids as findingAids',
                'i18n.location_of_originals as locationOfOriginals',
                'i18n.location_of_copies as locationOfCopies',
                'i18n.related_units_of_description as relatedUnitsOfDescription',
                'i18n.institution_responsible_identifier as institutionResponsibleIdentifier',
                'i18n.rules',
                'i18n.sources',
                'i18n.revision_history as revisionHistory',
                'i18n.culture',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            )
            ->first();

        return $result ? (object) $result : null;
    }

    /**
     * Search information objects with filters.
     */
    public function search(array $filters = [], string $culture = 'en'): Collection
    {
        $query = DB::table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('i.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('o.class_name', DigitalObjectConfig::classInformationObject())
            ->whereNotNull('i.parent_id');

        // Apply filters
        if (!empty($filters['levelOfDescription'])) {
            $query->where('i.level_of_description_id', $filters['levelOfDescription']);
        }

        if (!empty($filters['publicationStatus'])) {
            $query->where('i.publication_status_id', $filters['publicationStatus']);
        }

        if (!empty($filters['repositoryId'])) {
            $query->where('i.repository_id', $filters['repositoryId']);
        }

        $results = $query->select(
            'i.id',
            'i.identifier',
            'i.level_of_description_id as levelOfDescriptionId',
            'i.publication_status_id as publicationStatusId',
            'i.repository_id as repositoryId',
            'i18n.title',
            'i18n.alternate_title as alternateTitle',
            'i18n.extent_and_medium as extentAndMedium',
            'i18n.archival_history as archivalHistory',
            'i18n.acquisition',
            'i18n.scope_and_content as scopeAndContent',
            'i18n.appraisal',
            'i18n.accruals',
            'i18n.arrangement',
            'i18n.access_conditions as accessConditions',
            'i18n.reproduction_conditions as reproductionConditions',
            'i18n.physical_characteristics as physicalCharacteristics',
            'i18n.finding_aids as findingAids',
            'i18n.location_of_originals as locationOfOriginals',
            'i18n.location_of_copies as locationOfCopies',
            'i18n.related_units_of_description as relatedUnitsOfDescription',
            'i18n.institution_responsible_identifier as institutionResponsibleIdentifier',
            'i18n.rules',
            'i18n.sources',
            'i18n.revision_history as revisionHistory',
            'i18n.culture',
            'o.created_at as createdAt',
            'o.updated_at as updatedAt'
        )
        ->get();

        return $results->map(fn ($item) => (object) $item);
    }

    /**
     * Get repository for information object (with inheritance).
     */
    public function getRepository(int $informationObjectId): ?object
    {
        // Get the information object
        $io = DB::table('information_object')
            ->where('id', $informationObjectId)
            ->first();

        if (!$io) {
            return null;
        }

        // If it has a repository, return it
        if ($io->repository_id) {
            return $this->getRepositoryById($io->repository_id);
        }

        // Otherwise, walk up the tree to find inherited repository
        $parentId = $io->parent_id;
        while ($parentId) {
            $parent = DB::table('information_object')
                ->where('id', $parentId)
                ->first();

            if (!$parent) {
                break;
            }

            if ($parent->repository_id) {
                return $this->getRepositoryById($parent->repository_id);
            }

            $parentId = $parent->parent_id;
        }

        return null;
    }

    /**
     * Get repository by ID.
     */
    private function getRepositoryById(int $repositoryId, string $culture = 'en'): ?object
    {
        $repo = DB::table('repository as r')
            ->join('actor as a', 'r.id', '=', 'a.id')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('r.id', $repositoryId)
            ->select(
                'r.id',
                'r.identifier',
                'i18n.authorized_form_of_name as name'
            )
            ->first();

        return $repo ? (object) $repo : null;
    }

    /**
     * Get publication status for information object.
     */
    public function getPublicationStatus(int $informationObjectId): ?object
    {
        $status = DB::table('status')
            ->where('object_id', $informationObjectId)
            ->where('type_id', 159) // Publication status type
            ->select('status_id as statusId', 'type_id as typeId')
            ->first();

        return $status ? (object) $status : null;
    }

    /**
     * Count total information objects.
     */
    public function count(array $filters = []): int
    {
        $query = DB::table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->where('o.class_name', DigitalObjectConfig::classInformationObject())
            ->whereNotNull('i.parent_id');

        if (!empty($filters['levelOfDescription'])) {
            $query->where('i.level_of_description_id', $filters['levelOfDescription']);
        }

        if (!empty($filters['publicationStatus'])) {
            $query->where('i.publication_status_id', $filters['publicationStatus']);
        }

        return $query->count();
    }
}