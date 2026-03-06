<?php

/**
 * QubitRights — Compatibility shim.
 *
 * Read-only stub for rights entities with i18n support.
 * Supports PREMIS rights statements attached to objects.
 */

if (!class_exists('QubitRights', false)) {
    class QubitRights
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'rights';
        protected static string $i18nTableName = 'rights_i18n';

        // Propel column constants
        public const ID = 'rights.id';
        public const OBJECT_ID = 'rights.object_id';
        public const BASIS_ID = 'rights.basis_id';
        public const ACT_ID = 'rights.act_id';
        public const RIGHTS_HOLDER_ID = 'rights.rights_holder_id';
        public const COPYRIGHT_STATUS_ID = 'rights.copyright_status_id';
        public const COPYRIGHT_JURISDICTION = 'rights.copyright_jurisdiction';
        public const STATUTE_DETERMINATION_DATE = 'rights.statute_determination_date';
        public const SOURCE_CULTURE = 'rights.source_culture';
        public const START_DATE = 'rights.start_date';
        public const END_DATE = 'rights.end_date';

        /**
         * Get all rights for a specific object.
         *
         * @param  int   $objectId
         * @param  array $options
         *
         * @return array
         */
        public static function getByObjectId($objectId, $options = [])
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('rights')
                    ->where('object_id', $objectId)
                    ->get();

                $results = [];
                foreach ($rows as $row) {
                    $results[] = static::hydrate($row);
                }

                return $results;
            } catch (\Exception $e) {
                return [];
            }
        }

        /**
         * Get the granted rights for this rights statement.
         *
         * @return array
         */
        public function getGrantedRights()
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('granted_right')
                    ->where('rights_id', $this->__get('id'))
                    ->get();

                $results = [];
                foreach ($rows as $row) {
                    if (class_exists('QubitGrantedRight', false)) {
                        $results[] = \QubitGrantedRight::hydrate($row);
                    } else {
                        $results[] = $row;
                    }
                }

                return $results;
            } catch (\Exception $e) {
                return [];
            }
        }

        /**
         * Get the rights holder.
         *
         * @return QubitRightsHolder|null
         */
        public function getRightsHolder()
        {
            $holderId = $this->__get('rights_holder_id');
            if ($holderId && class_exists('QubitRightsHolder', false)) {
                return \QubitRightsHolder::getById($holderId);
            }

            return null;
        }

        /**
         * Get the basis term.
         *
         * @return QubitTerm|null
         */
        public function getBasis()
        {
            $basisId = $this->__get('basis_id');
            if ($basisId && class_exists('QubitTerm', false)) {
                return \QubitTerm::getById($basisId);
            }

            return null;
        }

        /**
         * Copy rights from one object to another (stub).
         *
         * @param  int $sourceId
         * @param  int $targetId
         *
         * @return int  Number of rights copied (0 in read-only mode)
         */
        public static function copy($sourceId, $targetId)
        {
            // Read-only in standalone mode
            return 0;
        }
    }
}
