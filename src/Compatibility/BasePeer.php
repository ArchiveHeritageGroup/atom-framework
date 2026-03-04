<?php

/**
 * BasePeer Compatibility Stub.
 *
 * Translates Propel's BasePeer::doCount() to Laravel Query Builder.
 * Used by locked/stable plugins that contain legacy Propel patterns.
 *
 * Only loaded if the real Propel BasePeer class is not available.
 */

if (!class_exists('BasePeer', false)) {
    class BasePeer
    {
        /**
         * Count rows matching a Criteria.
         *
         * Returns an object mimicking PDOStatement with fetchColumn() method,
         * since plugin code typically calls: BasePeer::doCount($c)->fetchColumn(0)
         *
         * @param Criteria $criteria
         *
         * @return object
         */
        public static function doCount(Criteria $criteria): object
        {
            $query = $criteria->toQueryBuilder();
            $count = $query->count();

            return new class($count) {
                private int $count;

                public function __construct(int $c)
                {
                    $this->count = $c;
                }

                public function fetchColumn($idx = 0)
                {
                    return $this->count;
                }
            };
        }

        /**
         * Select rows matching a Criteria.
         *
         * Returns results as an array of stdClass objects.
         *
         * @param Criteria $criteria
         *
         * @return array
         */
        public static function doSelect(Criteria $criteria): array
        {
            $query = $criteria->toQueryBuilder();

            return $query->get()->all();
        }
    }
}
