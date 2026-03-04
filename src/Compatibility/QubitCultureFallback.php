<?php

/**
 * QubitCultureFallback — Compatibility stub.
 *
 * Provides addFallbackCriteria() for i18n culture fallback in queries.
 * Used by ahgReportsPlugin reportTaxomomyAction.
 */
if (!class_exists('QubitCultureFallback', false)) {
    class QubitCultureFallback
    {
        /**
         * Add culture fallback join to a Criteria.
         *
         * In the Propel world, this adds a LEFT JOIN to the i18n table with
         * COALESCE for the current culture → source culture. In the stub,
         * we add the join directly to the Criteria.
         *
         * @param Criteria $criteria   Existing criteria
         * @param string   $className  Class name (e.g. 'QubitTaxonomy')
         * @param array    $options    Options
         *
         * @return Criteria
         */
        public static function addFallbackCriteria(Criteria $criteria, string $className, array $options = []): Criteria
        {
            // Resolve table name from class name
            $tableMap = [
                'QubitTaxonomy' => 'taxonomy',
                'QubitTerm' => 'term',
                'QubitInformationObject' => 'information_object',
                'QubitActor' => 'actor',
                'QubitRepository' => 'repository',
            ];

            $table = $tableMap[$className] ?? strtolower(str_replace('Qubit', '', $className));
            $i18nTable = $table . '_i18n';

            // Add the i18n join
            $criteria->addJoin("{$table}.id", "{$i18nTable}.id");

            return $criteria;
        }
    }
}
