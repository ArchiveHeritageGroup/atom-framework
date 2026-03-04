<?php

/**
 * QubitRelation Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitRelation', false)) {
    class QubitRelation
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'relation';
        protected static string $i18nTableName = '';

        // Propel column constants
        public const ID = 'relation.id';
        public const SUBJECT_ID = 'relation.subject_id';
        public const OBJECT_ID = 'relation.object_id';
        public const TYPE_ID = 'relation.type_id';
        public const START_DATE = 'relation.start_date';
        public const END_DATE = 'relation.end_date';
        public const SOURCE_CULTURE = 'relation.source_culture';

        /**
         * Get relations by subject or object ID, optionally filtered by type.
         *
         * @param int   $id
         * @param array $options  Keys: 'typeId' => int
         * @return array
         */
        public static function getBySubjectOrObjectId($id, $options = [])
        {
            $query = \Illuminate\Database\Capsule\Manager::table('relation')
                ->where(function ($q) use ($id) {
                    $q->where('subject_id', $id)
                        ->orWhere('object_id', $id);
                });

            if (!empty($options['typeId'])) {
                $query->where('type_id', $options['typeId']);
            }

            return $query->get()->all();
        }
    }
}
