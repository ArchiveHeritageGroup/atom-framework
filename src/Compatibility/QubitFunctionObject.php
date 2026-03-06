<?php

/**
 * QubitFunctionObject — Compatibility shim.
 *
 * Read-only stub for ISDF function objects with i18n support.
 */

if (!class_exists('QubitFunctionObject', false)) {
    class QubitFunctionObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'function_object';
        protected static string $i18nTableName = 'function_object_i18n';

        // Propel column constants
        public const ID = 'function_object.id';
        public const TYPE_ID = 'function_object.type_id';
        public const PARENT_ID = 'function_object.parent_id';
        public const DESCRIPTION_STATUS_ID = 'function_object.description_status_id';
        public const DESCRIPTION_DETAIL_ID = 'function_object.description_detail_id';
        public const DESCRIPTION_IDENTIFIER = 'function_object.description_identifier';
        public const SOURCE_CULTURE = 'function_object.source_culture';
        public const SOURCE_STANDARD = 'function_object.source_standard';

        // Root ID
        public const ROOT_ID = 226;

        /**
         * Get the root function object.
         *
         * @return static|null
         */
        public static function getRoot()
        {
            return static::getById(static::ROOT_ID);
        }

        /**
         * Get the type term.
         *
         * @return QubitTerm|null
         */
        public function getType()
        {
            $typeId = $this->__get('type_id');
            if ($typeId && class_exists('QubitTerm', false)) {
                return \QubitTerm::getById($typeId);
            }

            return null;
        }

        /**
         * Get related information objects.
         *
         * @return array
         */
        public function getRelatedInformationObjects()
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('relation')
                    ->where('subject_id', $this->__get('id'))
                    ->where('type_id', \QubitTerm::NAME_ACCESS_POINT_ID ?? 161)
                    ->get();

                $results = [];
                foreach ($rows as $row) {
                    if (class_exists('QubitInformationObject', false)) {
                        $io = \QubitInformationObject::getById($row->object_id);
                        if ($io) {
                            $results[] = $io;
                        }
                    }
                }

                return $results;
            } catch (\Exception $e) {
                return [];
            }
        }
    }
}
