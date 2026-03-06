<?php

/**
 * QubitSearchPager — Compatibility shim.
 *
 * Wraps Elasticsearch results in a pager interface compatible with Symfony 1.x.
 * Does NOT depend on Propel — works directly with Elastica ResultSet.
 */

if (!class_exists('QubitSearchPager', false)) {
    class QubitSearchPager
    {
        protected $query;
        protected $hits = [];
        protected $nbResults = 0;
        protected $page = 1;
        protected $maxPerPage = 10;

        // ES search objects
        protected $search;
        protected $resultSet;

        /**
         * @param  object $search  Elastica search or QubitSearch index object
         */
        public function __construct($search = null)
        {
            $this->search = $search;
        }

        /**
         * Set the search query.
         *
         * @param  mixed $query  Elastica query
         */
        public function setQuery($query)
        {
            $this->query = $query;
        }

        /**
         * Get the search query.
         *
         * @return mixed
         */
        public function getQuery()
        {
            return $this->query;
        }

        /**
         * Initialize/execute the search.
         */
        public function init()
        {
            if (null === $this->search || null === $this->query) {
                return;
            }

            try {
                // Set pagination on query
                $offset = ($this->page - 1) * $this->maxPerPage;

                if (is_object($this->query) && method_exists($this->query, 'setFrom')) {
                    $this->query->setFrom($offset);
                    $this->query->setSize($this->maxPerPage);
                }

                // Execute search
                if (method_exists($this->search, 'search')) {
                    $this->resultSet = $this->search->search($this->query);

                    if (method_exists($this->resultSet, 'getTotalHits')) {
                        $this->nbResults = $this->resultSet->getTotalHits();
                    }

                    if (method_exists($this->resultSet, 'getResults')) {
                        $this->hits = $this->resultSet->getResults();
                    }
                }
            } catch (\Exception $e) {
                error_log('QubitSearchPager::init() error: ' . $e->getMessage());
                $this->nbResults = 0;
                $this->hits = [];
            }
        }

        /**
         * Get the current page results.
         *
         * @return array
         */
        public function getResults()
        {
            return $this->hits;
        }

        /**
         * Get the raw Elastica ResultSet.
         *
         * @return object|null
         */
        public function getResultSet()
        {
            return $this->resultSet;
        }

        // ── Pagination ───────────────────────────────────────────────────

        /**
         * @param  int $page
         */
        public function setPage($page)
        {
            $this->page = max(1, (int) $page);
        }

        /**
         * @return int
         */
        public function getPage()
        {
            return $this->page;
        }

        /**
         * @param  int $maxPerPage
         */
        public function setMaxPerPage($maxPerPage)
        {
            $this->maxPerPage = max(1, (int) $maxPerPage);
        }

        /**
         * @return int
         */
        public function getMaxPerPage()
        {
            return $this->maxPerPage;
        }

        /**
         * @return int
         */
        public function getNbResults()
        {
            return $this->nbResults;
        }

        /**
         * Get the total number of pages.
         *
         * @return int
         */
        public function getLastPage()
        {
            return max(1, (int) ceil($this->nbResults / $this->maxPerPage));
        }

        /**
         * @return bool
         */
        public function haveToPaginate()
        {
            return $this->nbResults > $this->maxPerPage;
        }

        /**
         * @return int
         */
        public function getFirstIndice()
        {
            if (0 === $this->nbResults) {
                return 0;
            }

            return ($this->page - 1) * $this->maxPerPage + 1;
        }

        /**
         * @return int
         */
        public function getLastIndice()
        {
            if (0 === $this->nbResults) {
                return 0;
            }

            return min($this->page * $this->maxPerPage, $this->nbResults);
        }

        /**
         * @return bool
         */
        public function isFirstPage()
        {
            return 1 === $this->page;
        }

        /**
         * @return bool
         */
        public function isLastPage()
        {
            return $this->page >= $this->getLastPage();
        }

        /**
         * @return int
         */
        public function getNextPage()
        {
            return min($this->page + 1, $this->getLastPage());
        }

        /**
         * @return int
         */
        public function getPreviousPage()
        {
            return max($this->page - 1, 1);
        }

        /**
         * Get links for pager navigation.
         *
         * @param  int $nbLinks  Number of links to show
         *
         * @return array
         */
        public function getLinks($nbLinks = 5)
        {
            $links = [];
            $lastPage = $this->getLastPage();
            $half = (int) floor($nbLinks / 2);

            $start = max(1, $this->page - $half);
            $end = min($lastPage, $start + $nbLinks - 1);

            // Adjust start if we're near the end
            if ($end - $start < $nbLinks - 1) {
                $start = max(1, $end - $nbLinks + 1);
            }

            for ($i = $start; $i <= $end; ++$i) {
                $links[] = $i;
            }

            return $links;
        }
    }
}
