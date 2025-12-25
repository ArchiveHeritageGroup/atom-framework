<?php

/**
 * QubitTaxonomy Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\TaxonomyService directly
 */

use AtomExtensions\Services\TaxonomyService;

class QubitTaxonomy
{
    // Mirror constants from TaxonomyService
    public const SUBJECT_ID = TaxonomyService::SUBJECT_ID;
    public const PLACE_ID = TaxonomyService::PLACE_ID;
    public const LEVEL_OF_DESCRIPTION_ID = TaxonomyService::LEVEL_OF_DESCRIPTION_ID;
    public const PUBLICATION_STATUS_ID = TaxonomyService::PUBLICATION_STATUS_ID;
    public const EVENT_TYPE_ID = TaxonomyService::EVENT_TYPE_ID;
    public const ACTOR_ENTITY_TYPE_ID = TaxonomyService::ACTOR_ENTITY_TYPE_ID;
    public const RIGHT_BASIS_ID = TaxonomyService::RIGHT_BASIS_ID;
    public const MEDIA_TYPE_ID = TaxonomyService::MEDIA_TYPE_ID;

    public static function getById(int $id): ?object
    {
        return TaxonomyService::getById($id);
    }

    public static function getTermsById(int $taxonomyId): array
    {
        return TaxonomyService::getTermsById($taxonomyId)->toArray();
    }
}
