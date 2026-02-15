<?php

/**
 * QubitObjectTermRelation Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitObjectTermRelation', false)) {
    class QubitObjectTermRelation
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'object_term_relation';
        protected static string $i18nTableName = '';

        /**
         * Get term relations for an object.
         *
         * @param int      $objectId
         * @param int|null $termId   Optional filter by term ID
         * @return array
         */
        public static function getByObjectId($objectId, $termId = null)
        {
            $query = \Illuminate\Database\Capsule\Manager::table('object_term_relation')
                ->where('object_id', $objectId);

            if (null !== $termId) {
                $query->where('term_id', $termId);
            }

            return $query->get()->all();
        }
    }
}
