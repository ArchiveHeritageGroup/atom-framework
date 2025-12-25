<?php
/**
 * QubitTerm Compatibility Layer
 */
if (!class_exists('QubitTerm', false)) {
    class QubitTerm
    {
        // Constants
        public const ROOT_ID = 110;
        public const SUBJECT_ID = 35;
        public const PLACE_ID = 42;
        public const GENRE_ID = 78;
        public const CONVERSE_TERM_ID = 165;
        public const PHYSICAL_OBJECT_ID = 67;
        public const MASTER_ID = 169;
        public const REFERENCE_ID = 170;
        public const THUMBNAIL_ID = 171;
        public const COMPOUND_ID = 172;
        public const CREATION_ID = 111;
        public const ACCUMULATION_ID = 112;
        public const PUBLICATION_STATUS_DRAFT_ID = 159;
        public const PUBLICATION_STATUS_PUBLISHED_ID = 160;
        public const LEVEL_OF_DESCRIPTION_ID = 34;
        public const ACTOR_ENTITY_TYPE_CORPORATE_BODY_ID = 131;
        public const ACTOR_ENTITY_TYPE_PERSON_ID = 132;
        public const ACTOR_ENTITY_TYPE_FAMILY_ID = 133;
        public const TABLE_NAME = 'term';

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

        public static function getById($id)
        {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            return $db->table('term')->where('id', $id)->first();
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
            $protectedIds = [self::ROOT_ID, self::MASTER_ID, self::REFERENCE_ID, self::THUMBNAIL_ID, self::COMPOUND_ID];
            return in_array($id, $protectedIds);
        }
    }
}
