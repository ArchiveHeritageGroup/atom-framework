<?php
declare(strict_types=1);
namespace AtomExtensions\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use AtomExtensions\Constants\TermConstants;
use AtomExtensions\Constants\TaxonomyConstants;

/**
 * Term Service - Replaces QubitTerm (658 uses)
 */
class TermService
{
    // Re-export constants for backward compatibility
    public const ROOT_ID = TermConstants::ROOT_ID;
    public const PUBLICATION_STATUS_DRAFT_ID = TermConstants::PUBLICATION_STATUS_DRAFT_ID;
    public const PUBLICATION_STATUS_PUBLISHED_ID = TermConstants::PUBLICATION_STATUS_PUBLISHED_ID;
    public const MASTER_ID = TermConstants::MASTER_ID;
    public const REFERENCE_ID = TermConstants::REFERENCE_ID;
    public const THUMBNAIL_ID = TermConstants::THUMBNAIL_ID;
    public const OFFLINE_ID = TermConstants::OFFLINE_ID;
    public const EXTERNAL_URI_ID = TermConstants::EXTERNAL_URI_ID;
    public const CHAPTERS_ID = TermConstants::CHAPTERS_ID;
    public const SUBTITLES_ID = TermConstants::SUBTITLES_ID;
    public const CREATION_ID = TermConstants::CREATION_ID;
    public const GENERAL_NOTE_ID = TermConstants::GENERAL_NOTE_ID;
    public const LANGUAGE_NOTE_ID = TermConstants::LANGUAGE_NOTE_ID;
    public const PUBLICATION_NOTE_ID = TermConstants::PUBLICATION_NOTE_ID;
    public const ARCHIVIST_NOTE_ID = TermConstants::ARCHIVIST_NOTE_ID;
    public const SCOPE_NOTE_ID = TermConstants::SCOPE_NOTE_ID;
    public const ALTERNATIVE_LABEL_ID = TermConstants::ALTERNATIVE_LABEL_ID;
    public const NAME_ACCESS_POINT_ID = TermConstants::NAME_ACCESS_POINT_ID;
    public const DONOR_ID = TermConstants::DONOR_ID;
    public const RIGHT_ID = TermConstants::RIGHT_ID;
    public const ACCESSION_ID = TermConstants::ACCESSION_ID;
    public const ACCRUAL_ID = TermConstants::ACCRUAL_ID;
    public const AIP_RELATION_ID = TermConstants::AIP_RELATION_ID;
    public const CONVERSE_TERM_ID = TermConstants::CONVERSE_TERM_ID;
    public const RELATED_MATERIAL_DESCRIPTIONS_ID = TermConstants::RELATED_MATERIAL_DESCRIPTIONS_ID;
    public const RIGHT_BASIS_COPYRIGHT_ID = TermConstants::RIGHT_BASIS_COPYRIGHT_ID;
    public const RIGHT_BASIS_LICENSE_ID = TermConstants::RIGHT_BASIS_LICENSE_ID;
    public const RIGHT_BASIS_STATUTE_ID = TermConstants::RIGHT_BASIS_STATUTE_ID;
    public const CORPORATE_BODY_ID = TermConstants::CORPORATE_BODY_ID;
    public const PERSON_ID = TermConstants::PERSON_ID;
    public const FAMILY_ID = TermConstants::FAMILY_ID;
    public const PARALLEL_FORM_OF_NAME_ID = TermConstants::PARALLEL_FORM_OF_NAME_ID;
    public const OTHER_FORM_OF_NAME_ID = TermConstants::OTHER_FORM_OF_NAME_ID;
    public const STANDARDIZED_FORM_OF_NAME_ID = TermConstants::STANDARDIZED_FORM_OF_NAME_ID;
    public const OTHER_ID = TermConstants::OTHER_ID;
    public const ACTOR_OCCUPATION_NOTE_ID = TermConstants::ACTOR_OCCUPATION_NOTE_ID;
    public const MAINTENANCE_NOTE_ID = TermConstants::MAINTENANCE_NOTE_ID;
    public const FONDS_ID = TermConstants::FONDS_ID;
    public const COLLECTION_ID = TermConstants::COLLECTION_ID;
    public const SERIES_ID = TermConstants::SERIES_ID;
    public const FILE_ID = TermConstants::FILE_ID;
    public const ITEM_ID = TermConstants::ITEM_ID;
    
