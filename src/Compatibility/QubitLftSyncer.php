<?php

/**
 * QubitLftSyncer — Compatibility shim.
 *
 * Syncs nested set left (lft) values between database and Elasticsearch.
 * Ensures MPTT ordering in ES matches the database.
 */

if (!class_exists('QubitLftSyncer', false)) {
    class QubitLftSyncer
    {
        protected $search;

        /**
         * @param  object|null $search  QubitSearch or Elastica index
         */
        public function __construct($search = null)
        {
            $this->search = $search;
        }

        /**
         * Sync lft values from database to Elasticsearch.
         *
         * @param  array $options  ['objectId' => int, 'table' => string]
         *
         * @return int  Number of documents updated
         */
        public function sync($options = [])
        {
            $table = $options['table'] ?? 'information_object';
            $objectId = $options['objectId'] ?? null;
            $count = 0;

            try {
                $query = \Illuminate\Database\Capsule\Manager::table($table)
                    ->select(['id', 'lft', 'rgt']);

                if (null !== $objectId) {
                    $query->where('id', $objectId);
                }

                $rows = $query->get();

                if (null === $this->search) {
                    return 0;
                }

                foreach ($rows as $row) {
                    try {
                        // Update ES document with lft/rgt values
                        if (method_exists($this->search, 'partialUpdate')) {
                            $this->search->partialUpdate(
                                $row->id,
                                ['doc' => ['lft' => $row->lft, 'rgt' => $row->rgt]]
                            );
                            ++$count;
                        }
                    } catch (\Exception $e) {
                        // Individual document update failures are non-fatal
                        continue;
                    }
                }
            } catch (\Exception $e) {
                error_log('QubitLftSyncer::sync() error: ' . $e->getMessage());
            }

            return $count;
        }

        /**
         * Set the search index.
         *
         * @param  object $search
         */
        public function setSearch($search)
        {
            $this->search = $search;
        }
    }
}
