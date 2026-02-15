<?php

/**
 * QubitTaxonomy Compatibility Layer.
 *
 * Provides Laravel-based implementations for QubitTaxonomy methods
 * while maintaining compatibility with core AtoM.
 *
 * This class is ONLY loaded if the core QubitTaxonomy doesn't exist.
 * All 50 constants sourced from lib/model/QubitTaxonomy.php.
 */

if (!class_exists('QubitTaxonomy', false)) {
    class QubitTaxonomy
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'taxonomy';
        protected static string $i18nTableName = 'taxonomy_i18n';

        public const ROOT_ID = 30;
        public const DESCRIPTION_DETAIL_LEVEL_ID = 31;
        public const ACTOR_ENTITY_TYPE_ID = 32;
        public const DESCRIPTION_STATUS_ID = 33;
        public const LEVEL_OF_DESCRIPTION_ID = 34;
        public const SUBJECT_ID = 35;
        public const ACTOR_NAME_TYPE_ID = 36;
        public const NOTE_TYPE_ID = 37;
        public const REPOSITORY_TYPE_ID = 38;
        public const EVENT_TYPE_ID = 40;
        public const QUBIT_SETTING_LABEL_ID = 41;
        public const PLACE_ID = 42;
        public const FUNCTION_ID = 43;
        public const HISTORICAL_EVENT_ID = 44;
        public const COLLECTION_TYPE_ID = 45;
        public const MEDIA_TYPE_ID = 46;
        public const DIGITAL_OBJECT_USAGE_ID = 47;
        public const PHYSICAL_OBJECT_TYPE_ID = 48;
        public const RELATION_TYPE_ID = 49;
        public const MATERIAL_TYPE_ID = 50;

        // Rules for Archival Description (RAD) taxonomies
        public const RAD_NOTE_ID = 51;
        public const RAD_TITLE_NOTE_ID = 52;
        public const MODS_RESOURCE_TYPE_ID = 53;
        public const DC_TYPE_ID = 54;
        public const ACTOR_RELATION_TYPE_ID = 55;
        public const RELATION_NOTE_TYPE_ID = 56;
        public const TERM_RELATION_TYPE_ID = 57;
        public const STATUS_TYPE_ID = 59;
        public const PUBLICATION_STATUS_ID = 60;
        public const ISDF_RELATION_TYPE_ID = 61;

        // Accession taxonomies
        public const ACCESSION_RESOURCE_TYPE_ID = 62;
        public const ACCESSION_ACQUISITION_TYPE_ID = 63;
        public const ACCESSION_PROCESSING_PRIORITY_ID = 64;
        public const ACCESSION_PROCESSING_STATUS_ID = 65;
        public const DEACCESSION_SCOPE_ID = 66;

        // Right taxonomies
        public const RIGHT_ACT_ID = 67;
        public const RIGHT_BASIS_ID = 68;
        public const COPYRIGHT_STATUS_ID = 69;

        // Metadata templates
        public const INFORMATION_OBJECT_TEMPLATE_ID = 70;
        public const AIP_TYPE_ID = 71;
        public const THEMATIC_AREA_ID = 72;
        public const GEOGRAPHIC_SUBREGION_ID = 73;

        // DACS notes
        public const DACS_NOTE_ID = 74;

        // PREMIS Rights Statuses
        public const RIGHTS_STATUTES_ID = 75;

        // Genre taxonomy
        public const GENRE_ID = 78;
        public const JOB_STATUS_ID = 79;
        public const ACTOR_OCCUPATION_ID = 80;
        public const USER_ACTION_ID = 81;
        public const ACCESSION_ALTERNATIVE_IDENTIFIER_TYPE_ID = 82;
        public const ACCESSION_EVENT_TYPE_ID = 83;

        // Locked taxonomies list
        public static $lockedTaxonomies = [
            self::QUBIT_SETTING_LABEL_ID,
            self::COLLECTION_TYPE_ID,
            self::DIGITAL_OBJECT_USAGE_ID,
            self::MEDIA_TYPE_ID,
            self::RELATION_TYPE_ID,
            self::RELATION_NOTE_TYPE_ID,
            self::TERM_RELATION_TYPE_ID,
            self::ROOT_ID,
            self::STATUS_TYPE_ID,
            self::PUBLICATION_STATUS_ID,
            self::ACTOR_NAME_TYPE_ID,
            self::INFORMATION_OBJECT_TEMPLATE_ID,
            self::JOB_STATUS_ID,
        ];

        public static function getTermsById(int $taxonomyId, string $culture = 'en'): \Illuminate\Support\Collection
        {
            $culture = self::resolveCulture(['culture' => $culture]);

            return \Illuminate\Database\Capsule\Manager::table('term as t')
                ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                })
                ->where('t.taxonomy_id', $taxonomyId)
                ->orderBy('ti.name')
                ->select('t.id', 'ti.name', 't.taxonomy_id')
                ->get();
        }

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }
    }
}
