<?php

/**
 * QubitDeaccession — Compatibility shim.
 *
 * Read-only stub for deaccession records with i18n support.
 * Deaccessions are linked to parent accession objects.
 */

if (!class_exists('QubitDeaccession', false)) {
    class QubitDeaccession
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'deaccession';
        protected static string $i18nTableName = 'deaccession_i18n';

        // Propel column constants
        public const ID = 'deaccession.id';
        public const ACCESSION_ID = 'deaccession.accession_id';
        public const SCOPE_ID = 'deaccession.scope_id';
        public const DATE = 'deaccession.date';
        public const IDENTIFIER = 'deaccession.identifier';
        public const SOURCE_CULTURE = 'deaccession.source_culture';

        /**
         * Get deaccessions for an accession.
         *
         * @param  int $accessionId
         *
         * @return array
         */
        public static function getByAccessionId($accessionId)
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('deaccession')
                    ->where('accession_id', $accessionId)
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
         * Get the parent accession.
         *
         * @return QubitAccession|null
         */
        public function getAccession()
        {
            $accessionId = $this->__get('accession_id');
            if ($accessionId && class_exists('QubitAccession', false)) {
                return \QubitAccession::getById($accessionId);
            }

            return null;
        }

        /**
         * Get the scope term.
         *
         * @return QubitTerm|null
         */
        public function getScope()
        {
            $scopeId = $this->__get('scope_id');
            if ($scopeId && class_exists('QubitTerm', false)) {
                return \QubitTerm::getById($scopeId);
            }

            return null;
        }
    }
}