    private static string $culture = 'en';

    public static function getById(int $id, ?string $culture = null): ?object
    {
        $culture = $culture ?? CultureHelper::getCulture();
        return DB::table('term as t')
            ->leftJoin('term_i18n as ti', fn($j) => $j->on('t.id', '=', 'ti.id')->where('ti.culture', $culture))
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.id', $id)
            ->select('t.*', 'ti.name', 'ti.culture', 's.slug')
            ->first();
    }

    public static function getBySlug(string $slug): ?object
    {
        return DB::table('term as t')
            ->join('slug as s', 't.id', '=', 's.object_id')
            ->leftJoin('term_i18n as ti', fn($j) => $j->on('t.id', '=', 'ti.id')->where('ti.culture', CultureHelper::getCulture()))
            ->where('s.slug', $slug)
            ->select('t.*', 'ti.name', 's.slug')
            ->first();
    }

    public static function getLevelsOfDescription(?string $culture = null): Collection
    {
        return TaxonomyService::getTermsById(TaxonomyConstants::LEVEL_OF_DESCRIPTION_ID, $culture);
    }

    public static function getNoteTypes(?string $culture = null): Collection
    {
        return TaxonomyService::getTermsById(TaxonomyConstants::NOTE_TYPE_ID, $culture);
    }

    public static function getRADNotes(?string $culture = null): Collection
    {
        return TaxonomyService::getTermsById(TaxonomyConstants::RAD_NOTE_ID, $culture);
    }

    public static function isProtected(int $id): bool
    {
        // Protected terms are core system terms that shouldn't be deleted
        return $id < 500;
    }

    public static function countRelatedInformationObjects(int $termId): int
    {
        return DB::table('object_term_relation')
            ->where('term_id', $termId)
            ->count();
    }

    public static function loadTermParentList(array $taxonomyIds): array
    {
        return DB::table('term')
            ->whereIn('taxonomy_id', $taxonomyIds)
            ->pluck('parent_id', 'id')
            ->toArray();
    }

    public static function setCulture(string $culture): void
    {
        self::$culture = $culture;
    }

    public static function getName(?object $term): string
    {
        return $term->name ?? '[Untitled]';
    }

    /**
     * Get publication statuses.
     */
    public static function getPublicationStatuses(?string $culture = null): Collection
    {
        $culture = $culture ?? CultureHelper::getCulture();
        
        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('t.taxonomy_id', TaxonomyConstants::PUBLICATION_STATUS_ID)
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 'ti.culture')
            ->get();
    }

    // Instance support
    private ?string $instanceCulture = null;

    /**
     * Constructor for instance usage.
     */
    public function __construct(?string $culture = null)
    {
        $this->instanceCulture = $culture ?? CultureHelper::getCulture();
    }

    /**
     * Get actor entity types (Corporate Body, Person, Family).
     */
    public function getActorEntityTypes(): Collection
    {
        $culture = $this->instanceCulture ?? CultureHelper::getCulture();
        
        return DB::table('term as t')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('t.taxonomy_id', TaxonomyConstants::ACTOR_ENTITY_TYPE_ID)
            ->orderBy('ti.name')
            ->select('t.id', 'ti.name', 'ti.culture')
            ->get();
    }

    /**
     * Get term name by ID (instance method).
     */
    public function getTermName(int $id): ?string
    {
        $culture = $this->instanceCulture ?? CultureHelper::getCulture();
        
        return DB::table('term_i18n')
            ->where('id', $id)
            ->where('culture', $culture)
            ->value('name');
    }

    /**
     * Convert collection to choices array for forms.
     */
    public function toChoices(Collection $items, string $valueField = 'id', string $labelField = 'name'): array
    {
        $choices = [];
        foreach ($items as $item) {
            $choices[$item->$valueField] = $item->$labelField ?? '[Untitled]';
        }
        return $choices;
    }

    /**
     * Get levels of description (instance method).
     */
    public function getLevelsOfDescriptionChoices(): Collection
    {
        return self::getLevelsOfDescription($this->instanceCulture);
    }

    /**
     * Get publication statuses (instance method).
     */
    public function getPublicationStatusChoices(): Collection
    {
        return self::getPublicationStatuses($this->instanceCulture);
    }
}
