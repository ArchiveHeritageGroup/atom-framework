<?php

/**
 * QubitTerm Compatibility Layer.
 *
 * Provides Laravel-based implementations for QubitTerm methods
 * while maintaining compatibility with core AtoM.
 *
 * This class is ONLY loaded if the core QubitTerm doesn't exist.
 * All 86 constants sourced from lib/model/QubitTerm.php.
 */

if (!class_exists('QubitTerm', false)) {
    class QubitTerm
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'term';
        protected static string $i18nTableName = 'term_i18n';

        // ROOT term id
        public const ROOT_ID = 110;

        // Event type taxonomy
        public const CREATION_ID = 111;
        public const CUSTODY_ID = 113;
        public const PUBLICATION_ID = 114;
        public const CONTRIBUTION_ID = 115;
        public const COLLECTION_ID = 117;
        public const ACCUMULATION_ID = 118;

        // Note type taxonomy
        public const TITLE_NOTE_ID = 119;
        public const PUBLICATION_NOTE_ID = 120;
        public const SOURCE_NOTE_ID = 121;
        public const SCOPE_NOTE_ID = 122;
        public const DISPLAY_NOTE_ID = 123;
        public const ARCHIVIST_NOTE_ID = 124;
        public const GENERAL_NOTE_ID = 125;
        public const OTHER_DESCRIPTIVE_DATA_ID = 126;
        public const MAINTENANCE_NOTE_ID = 127;

        // Collection type taxonomy
        public const ARCHIVAL_MATERIAL_ID = 128;
        public const PUBLISHED_MATERIAL_ID = 129;
        public const ARTEFACT_MATERIAL_ID = 130;

        // Actor type taxonomy
        public const CORPORATE_BODY_ID = 131;
        public const PERSON_ID = 132;
        public const FAMILY_ID = 133;

        // Legacy aliases for actor type constants
        public const ACTOR_ENTITY_TYPE_CORPORATE_BODY_ID = 131;
        public const ACTOR_ENTITY_TYPE_PERSON_ID = 132;
        public const ACTOR_ENTITY_TYPE_FAMILY_ID = 133;

        // Other name type taxonomy
        public const FAMILY_NAME_FIRST_NAME_ID = 134;

        // Media type taxonomy
        public const AUDIO_ID = 135;
        public const IMAGE_ID = 136;
        public const TEXT_ID = 137;
        public const VIDEO_ID = 138;
        public const OTHER_ID = 139;

        // Digital object usage taxonomy
        public const MASTER_ID = 140;
        public const REFERENCE_ID = 141;
        public const THUMBNAIL_ID = 142;
        public const COMPOUND_ID = 143;

        // Physical object type taxonomy
        public const LOCATION_ID = 144;
        public const CONTAINER_ID = 145;
        public const ARTEFACT_ID = 146;

        // Relation type taxonomy
        public const HAS_PHYSICAL_OBJECT_ID = 147;

        // Actor name type taxonomy
        public const PARALLEL_FORM_OF_NAME_ID = 148;
        public const OTHER_FORM_OF_NAME_ID = 149;

        // Actor relation type taxonomy
        public const HIERARCHICAL_RELATION_ID = 150;
        public const TEMPORAL_RELATION_ID = 151;
        public const FAMILY_RELATION_ID = 152;
        public const ASSOCIATIVE_RELATION_ID = 153;

        // Actor relation note taxonomy
        public const RELATION_NOTE_DESCRIPTION_ID = 154;
        public const RELATION_NOTE_DATE_ID = 155;

        // Term relation taxonomy
        public const ALTERNATIVE_LABEL_ID = 156;
        public const TERM_RELATION_ASSOCIATIVE_ID = 157;

        // Status type taxonomy
        public const STATUS_TYPE_PUBLICATION_ID = 158;

        // Publication status taxonomy
        public const PUBLICATION_STATUS_DRAFT_ID = 159;
        public const PUBLICATION_STATUS_PUBLISHED_ID = 160;

        // Name access point
        public const NAME_ACCESS_POINT_ID = 161;

        // Function relation type taxonomy
        public const ISDF_HIERARCHICAL_RELATION_ID = 162;
        public const ISDF_TEMPORAL_RELATION_ID = 163;
        public const ISDF_ASSOCIATIVE_RELATION_ID = 164;

        // ISAAR standardized form name
        public const STANDARDIZED_FORM_OF_NAME_ID = 165;

        // Digital object usage taxonomy (addition)
        public const EXTERNAL_URI_ID = 166;

        // Relation types
        public const ACCESSION_ID = 167;
        public const RIGHT_ID = 168;
        public const DONOR_ID = 169;

        // Rights basis
        public const RIGHT_BASIS_COPYRIGHT_ID = 170;
        public const RIGHT_BASIS_LICENSE_ID = 171;
        public const RIGHT_BASIS_STATUTE_ID = 172;
        public const RIGHT_BASIS_POLICY_ID = 173;

        // Language note
        public const LANGUAGE_NOTE_ID = 174;

        // Accrual relation type
        public const ACCRUAL_ID = 175;

        // Relation type
        public const RELATED_MATERIAL_DESCRIPTIONS_ID = 176;

        // Converse term relation
        public const CONVERSE_TERM_ID = 177;

        // AIP relation
        public const AIP_RELATION_ID = 178;

        // AIP types
        public const ARTWORK_COMPONENT_ID = 179;
        public const ARTWORK_MATERIAL_ID = 180;
        public const SUPPORTING_DOCUMENTATION_ID = 181;
        public const SUPPORTING_TECHNOLOGY_ID = 182;

        // Job statuses
        public const JOB_STATUS_IN_PROGRESS_ID = 183;
        public const JOB_STATUS_COMPLETED_ID = 184;
        public const JOB_STATUS_ERROR_ID = 185;

        // Digital object usage taxonomy (addition)
        public const OFFLINE_ID = 186;

        // Relation type taxonomy
        public const MAINTAINING_REPOSITORY_RELATION_ID = 187;
        public const ACTOR_OCCUPATION_NOTE_ID = 188;

        // User action taxonomy
        public const USER_ACTION_CREATION_ID = 189;
        public const USER_ACTION_MODIFICATION_ID = 190;

        // Digital object usage taxonomy (addition)
        public const EXTERNAL_FILE_ID = 191;

        // Accession alternative identifier taxonomy
        public const ACCESSION_ALTERNATIVE_IDENTIFIER_DEFAULT_TYPE_ID = 192;

        // Accession event type: physical transfer
        public const ACCESSION_EVENT_PHYSICAL_TRANSFER_ID = 193;

        // Accession event note
        public const ACCESSION_EVENT_NOTE_ID = 194;

        // Digital object usage taxonomy (addition)
        public const CHAPTERS_ID = 195;
        public const SUBTITLES_ID = 196;

        // Job error note
        public const JOB_ERROR_NOTE_ID = 197;

        // Level of description (taxonomy ID, not term ID)
        public const LEVEL_OF_DESCRIPTION_ID = 34;

        // Legacy aliases
        public const SUBJECT_ID = 35;
        public const PLACE_ID = 42;
        public const GENRE_ID = 78;
        public const PHYSICAL_OBJECT_ID = 67;

        /**
         * Load term parent list for given taxonomy IDs.
         * Used by Elasticsearch indexing.
         *
         * @param array $taxonomyIds
         * @return array
         */
        public static function loadTermParentList($taxonomyIds = [])
        {
            $db = \Illuminate\Database\Capsule\Manager::connection();

            $query = $db->table('term')
                ->select('term.id', 'term.parent_id')
                ->where('term.parent_id', '!=', self::ROOT_ID);

            if (is_array($taxonomyIds) && count($taxonomyIds) > 0) {
                $query->whereIn('term.taxonomy_id', $taxonomyIds);
            }

            $results = $query->get();

            $list = [];
            foreach ($results as $row) {
                $list[$row->id] = $row->parent_id;
            }

            return $list;
        }

        public static function getNameById($id, $culture = 'en')
        {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            return $db->table('term_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->value('name');
        }

        public static function getByTaxonomyId($taxonomyId, $culture = 'en')
        {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            return $db->table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', $taxonomyId)
                ->where('term_i18n.culture', $culture)
                ->orderBy('term_i18n.name')
                ->select('term.id', 'term_i18n.name', 'term.parent_id')
                ->get();
        }

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }

        public static function isProtected($id)
        {
            $protectedIds = [
                self::ACCESSION_ID, self::ACCRUAL_ID, self::ACCUMULATION_ID,
                self::ALTERNATIVE_LABEL_ID, self::ARCHIVAL_MATERIAL_ID,
                self::ARCHIVIST_NOTE_ID, self::ARTEFACT_ID, self::ARTEFACT_MATERIAL_ID,
                self::ASSOCIATIVE_RELATION_ID, self::AUDIO_ID, self::CHAPTERS_ID,
                self::COLLECTION_ID, self::COMPOUND_ID, self::CONTAINER_ID,
                self::CONTRIBUTION_ID, self::CONVERSE_TERM_ID, self::CORPORATE_BODY_ID,
                self::CREATION_ID, self::CUSTODY_ID, self::DISPLAY_NOTE_ID,
                self::DONOR_ID, self::EXTERNAL_URI_ID, self::FAMILY_ID,
                self::FAMILY_NAME_FIRST_NAME_ID, self::FAMILY_RELATION_ID,
                self::GENERAL_NOTE_ID, self::HAS_PHYSICAL_OBJECT_ID,
                self::HIERARCHICAL_RELATION_ID, self::IMAGE_ID, self::LANGUAGE_NOTE_ID,
                self::LOCATION_ID, self::MAINTENANCE_NOTE_ID, self::MASTER_ID,
                self::NAME_ACCESS_POINT_ID, self::OTHER_DESCRIPTIVE_DATA_ID,
                self::OTHER_FORM_OF_NAME_ID, self::OTHER_ID,
                self::PARALLEL_FORM_OF_NAME_ID, self::PERSON_ID, self::PUBLICATION_ID,
                self::PUBLICATION_NOTE_ID, self::PUBLICATION_STATUS_DRAFT_ID,
                self::PUBLICATION_STATUS_PUBLISHED_ID, self::PUBLISHED_MATERIAL_ID,
                self::REFERENCE_ID, self::RELATION_NOTE_DATE_ID,
                self::RELATION_NOTE_DESCRIPTION_ID, self::RIGHT_BASIS_COPYRIGHT_ID,
                self::RIGHT_BASIS_LICENSE_ID, self::RIGHT_BASIS_STATUTE_ID,
                self::RIGHT_ID, self::ROOT_ID, self::SCOPE_NOTE_ID,
                self::SOURCE_NOTE_ID, self::STANDARDIZED_FORM_OF_NAME_ID,
                self::STATUS_TYPE_PUBLICATION_ID, self::SUBTITLES_ID,
                self::TEMPORAL_RELATION_ID, self::TERM_RELATION_ASSOCIATIVE_ID,
                self::TEXT_ID, self::THUMBNAIL_ID, self::TITLE_NOTE_ID,
                self::VIDEO_ID, self::JOB_STATUS_IN_PROGRESS_ID,
                self::JOB_STATUS_COMPLETED_ID, self::JOB_STATUS_ERROR_ID,
                self::ACTOR_OCCUPATION_NOTE_ID,
                self::ACCESSION_EVENT_PHYSICAL_TRANSFER_ID,
                self::ACCESSION_EVENT_NOTE_ID,
            ];

            return in_array($id, $protectedIds);
        }
    }
}
